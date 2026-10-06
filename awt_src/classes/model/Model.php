<?php

namespace model;

use database\DatabaseManager;
use model\exceptions\ModelCreationException;
use model\exceptions\ModelCRUDException;
use model\interfaces\IRelationBelongs;
use model\interfaces\IRelationHasMany;
use model\interfaces\IRelationWith;
use ReflectionClass;
use ReflectionProperty;
use Throwable;

/**
 * Model Class
 *
 * This abstract class extends the DatabaseManager to provide common
 * functionality for all models, including methods for retrieving
 * records from the database by ID or retrieving all records from a table.
 * It serves as a base class for specific models that represent database
 * entities.
 */
abstract class Model extends DatabaseManager implements \JsonSerializable
{

    public ?string $model_source = null;
    protected ?int $model_id = null;
    public ?string $id_column = null;
    public array $dynamicData = [];

    protected array $paramBlackList = ["tables", "model_source", "id_column"];

    /**
     * Initializes the Model by calling the parent constructor of
     * DatabaseManager.
     */
    private array $ormOriginal = [];
    private array $ormMissingAttributes = [];
    private array $ormLoadedKeys = [];
    private static array $ormLoading = [];
    private array $ormRelationKeys = [];
    private array $ormLastChanges = [];

    public function __construct(
        ?\database\interface\IProvider $provider = null,
        ?\database\query\QueryBuilder $builder = null,
        ?\database\interface\ICache $cache = null,
    ) {
        parent::__construct(
            $provider ?? new \database\provider\DatabaseProvider(),
            $builder ?? new \database\query\QueryBuilder(),
            $cache ?? new \database\cache\DatabaseCache(),
        );
    }

    /**
     * Selects a record from the specified table by its ID and populates the object's properties
     * with the data from the selected row.
     *
     * @param int|null $id The ID of the record to be selected. If null, the method returns without performing any operation.
     * @param string $table Optional. The name of the table to query from. Defaults to an inferred table name if not provided.
     * @param string $column Optional. The column name used to match the ID. Defaults to 'id' if not provided.
     *
     * @return void
     * @throws ModelCreationException
     */
    final public function selectByID(?int $id, string $table = '', string $column = ''): void
    {
        if ($id === null) return;

        $table = $table ?: ($this->model_source ?: $this->inferTableName());
        $column = $column ?: ($this->id_column ?: 'id');

        try {
            $result = $this->table($table)->select()->where([$column => $id])->get(1);
            $this->clearLoadedAttributes();
            $this->model_source = $table;
            $this->id_column = $column;
            if ($result === []) return;
            $this->hydrateRow($result[0], $table, $column);
        } catch (Throwable $e) {
            throw new ModelCreationException($e);
        }
    }

    /**
     * Infers the table name based on the class name of the current object.
     * If the class name is in snake_case, it will be used directly.
     * Otherwise, the class name will be converted from CamelCase to snake_case.
     *
     * @return string The inferred table name in snake_case format.
     */
    public function inferTableName(): string
    {
        $fullClass = get_class($this);
        $exp = explode("\\", $fullClass);
        $shortClass = end($exp);
        return $this->isSnakeCase($shortClass) ? $shortClass : $this->camelToSnake($shortClass);
    }


    /**
     * Loads a related object based on the specified configuration and data row.
     *
     * @param array $row The associative array of data used to load the related object.
     * @return void
     */
    public function loadWith(array $row): void
    {
        if (!($this instanceof IRelationWith)) return;

        $with = $this->with();
        if (empty($with['model']) || empty($with['column'])) return;

        $this->loadRelationObject($with, $row);
    }


    /**
     * Loads a "Belongs To" relationship object for the current model.
     *
     * @param array $row An associative array representing the data row used to load the related object.
     * @return void
     */
    public function loadBelongsTo(array $row): void
    {
        if (!($this instanceof IRelationBelongs)) return;

        $belongs = $this->belongsTo();
        if (empty($belongs['model']) || empty($belongs['column'])) return;

        $this->loadRelationObject($belongs, $row);
    }


