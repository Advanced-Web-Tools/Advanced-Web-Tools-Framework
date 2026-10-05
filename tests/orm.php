<?php
/** Independent ORM regression checks. Uses SQLite in memory; no application packages. */
error_reporting(E_ALL);
$root = dirname(__DIR__) . '/';
$temp = sys_get_temp_dir() . '/awt-orm-' . bin2hex(random_bytes(6));
define('CLASSES', $root . 'awt_src/classes/');
define('CONFIG', $root . 'awt_data/config/');
define('PACKAGES', $temp . '/packages/');
define('DATA', $temp . '/data/');
require $root . 'awt_src/functions/awt_autoLoader.fun.php';
register_shutdown_function(function () use ($temp) {
    $remove = function ($path) use (&$remove) {
        if (is_dir($path)) { foreach (array_diff(scandir($path), ['.', '..']) as $name) $remove($path . '/' . $name); rmdir($path); }
        elseif (file_exists($path)) unlink($path);
    };
    $remove($temp);
});
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function fails(callable $callback, string $text): void {
    try { $callback(); } catch (Throwable $e) {
        check(str_contains($e->getMessage(), $text), 'Unexpected error: ' . $e->getMessage());
        return;
    }
    throw new RuntimeException('Expected failure: ' . $text);
}
class MemoryCache implements database\interface\ICache {
    public array $entries = [];
    public function get(string $table, string $query): array|false { return $this->entries[$table][$query] ?? false; }
    public function set(string $table, string $query, array $result, array $conditions): void { $this->entries[$table][$query] = $result; }
    public function invalidate(string $table, array $conditions): void { $this->invalidateTable($table); }
    public function invalidateTable(string $table): void { unset($this->entries[$table]); }
}
class SQLiteProvider implements database\interface\IProvider {
    public int $queries = 0;
    public function __construct(public PDO $pdo) {}
    public function execute(string $sql, array $bindings = []): PDOStatement {
        $this->queries++;
        $stmt = $this->pdo->prepare($sql);
        foreach ($bindings as $key => $value) $stmt->bindValue($key, $value, match (true) {
            $value === null => PDO::PARAM_NULL, is_bool($value) => PDO::PARAM_BOOL,
            is_int($value) => PDO::PARAM_INT, default => PDO::PARAM_STR,
        });
        $stmt->execute();
        return $stmt;
    }
    public function lastInsertId(): int { return (int) $this->pdo->lastInsertId(); }
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$provider = new SQLiteProvider($pdo);
$cache = new MemoryCache();
$shared['DBEngine']['PDO'] = $pdo;
$pdo->exec("CREATE TABLE users(id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, enabled INTEGER, profile TEXT, deleted_at TEXT, password TEXT, token TEXT);
CREATE TABLE children(child_key INTEGER PRIMARY KEY, user_id INTEGER, name TEXT);
CREATE TABLE awt_table(id INTEGER PRIMARY KEY, name TEXT);
CREATE TABLE awt_table_structure(table_id INTEGER, column_name TEXT);
INSERT INTO awt_table VALUES(1,'users'),(2,'children');
INSERT INTO awt_table_structure VALUES(1,'name'),(2,'name');
INSERT INTO users VALUES(1,'Alice',1,'{\"n\":1}',NULL,'secret','token'),(2,'Bob',0,'{}',NULL,'secret2','token2');
INSERT INTO children VALUES(10,1,'A'),(11,1,'B');");
class UserModel extends model\Model {
    public ?int $id = null;
    public string $name;
    public bool $enabled = false;
    public array $profile = [];
    public ?string $deleted_at = null;
    public ?string $password = null;
    public ?string $token = null;
    public function __construct(?int $id = null) {
        parent::__construct($GLOBALS['provider'], null, $GLOBALS['cache']);
        $this->model_source = 'users';
        if ($id !== null) $this->selectByID($id);
    }
    protected function fillableAttributes(): array { return ['name', 'enabled', 'profile']; }
    protected function hiddenAttributes(): array { return ['password', 'token']; }
    protected function attributeCasts(): array { return ['enabled' => 'bool', 'profile' => 'json']; }
    public function allRows(): array { return $this->selectAll(); }
}
class ChildModel extends model\Model {
    public ?int $child_key = null;
    public ?int $user_id = null;
    public string $name;
    public function __construct(?int $id = null) {
        parent::__construct($GLOBALS['provider'], null, $GLOBALS['cache']);
        $this->model_source = 'children'; $this->id_column = 'child_key';
        if ($id !== null) $this->selectByID($id);
    }
}
class FamilyModel extends UserModel implements model\interfaces\IRelationHasMany {
    public function hasMany(): array { return ['model' => ChildModel::class, 'column' => 'user_id', 'as' => 'children', 'inConstructor' => false]; }
}
class Users extends model\ModelCollection { public function getModel(): string { return UserModel::class; } }
$user = new UserModel(1);
check($user->name === 'Alice' && $user->enabled && $user->profile === ['n' => 1], 'Typed hydration');
check(!$user->isDirty() && $user->getKey() === 1, 'Hydration snapshot');
$user->fill(['name' => 'Updated', 'password' => 'attack', 'model_source' => 'children', 'model_id' => 2]);
check($user->name === 'Updated' && $user->password === 'secret' && $user->model_source === 'users' && $user->getKey() === 1, 'Safe fill');
check($user->isDirty('name'), 'Dirty tracking');
check($user->save(), 'Save succeeds with table-name schema lookup');
check(!$user->isDirty() && $user->getChanges()['name'] === 'Updated', 'Save snapshot');
check($pdo->query('SELECT name FROM users WHERE id=1')->fetchColumn() === 'Updated', 'Stored update');
fails(fn() => $user->fromArray(['paramBlackList' => []]), 'internal model property');
fails(fn() => $user->fromArray(['model_id' => 2]), 'internal model property');
fails(fn() => $user->fill(['enabled' => 'maybe']), 'Invalid boolean');
check(!array_key_exists('password', $user->toArray()) && !str_contains(json_encode($user), 'secret'), 'Hidden serialization');
$partial = new UserModel();
check(!array_key_exists('name', $partial->__toArray()), 'Partial serialization');
$missing = $partial->not_present;
check(!array_key_exists('not_present', $partial->__toArray()), 'Missing read does not pollute persistence');
$user->selectByID(999);
check(!$user->existsInDatabase() && $user->getKey() === null && $user->password === null, 'Missing lookup clears old state');
fails(fn() => $user->save(), 'without a primary key');
fails(fn() => $user->deleteModel(), 'without a primary key');
$new = (new UserModel())->fill(['name' => 'Cara', 'enabled' => 'false', 'profile' => ['n' => 2]]);
$id = $new->saveModel();
check($id === $new->id && $id === $new->getKey(), 'Insert assigns identity');
check($new->save(), 'Inserted model can update');
check(count($new->allRows()) === 3, 'Legacy selectAll resolves concrete table');
$db = new database\DatabaseManager($provider, new database\query\QueryBuilder(), $cache);
check($db->table('users')->select()->where(['users.id' => 1])->first()['name'] === 'Updated', 'Qualified placeholders');
check($db->table('users')->select()->where(['enabled' => 0])->where(['id' => 1])->first()['id'] === 1, 'Legacy replacement clears bindings');
check($db->table('users')->select()->where(['id' => 1])->andWhere(['enabled' => 0])->get() === [], 'AND composition');
check(count($db->table('users')->select()->where(['id' => 1])->orWhere(['id' => 2])->get()) === 2, 'Repeated column OR placeholders');
check(count($db->table('users')->select()->where(['deleted_at' => null])->get()) === 3, 'IS NULL');
check($db->table('users')->select()->where(['id' => 1])->like(['name' => 'Upd%'])->first()['id'] === 1, 'Legacy LIKE replacement');
check(count($db->table('users')->select(['users.id'])->joinOn('children','users.id','children.user_id')->get()) === 2, 'Structured join');
fails(fn() => (new database\query\QueryBuilder())->table('users; DROP TABLE users'), 'Invalid SQL identifier');
fails(fn() => (new database\query\QueryBuilder())->table('users')->where(['id OR 1=1' => 1]), 'Invalid SQL identifier');
fails(fn() => (new database\query\QueryBuilder())->table('users')->orderBy('id; DELETE FROM users'), 'Invalid SQL identifier');
fails(fn() => (new database\query\QueryBuilder())->table('users')->select()->buildSelect(-1), 'nonnegative');
fails(fn() => (new database\query\QueryBuilder())->table('users')->buildDelete(), 'without a WHERE');
$family = new FamilyModel(1);
check(count($family->children) === 2 && $family->children[0]->getKey() === 10, 'Custom relationship table/key');
$before = $provider->queries;
$family->loadHasMany($family->__toArray());
check(count($family->children) === 2 && $provider->queries - $before <= 1, 'Relationship reload avoids duplicates and N+1');
$family->name = 'Family';
check($family->save(), 'Relations excluded from persistence');
$models = (new UserModel())->table('users')->select()->orderBy('id')->getModels(2);
check(count($models) === 2 && $models[0] instanceof UserModel && !$models[0]->isDirty(), 'Model retrieval');
$users = new Users('users');
check(count($users) === 3 && iterator_to_array($users)[0] instanceof UserModel, 'Explicit-table iterable collection');
check($new->deleteModel() && !$new->existsInDatabase(), 'Delete clears persisted state');
$defaultProvider = new database\provider\DatabaseProvider();
$statement = $defaultProvider->execute('SELECT :n IS NULL AS n, :b AS b', [':n' => null, ':b' => false]);
check($statement->fetch(PDO::FETCH_ASSOC) === ['n' => 1, 'b' => 0], 'Default provider NULL/boolean binding');
// Value semantics, custom keys, legacy hooks, and failure cleanup.
$literal = new UserModel(1);
$literal->fill(['name' => 'DEFAULT']);
check($literal->save() && $pdo->query('SELECT name FROM users WHERE id=1')->fetchColumn() === 'DEFAULT', 'Filled DEFAULT stays literal');
$literal->fromArray(['extra' => 'value']);
check($literal->getParam('extra') === 'value' && isset($literal->extra), 'Dynamic attribute access');
check($literal->getParam('model_id') === null, 'Internal state not exposed by getParam');
$child = new ChildModel(10);
$child->name = 'Changed';
check($child->save() && $pdo->query('SELECT name FROM children WHERE child_key=10')->fetchColumn() === 'Changed', 'Custom-key update');
class ChildWithOwner extends ChildModel implements model\interfaces\IRelationBelongs {
    public function belongsTo(): array { return ['model' => UserModel::class, 'column' => 'user_id', 'as' => 'owner']; }
}
$withOwner = new ChildWithOwner(10);
check($withOwner->owner instanceof UserModel && $withOwner->owner->getKey() === 1, 'Belongs-to custom model');
$withOwner->name = 'Owner relation';
check($withOwner->save(), 'Belongs-to excluded from persistence');
$withOwner->hydrateRow(['child_key' => 100, 'user_id' => null, 'name' => 'Orphan'], 'children', 'child_key');
check($withOwner->owner === null, 'Nullable missing relationship');
class TimestampModel extends model\Model {
    public ?int $id = null;
    public string $name = 'timestamp';
    public ?string $updated_on = null;
    public function __construct(?int $id = null) {
        parent::__construct($GLOBALS['provider'], null, $GLOBALS['cache']);
        $this->model_source = 'timestamps';
        if ($id !== null) $this->selectByID($id);
    }
}
$pdo->exec("CREATE TABLE timestamps(id INTEGER PRIMARY KEY, name TEXT, updated_on TEXT); INSERT INTO timestamps VALUES(1,'old',NULL);
INSERT INTO awt_table VALUES(3,'timestamps'); INSERT INTO awt_table_structure VALUES(3,'updated_on');");
$timestamp = new TimestampModel(1);
check($timestamp->save() && $pdo->query('SELECT updated_on FROM timestamps')->fetchColumn() !== null, 'Timestamp uses explicit SQL expression');
class CyclicModel extends model\Model implements model\interfaces\IRelationBelongs {
    public ?int $id = null;
    public ?int $parent_id = null;
    public function __construct(?int $id = null) {
        parent::__construct($GLOBALS['provider'], null, $GLOBALS['cache']);
        $this->model_source = 'cycles';
        if ($id !== null) $this->selectByID($id);
    }
    public function belongsTo(): array { return ['model' => self::class, 'column' => 'parent_id', 'as' => 'parent']; }
}
$pdo->exec('CREATE TABLE cycles(id INTEGER PRIMARY KEY, parent_id INTEGER); INSERT INTO cycles VALUES(1,2),(2,1);');
$cycle = new CyclicModel(1);
check($cycle->parent->parent->getKey() === 1, 'Cyclic loading is bounded');
check(json_encode($cycle) !== false, 'Bounded relationships serialize');
fails(fn() => $db->transaction(fn() => null), 'does not support transactions');
$txCache = new MemoryCache();
$tx = new database\DatabaseManager($defaultProvider, new database\query\QueryBuilder(), $txCache);
$readName = fn() => $tx->table('users')->select(['name'])->where(['id' => 1])->first()['name'];
check($readName() === 'DEFAULT', 'Initial transaction cache');
fails(function () use ($tx, $readName) {
    $tx->transaction(function ($db) use ($readName) {
        $db->table('users')->where(['id' => 1])->update(['name' => 'uncommitted']);
        check($readName() === 'uncommitted', 'Transaction reads bypass stale cache');
        throw new RuntimeException('rollback test');
    });
}, 'rollback test');
check($readName() === 'DEFAULT', 'Rollback preserves committed cache and database');
check($tx->transaction(function ($db) {
    $db->table('users')->where(['id' => 1])->update(['name' => 'committed']);
    return 'result';
}) === 'result', 'Transaction return value');
check($readName() === 'committed', 'Commit invalidates query cache');
$tx->transaction(function ($db) use ($txCache) {
    fails(function () use ($db) {
        $db->transaction(function ($nested) {
            $nested->table('users')->where(['id' => 1])->update(['name' => 'nested rollback']);
            throw new RuntimeException('nested test');
        });
    }, 'nested test');
    check($db->table('users')->select(['name'])->where(['id' => 1])->first()['name'] === 'committed', 'Nested savepoint rollback');
    $db->transaction(fn($nested) => $nested->table('users')->where(['id' => 1])->update(['name' => 'nested commit']));
});
check($readName() === 'nested commit', 'Nested commit invalidates at outer commit');
// A separate provider sharing the same PDO participates in the transaction.
$other = new database\DatabaseManager(new database\provider\DatabaseProvider(), new database\query\QueryBuilder(), $txCache);
$tx->transaction(fn() => $other->table('users')->where(['id' => 1])->update(['name' => 'shared transaction']));
check($readName() === 'shared transaction', 'Shared-provider commit callbacks');
class LegacyOverride extends UserModel {
    public int $hookCalls = 0;
    public function __construct(?int $id = null) { parent::__construct($id); $this->paramBlackList('hookCalls'); }
    public function save(): bool { $this->hookCalls++; return parent::save(); }
}
$legacy = new LegacyOverride(1);
check($legacy->save() && $legacy->hookCalls === 1, 'Legacy save override and blacklist');
$package = new package\model\InstalledPackage();
$package->fromArray(['id' => 1, 'name' => 'Metadata', 'version' => '1.0.0', 'minimum_awt_version' => '27.0.0', 'type' => 1, 'status' => true,
    'installation_date' => '2026-10-05', 'dependencies' => [], 'model_source' => 'awt_package', 'id_column' => 'id']);
$package->createDependencyCollection();
check($package->getName() === 'Metadata' && $package->getDependencies() === [], 'Legacy package metadata hydration');
$readonly = (new UserModel())->table('users')->select(['id', 'name'])->firstModel();
check($readonly instanceof UserModel && $readonly->getKey() === 1, 'Partial model query');
$query = (new UserModel())->newQuery();
check($query->where(['id' => 1])->firstModelOrFail()->id === 1, 'Independent model query');
fails(fn() => (new UserModel())->newQuery()->where(['id' => 999])->firstModelOrFail(), 'record not found');
class SharedAliasFamily extends UserModel implements model\interfaces\IRelationHasMany {
    public function hasMany(): array { return ['model' => [ChildModel::class, ChildWithOwner::class], 'column' => 'user_id', 'as' => 'children']; }
}
$alias = new SharedAliasFamily(1);
check(count($alias->children) === 4, 'Multiple legacy models preserve shared alias items');
$alias->loadHasMany($alias->__toArray());
check(count($alias->children) === 4, 'Shared alias reload replaces previous items');
$inserted = (new UserModel())->fill(['name' => 'Transient']);
$inserted->saveModel();
$inserted->selectByID(999);
check($inserted->getParam('name') === null && !$inserted->existsInDatabase(), 'Missing lookup clears inserted-model snapshot');
echo "ORM: {$checks} checks passed.\n";
