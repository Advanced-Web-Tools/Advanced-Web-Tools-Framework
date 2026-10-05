# ORM usage and compatibility

The ORM retains `selectByID()`, `find()`, `save()`, `saveModel()`,
`deleteModel()`, `fromArray()`, `paramBlackList()`, the relationship interfaces,
and array-returning database queries. Existing no-argument calls to the base
model constructor continue to work. This update does not change the application
schema or inspect installed packages.

## Safe assignment and output

Define an allowlist before accepting request data:

```php
class Product extends \model\Model
{
    public ?int $id = null;
    public string $name;
    public int $stock = 0;
    public array $options = [];

    protected function fillableAttributes(): array
    {
        return ['name', 'stock', 'options'];
    }

    protected function attributeCasts(): array
    {
        return ['stock' => 'int', 'options' => 'json'];
    }

    protected function hiddenAttributes(): array
    {
        return ['supplier_token'];
    }
}

$product = new Product();
$product->fill($validatedInput);
$id = $product->saveModel();
```

`fill()` ignores non-allowlisted attributes and prevents assignment of framework
metadata. It is not a replacement for business validation or authorization.
`fromArray()` remains for trusted public attributes and metadata; assigning
protected/private ORM internals now throws. Direct public-property assignment
remains supported, and bypasses casting, as in ordinary PHP.

`toArray()`, `json_encode($model)`, and string conversion omit hidden fields and
framework metadata. Core admin models hide password and token fields.
`__toArray()` retains public-property output for legacy callers, except that
uninitialized fields and null attributes created only by a missing-property read
are omitted. Do not use this legacy method to publish sensitive models.

Persistence continues to honor `paramBlackList()`. Loaded relationships and
framework metadata are excluded automatically. Optionally override
`writableAttributes(): ?array` to explicitly name persisted columns; its default
`null` preserves the blacklist contract. Output hiding does not prevent a field
from being persisted.

Casts support `int`/`integer`, `float`, `string`, `bool`/`boolean`, and
`json`/`array`. JSON is decoded on assignment and encoded for persistence.
Nulls remain null, subject to declared PHP property types. Boolean casts reject
unrecognized boolean values. Database enums are persisted through their backing
values.

## Identity and changes

`saveModel()` still inserts and returns `int|null`; successful auto-increment
inserts now populate the key on the model. `save()` still updates and returns a
boolean indicating changed rows; it does not automatically insert a new model.
Updating or deleting without a model key throws `ModelCRUDException`.

`getKey()` and `existsInDatabase()` expose tracked identity. The existence flag
means the model has an assigned/hydrated key; it does not perform an existence
query. Existing integer-key contracts remain in place.

`isDirty()`, `isDirty('name')`, and `getDirty()` compare persistable values with
the last hydration/successful save snapshot. `getChanges()` reports changes
captured by a successful save. `syncOriginal()` accepts the current values as the
baseline. Legacy `save()` still writes the full persistable attribute set; dirty
tracking does not silently alter write behavior.

Loading a missing ID clears the previous hydrated record and relationships.
`selectByID(null)` remains a no-op. SQL-generated timestamps/defaults are not
automatically reloaded; reload the record to obtain the database-generated value.
Models treat the string `DEFAULT` as literal data during save. To request a SQL
expression, use `new database\query\SqlExpression('DEFAULT')` or
`SqlExpression('CURRENT_TIMESTAMP')` in a compatible untyped/dynamic field.

## Queries

Legacy `where()` and `like()` replace existing predicates. Replaced bindings are
now removed. Use the new methods for composition:

```php
$rows = $database->table('products')->select()
    ->where(['active' => true])
    ->andWhere(['deleted_at' => null])
    ->orWhere(['id' => 42])
    ->get();
```

Composition groups the accumulated predicate, so the example means
`(active AND deleted_at IS NULL) OR id = 42`. Null comparisons use `IS NULL` or
`IS NOT NULL`. Generated parameter names are independent of column names.

`first()` returns an associative row or null. Models add `getModels($limit,
$offset)` and `firstModel()` to an already prepared fluent query; legacy `get()`
and `find($column, $value, $source)` still return rows. `newQuery()` starts an
independent SELECT with the model's table/connection. `firstModelOrFail()` throws
`OutOfBoundsException` when no record matches:

```php
$product = (new Product())->newQuery()->where(['id' => 1])->firstModelOrFail();
$products = (new Product())->newQuery()->where(['stock' => 10])->getModels(20, 0);
```

Model retrieval clones
its configured prototype and hydrates fetched rows without invoking another
constructor lookup. Use a fresh prototype if it contains unrelated application
state. Pass the key in the selected columns if results need to be saved.

Table/filter/write/sort identifiers now reject SQL fragments. Supported forms
are ordinary identifiers, dot-qualified identifiers, and backtick-quoted ordinary
identifiers. Numeric WHERE operands used by legacy unconditional queries remain
supported. `select()` expressions and legacy `join()` ON strings remain trusted
SQL for compatibility: never pass request input into them. `joinOn()` provides
validated identifier-based equality JOINs. The low-level `update()` API retains
its historical string `DEFAULT` sentinel; use `BoundValue('DEFAULT')` to bind that
literal. Negative pagination and empty writes are rejected.

## Relationships and collections

Existing `with()`, `belongsTo()`, and `hasMany()` definitions remain supported.
Definitions can optionally specify `table`, `key`, and (for has-many) `localKey`.
`as` names the relationship property. `inConstructor => true` retains legacy
ID-in-constructor loading, including its constructor side effects; false/omitted
has-many loading hydrates fetched rows directly. Avoid constructor queries when
using a custom connection: a legacy constructor can run before injection.

Default has-many loading uses the configured related table/key, replaces items
on reload, and avoids a second query per child. Null/missing singular targets
return null. Related classes must extend `Model`. Loading detects active
class/table/key identities and stops expanding repeated nodes in cyclic graphs.
Relationship hydration is still eager and per-parent; batched eager loading,
multiple named relationship definitions, and arbitrary string primary keys are
not introduced by this update.

`ModelCollection('explicit_table')` now resolves its model correctly. Collections
are iterable and countable, and retain `obCollection`. Existing collection
constructors still load eagerly; use limited fluent model queries for large sets.

## Connections and transactions

The base model constructor accepts optional provider, builder, and cache
arguments. `useConnectionFrom($source)` shares the provider/cache while creating
an independent query builder. Ordinary relation loading inherits that context.

The default provider implements the optional `ITransactionalProvider` capability;
existing `IProvider` implementations do not need new methods.

```php
$database->transaction(function ($db) {
    $db->table('products')->where(['id' => 1])->update(['stock' => 10]);
    $db->table('products')->where(['id' => 2])->update(['stock' => 20]);
});
```

Exceptions roll back. Nested calls use savepoints. Reads bypass query-result
caches during a transaction, and writes defer invalidation until commit. Providers
sharing the default PDO connection also share commit callbacks. Transactions
opened externally on PDO are not managed by this API. Avoid DDL inside these
transactions on databases that implicitly commit it.

Rollback restores database state, not existing PHP model objects; reload them
before reuse. Custom providers must implement the optional capability to use
`transaction()`. Existing schema lookup still uses the framework's table registry.

## Verification

```sh
php tests/orm.php
```

The suite uses in-memory SQLite and temporary cache directories. It never reads
`awt_packages` or connects to the configured application database. MySQL-specific
DDL/default behavior and application package compatibility require separate
integration verification.