    /**
     * Loads and initializes "has many" relationship objects for the current model instance.
     *
     * @param array $row The row of data representing the current model, containing values needed
     *                   to establish relationships.
     * @return void
     */
    public function loadHasMany(array $row): void
    {
        if (!($this instanceof IRelationHasMany)) return;

        $hasMany = $this->hasMany();
        if (empty($hasMany['model']) || empty($hasMany['column'])) return;

        $models = is_array($hasMany['model']) ? $hasMany['model'] : [$hasMany['model']];

        $initialized = [];
        foreach ($models as $model) {
            $model = ltrim($model, '\\');
            if (!is_subclass_of($model, self::class)) throw new \InvalidArgumentException('Related classes must extend Model.');
            $shortName = $hasMany['as'] ?? (new ReflectionClass($model))->getShortName();
            $this->ormRelationKeys[] = $shortName;
            if (!isset($initialized[$shortName])) {
                $this->{$shortName} = [];
                $initialized[$shortName] = true;
            }
            $prototype = new $model(null);
            $prototype->useConnectionFrom($this);
            $table = $hasMany['table'] ?? $prototype->model_source ?? $prototype->inferTableName();
            $key = $hasMany['key'] ?? $prototype->id_column ?? 'id';
            $localKey = $hasMany['localKey'] ?? $this->id_column ?? 'id';
            $localValue = $row[$localKey] ?? $this->model_id;
            if ($localValue === null) continue;
            $rows = $this->find($hasMany['column'], $localValue, $table);
            foreach ($rows as $relatedRow) {
                if (!empty($hasMany['inConstructor'])) {
                    $related = new $model($relatedRow[$key]);
                    $related->useConnectionFrom($this);
                } else {
                    $related = clone $prototype;
                    $related->useConnectionFrom($this);
                    $related->hydrateRow($relatedRow, $table, $key);
                }
                $this->{$shortName}[] = $related;
            }
        }
    }

    /**
     * Loads and initializes a related object based on the provided relation and row data.
     *
     * @param array $relation An associative array defining the relationship, containing details such as the model class and column for foreign key lookup.
     * @param array $row The data row containing information necessary to resolve the relation, typically including the foreign key value.
     * @return void
     */
    protected function loadRelationObject(array $relation, array $row): void
    {
        $modelClass = ltrim($relation['model'], '\\');
        if (!is_subclass_of($modelClass, self::class)) throw new \InvalidArgumentException('Related classes must extend Model.');
        $shortName = $relation['as'] ?? (new ReflectionClass($modelClass))->getShortName();
        $this->ormRelationKeys[] = $shortName;
        $this->{$shortName} = $this->createRelationObject($relation, $row);
    }

    /**
     * Creates and returns a related object based on the provided relationship definition and optional row data.
     *
     * @param array $relation An associative array detailing the relationship, including the model class and column for foreign key lookup.
     * @param array $row Optional associative array containing data for resolving the relation, typically with the foreign key value.
     * @return object|null The created related object if successful, or null if the related model class does not exist.
     */
    protected function createRelationObject(array $relation, array $row = []): ?object
    {
        $modelClass = ltrim($relation['model'], '\\');
        if (!is_subclass_of($modelClass, self::class)) throw new \InvalidArgumentException('Related classes must extend Model.');
        $foreignValue = $row[$relation['column']] ?? null;
        if ($foreignValue === null) return null;
        if (!empty($relation['inConstructor'])) {
            return (new $modelClass($foreignValue))->useConnectionFrom($this);
        }
        $related = (new $modelClass())->useConnectionFrom($this);
        $related->selectByID($foreignValue, $relation['table'] ?? '', $relation['key'] ?? '');
        return $related->existsInDatabase() ? $related : null;
    }

    /**
     * Retrieves all records from the specified table. If no table
     * is provided, it infers the table name from the class name.
     * This method does not populate any properties, as it is
     * intended for fetching data without direct assignment.
     *
     * @param string $table The name of the table to select from.
     * @return array Returns the array of query result;
     */
    final protected function selectAll(string $table = ''): array
    {
        $table = $table ?: ($this->model_source ?: $this->inferTableName());

        return $this->table($table)->select()->get();
    }

    /**
     * Returns the value of the specified property if it exists in
     * the model; otherwise, it returns null. This method is useful
     * for accessing model properties dynamically.
     *
     * @param string $key The name of the property to retrieve.
     * @return mixed The value of the property or null if it does not exist.
     */
    final public function getParam(string $key): mixed
    {
        if (property_exists($this, $key)) {
            $property = new ReflectionProperty($this, $key);
            return $property->isPublic() && $property->isInitialized($this) ? $this->{$key} : null;
        }
        return $this->dynamicData[$key] ?? null;
    }


    public function setModelId(int $id): void
    {
        $this->model_id = $id;
    }


