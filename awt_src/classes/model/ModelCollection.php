<?php

namespace model;

use database\DatabaseManager;
use object\ObjectCollection;

abstract class ModelCollection extends DatabaseManager implements \IteratorAggregate, \Countable
{
    private string $model;
    public ObjectCollection $obCollection;

    public function __construct(string $table = "")
    {
        parent::__construct();

        $this->model = $this->getModel();
        if (!is_subclass_of($this->model, Model::class)) throw new \InvalidArgumentException('Collection model must extend Model.');
        if ($table === "") {
            $table = $this->getTable();
        }

        $this->collectionTable = $table;
        $results = $this->table($table)->select()->get();

        $this->obCollection = new ObjectCollection();
        $key = $this->createModel()->id_column ?? 'id';
        $this->obCollection->setKey($key)->setStrictType(Model::class);

        foreach ($results as $result) {
            $model = $this->createModel($result);
            $model->model_source = $table;
            $this->obCollection->add($model);
        }

        $this->obCollection->sortByKey();

    }

    abstract public function getModel(): string;

    protected function createModel(?array $data = null): Model
    {
        $model = (new $this->model(null))->useConnectionFrom($this);
        if ($data !== null) $model->hydrateRow($data, $this->collectionTable, $model->id_column ?? 'id');
        return $model;
    }

    private string $collectionTable = '';
    public function getIterator(): \Traversable { yield from $this->obCollection->toArray(); }
    public function count(): int { return count($this->obCollection->toArray()); }

    protected function getTable(): string
    {
        $model = $this->createModel();

        return $model->inferTableName();
    }
}