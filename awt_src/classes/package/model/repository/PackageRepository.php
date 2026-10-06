<?php

namespace package\model\repository;

use database\DatabaseManager;
use database\trait\DoNotCache;
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
        $results = $this->table('awt_package')->select()->where(["status" => 1])->orderBy("system_package", "DESC")->get();
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

    public function getPackageById(int $id): ?array
    {
        $result = $this->table('awt_package')->select()->where(['id' => $id])->get()[0] ?? null;
        return $result ? array_merge($result, $this->append) : null;
    }

    public function setStatus(int $id, bool $status): bool
    {
        return $this->table('awt_package')->where(['id' => $id])->update(['status' => (int) $status]);
    }

    public function updatePackage(int $id, array $data): bool
    {
        $data = $this->manifestData($data);
        if (!empty($data['system_package'])) $data['status'] = 1;
        $existing = $this->getPackageById($id);
        if ($existing === null) return false;
        $changed = false;
        foreach ($data as $key => $value) {
            if (!array_key_exists($key, $existing)
                || ($value === null ? $existing[$key] !== null : $existing[$key] === null || (string) $existing[$key] !== (string) $value)) {
                $changed = true;
                break;
            }
        }
        // MySQL reports zero affected rows when identical metadata is written.
        if (!$changed) return true;
        return $this->table('awt_package')->where(['id' => $id])->update($data);
    }

    public function newPackage(array $data): ?int
    {
        $data = $this->manifestData($data);
        $data['status'] = !empty($data['system_package']) ? 1 : 0;
        $data['installation_date'] = date('Y-m-d H:i:s');
        return $this->table('awt_package')->insert($data)->executeInsert();
    }

    private function manifestData(array $data): array
    {
        $data = array_intersect_key($data, array_flip([
            'name', 'author', 'description', 'icon', 'preview_image', 'version',
            'minimum_awt_version', 'maximum_awt_version', 'type', 'system_package',
            'license', 'license_url', 'dependencies', 'store_id',
        ]));
        $data['dependencies'] = json_encode($data['dependencies'] ?? [], JSON_THROW_ON_ERROR);
        return $data;
    }
}