    /**
     * Saves the current state of the model to the database by updating the record
     * associated with the model's identifier column.
     *
     * The method converts the model's properties into an array, applies necessary
     * transformations (such as setting an updated timestamp, if applicable), and
     * removes blacklisted parameters from the update data. It then performs an
     * update operation on the corresponding database table.
     *
     * @return bool True if the update operation was successful, false otherwise.
     * @throws ModelCRUDException
     */

    public function save(): bool
    {

        try {
            if ($this->model_id === null) throw new \LogicException('Cannot update a model without a primary key.');
            $table = $this->model_source ?: $this->inferTableName();
            $column = $this->id_column ?: 'id';
            $update = $this->persistenceAttributes();
            unset($update[$column]);
            if ($this->checkColumn($table, 'updated_on')) {
                $update['updated_on'] = new \database\query\SqlExpression('CURRENT_TIMESTAMP');
            }
            if ($update === []) return false;
            $changes = $this->getDirty();
            foreach ($update as $attribute => $value) {
                if ($value === 'DEFAULT') $update[$attribute] = new \database\query\BoundValue($value);
            }
            $result = $this->table($table)->where([$column => $this->model_id])->update($update);
            if ($result) {
                $this->model_source = $table;
                $this->id_column = $column;
                $this->ormLastChanges = $changes;
                $this->syncOriginal();
            }
            return $result;
        } catch (Throwable $e) {
            throw new ModelCRUDException($e);
        }
    }

    /**
     * Saves the current model to the database by converting it to an array,
     * removing blacklisted parameters, and inserting it into the specified table.
     *
     * @return bool True if the model was successfully saved, false otherwise.
     * @throws ModelCRUDException
     */
    public function saveModel(): int|null
    {
        try {
            $this->model_source ??= $this->inferTableName();
            $this->id_column ??= 'id';
            $save = $this->persistenceAttributes();
            $id = $this->table($this->model_source)->insert($save)->executeInsert();
            if ($id !== null) {
                $this->model_id = $id;
                $this->assignAttribute($this->id_column, $id);
                $this->ormLastChanges = $save;
                $this->syncOriginal();
            }
            return $id;
        } catch (Throwable $e) {
            throw new ModelCRUDException($e);
        }
    }

    /**
     * Deletes the current model from the data source (database) based on the defined identifier column and its value.
     * The identifier column is determined by $id_column, defaulting to "id" if not set.
     *
     * @return bool True if the model was successfully deleted, false otherwise.
     * @throws ModelCRUDException
     */
    public function deleteModel(): bool
    {
        if ($this->id_column === null)
            $this->id_column = "id";

        $where = [$this->id_column => $this->model_id];

        if($this->model_source === null)
            $this->model_source = $this->inferTableName();

        try {
          if ($this->model_id === null) throw new \LogicException('Cannot delete a model without a primary key.');
          $deleted = $this->table($this->model_source)->where($where)->delete();
          if ($deleted) $this->model_id = null;
          return $deleted;
        } catch (Throwable $e) {
            throw new ModelCRUDException($e);
        }
    }


    /**
     * @throws ModelCRUDException
     */
    public function find(string $column, mixed $value, ?string $source = null): array
    {
        $source ??= $this->model_source ?: $this->inferTableName();

        try {
            return $this->table($source)->select()->where([$column => $value])->get();
        } catch (Throwable $e) {
            throw new ModelCRUDException($e);
        }
    }


    /**
     * Adds a given key to the blacklist of parameters.
     *
     * @param string $key The key to be added to the parameter blacklist.
     * @return void
     */
    public function paramBlackList(string $key): void
    {
        $this->paramBlackList[] = $key;
    }


