<?php

namespace package\model\repository;

use database\DatabaseManager;
use database\trait\DoNotCache;
use package\manifest\reader\interfaces\IManifestReader;
use package\model\repository\interfaces\IPackageRepository;

class PackageRepository extends DatabaseManager implements IPackageRepository
{

    use DoNotCache;

    private array $append = [
        "model_source" => "awt_package",
        "id_column" => "id",
    ];

    public function getActive(): array
    {
        $results = $this->table('awt_package')->select()->where(["status" => 1])->get();
        return array_map(fn($item) => array_merge($item, $this->append), $results);
    }

    public function getDisabled(): array
    {
        $results = $this->table('awt_package')->select()->where(["status" => 0])->get();
        return array_map(fn($item) => array_merge($item, $this->append), $results);

    }

    public function getAll(): array
    {
        $results = $this->table('awt_package')->select()->where([1 => 1])->get();
        return array_map(fn($item) => array_merge($item, $this->append), $results);
    }

    public function getPackage(string $name): ?array
    {
        $result = $this->table('awt_package')->select()->where(["name" => $name])->get()[0] ?? null;
        return $result ? array_merge($result, $this->append) : null;
    }

    public function newPackage(IManifestReader $manifestReader): ?int
    {
        return $this->table('awt_package')->insert($manifestReader->getManifest())->executeInsert();
    }
}