    /**
     * Converts a given string to snake case.
     *
     * @param string $input string for conversion
     * @return string
     */
    protected function camelToSnake(string $input): string
    {
        if ($this->isSnakeCase($input)) {
            return $input;
        }

        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $input);
        return strtolower($snake);
    }


    /**
     * Helper function to determine if classname is snake cased
     * @param string $input
     * @return bool
     */
    private function isSnakeCase(string $input): bool
    {
        return (bool)preg_match('/^[a-z0-9]+(?:_[a-z0-9]+)*$/', $input);
    }


    /** Legacy trusted-data assignment. Use fill() for request input. */
    public function fromArray(array $data): void
    {
        foreach ($data as $key => $value) $this->assignAttribute((string) $key, $value);
    }

    /** Only explicitly allowlisted attributes may be filled from untrusted data. */
    public function fill(array $data): static
    {
        $allowed = array_flip($this->fillableAttributes());
        foreach ($data as $key => $value) {
            if (isset($allowed[$key]) && !in_array($key, ['model_source', 'id_column', 'tables', 'dynamicData'], true)) {
                $this->assignAttribute((string) $key, $value);
            }
        }
        return $this;
    }

    protected function fillableAttributes(): array { return []; }
    protected function hiddenAttributes(): array { return []; }
    protected function attributeCasts(): array { return []; }
    /** null preserves the legacy blacklist-based persistence contract. */
    protected function writableAttributes(): ?array { return null; }

    private function assignAttribute(string $key, mixed $value): void
    {
        if (property_exists($this, $key)) {
            $property = new ReflectionProperty($this, $key);
            if (!$property->isPublic() || $property->isStatic()) {
                throw new \InvalidArgumentException("Cannot assign internal model property: {$key}");
            }
        }
        $cast = $this->attributeCasts()[$key] ?? null;
        if ($value !== null && $cast !== null) {
            $value = match ($cast) {
                'int', 'integer' => (int) $value,
                'float' => (float) $value,
                'string' => (string) $value,
                'bool', 'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
                    ?? throw new \InvalidArgumentException("Invalid boolean attribute: {$key}"),
                'json', 'array' => is_string($value) ? json_decode($value, true, 512, JSON_THROW_ON_ERROR) : $value,
                default => throw new \InvalidArgumentException("Unsupported attribute cast: {$cast}"),
            };
        }
        unset($this->ormMissingAttributes[$key]);
        if (property_exists($this, $key)) $this->{$key} = $value;
        else $this->dynamicData[$key] = $value;
    }

    /** Hydrate an already fetched row without executing a constructor lookup. */
    public function hydrateRow(array $row, ?string $table = null, ?string $key = null): static
    {
        $this->clearLoadedAttributes();
        $this->fromArray($row);
        $this->ormLoadedKeys = array_keys($row);
        $this->model_source = $table ?? $this->model_source ?? $this->inferTableName();
        $this->id_column = $key ?? $this->id_column ?? 'id';
        $this->model_id = isset($row[$this->id_column]) ? (int) $row[$this->id_column] : null;
        $identity = static::class . ':' . $this->model_source . ':' . $this->id_column . ':' . $this->model_id;
        if (!isset(self::$ormLoading[$identity])) {
            self::$ormLoading[$identity] = true;
            try {
                $this->loadWith($row);
                $this->loadBelongsTo($row);
                $this->loadHasMany($row);
            } finally { unset(self::$ormLoading[$identity]); }
        }
        $this->syncOriginal();
        return $this;
    }

    private function clearLoadedAttributes(): void
    {
        $defaults = (new ReflectionClass($this))->getDefaultProperties();
        foreach (array_unique([...$this->ormLoadedKeys, ...array_keys($this->ormOriginal)]) as $key) {
            if (in_array($key, ['model_source', 'id_column', 'tables', 'dynamicData'], true)) continue;
            if (property_exists($this, $key)) {
                $property = new ReflectionProperty($this, $key);
                if (!$property->isPublic() || $property->isReadOnly()) continue;
                if (array_key_exists($key, $defaults)) $this->{$key} = $defaults[$key];
                elseif ($property->getType()?->allowsNull()) $this->{$key} = null;
                else unset($this->{$key});
            }
        }
        $this->dynamicData = [];
        $this->model_id = null;
        $this->ormOriginal = [];
        foreach ($this->ormRelationKeys as $key) unset($this->{$key});
        $this->ormRelationKeys = [];
        $this->ormLastChanges = [];
        $this->ormMissingAttributes = [];
        $this->ormLoadedKeys = [];
    }

    private function persistenceAttributes(): array
    {
        $attributes = array_diff_key($this->__toArray(), array_flip([
            ...$this->paramBlackList, 'tables', 'model_source', 'id_column', ...$this->ormRelationKeys,
        ]));
        $writable = $this->writableAttributes();
        if ($writable !== null) $attributes = array_intersect_key($attributes, array_flip($writable));
        foreach ($attributes as $key => $value) {
            $cast = $this->attributeCasts()[$key] ?? null;
            if ($value !== null && in_array($cast, ['json', 'array'], true)) {
                $attributes[$key] = json_encode($value, JSON_THROW_ON_ERROR);
            } elseif ($value instanceof \BackedEnum) $attributes[$key] = $value->value;
        }
        return $attributes;
    }

    /** Start an independent SELECT using this model's configured table and connection. */
    public function newQuery(): static
    {
        $query = clone $this;
        $query->useConnectionFrom($this);
        $query->clearLoadedAttributes();
        $query->table($this->model_source ?: $this->inferTableName())->select();
        return $query;
    }

    /** Hydrated results from the current fluent query; legacy get() still returns rows. */
    public function getModels(?int $limit = null, ?int $offset = null): array
    {
        $table = $this->queryTable() ?: ($this->model_source ?: $this->inferTableName());
        $key = $this->id_column ?: 'id';
        return array_map(function (array $row) use ($table, $key) {
            $model = clone $this;
            $model->useConnectionFrom($this);
            return $model->hydrateRow($row, $table, $key);
        }, $this->get($limit, $offset));
    }

    public function firstModel(): ?static { return $this->getModels(1)[0] ?? null; }
    public function firstModelOrFail(): static
    {
        return $this->firstModel() ?? throw new \OutOfBoundsException('Model record not found.');
    }

    public function syncOriginal(): static { $this->ormOriginal = $this->persistenceAttributes(); return $this; }
    public function getDirty(): array
    {
        return array_filter($this->persistenceAttributes(), fn($value, $key) =>
            !array_key_exists($key, $this->ormOriginal) || $this->ormOriginal[$key] !== $value, ARRAY_FILTER_USE_BOTH);
    }
    public function isDirty(?string $attribute = null): bool
    {
        $dirty = $this->getDirty();
        return $attribute === null ? $dirty !== [] : array_key_exists($attribute, $dirty);
    }
    public function getChanges(): array { return $this->ormLastChanges; }
    public function existsInDatabase(): bool { return $this->model_id !== null; }
    public function getKey(): ?int { return $this->model_id; }
    public function toArray(): array
    {
        return array_diff_key($this->__toArray(), array_flip(['tables', 'model_source', 'id_column', ...$this->hiddenAttributes()]));
    }
    public function jsonSerialize(): array { return $this->toArray(); }

    /**
     * Generates a string representation of the object by creating a JSON-encoded
     * string of its public properties and their values.
     *
     * @return string A JSON-formatted string representing the public properties of the object.
     */
    public function __toString(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    }


    /**
     * Converts the object's public properties into an associative array.
     * Only includes properties that are publicly accessible.
     *
     * @return array Returns an associative array of the object's public properties.
     */
    public function __toArray(): array
    {
        $reflect = new ReflectionClass($this);
        $vars = $reflect->getProperties(ReflectionProperty::IS_PUBLIC);

        $result = [];

        foreach ($vars as $property) {
            if (!$property->isStatic() && $property->isInitialized($this)) {

                $result[$property->getName()] = $property->getValue($this);

            }
        }

        foreach($this->dynamicData as $key => $value) {
            if ($value === null && isset($this->ormMissingAttributes[$key])) continue;
            $result[$key] = $value;
        }

        unset($result['dynamicData']);


        return $result;
    }

    /**
     * Retrieves the value of a requested property.
     * Checks for both declared properties and dynamic data.
     *
     * @param string $name Name of the property to retrieve.
     * @return mixed Returns the value of the property if it exists, or null if not found.
     */
    public function __isset(string $name): bool
    {
        if (property_exists($this, $name)) {
            $property = new ReflectionProperty($this, $name);
            return $property->isPublic() && $property->isInitialized($this) && $this->{$name} !== null;
        }
        return isset($this->dynamicData[$name]);
    }

    public function &__get(string $name): mixed
    {
        if (property_exists($this, $name)) {
            throw new \LogicException("Model property is internal or uninitialized: {$name}");
        }

        if (array_key_exists($name, $this->dynamicData)) {
            return $this->dynamicData[$name];
        }

        if ($this instanceof IRelationWith) {
            $with = $this->with();
            $modelClass = $with['model'] ?? null;

            if ($modelClass) {
                $parts = explode('\\', $modelClass);
                $shortName = end($parts);
                $alias = $with['as'] ?? $shortName;

                if ($alias === $name) {
                    $this->ormRelationKeys[] = $name;
                    $this->dynamicData[$name] = $this->createRelationObject($with, $this->__toArray());
                    return $this->dynamicData[$name];
                }
            }
        }

        // Initialize null if nothing found
        $this->ormMissingAttributes[$name] = true;
        $this->dynamicData[$name] = null;
        return $this->dynamicData[$name];
    }


    /**
     * Dynamically sets a value to a property.
     * Updates an existing property if it exists, otherwise adds it to dynamic data.
     *
     * @param string $name The name of the property to set.
     * @param mixed $value The value to assign to the property.
     *
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->assignAttribute($name, $value);
    }
}