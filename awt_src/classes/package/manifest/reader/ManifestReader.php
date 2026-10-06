<?php
namespace package\manifest\reader;
use package\dependency\Dependency;
use package\manifest\exceptions\ManifestReaderException;
use package\manifest\reader\interfaces\IManifestReader;
readonly class ManifestReader implements IManifestReader
{
    public string $package;
    private string $manifestPath;
    public function __construct(string $package)
    {
        $this->package = $package;
        $this->manifestPath = PACKAGES . str_replace(' ', '', $package) . '/manifest.json';
        if (!is_file($this->manifestPath)) throw new ManifestReaderException($package, $this->manifestPath);
    }
    public function getManifest(): array
    {
        return self::readFile($this->manifestPath);
    }
    public static function readFile(string $path): array
    {
        $json = file_get_contents($path);
        if ($json === false) throw new \RuntimeException("Cannot read package manifest: {$path}");
        if (str_starts_with($json, "\xEF\xBB\xBF")) $json = substr($json, 3);
        try {
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $error) {
            throw new \JsonException("Invalid JSON in package manifest {$path}: " . $error->getMessage(), $error->getCode(), $error);
        }
        return self::validate($manifest);
    }
    public static function validate(mixed $manifest): array
    {
        if (!is_array($manifest)) throw new \InvalidArgumentException('Manifest must be a JSON object.');
        foreach (['name', 'version', 'minimum_awt_version'] as $key) {
            if (!isset($manifest[$key]) || !is_string($manifest[$key]) || $manifest[$key] === '') {
                throw new \InvalidArgumentException("Manifest requires string {$key}.");
            }
        }
        foreach (['version', 'minimum_awt_version', 'maximum_awt_version'] as $key) {
            if (isset($manifest[$key]) && (!is_string($manifest[$key]) || !preg_match('/^\d+(?:\.\d+){0,2}(?:-[0-9A-Za-z.-]+)?$/', $manifest[$key]))) {
                throw new \InvalidArgumentException("Invalid manifest version: {$key}");
            }
        }
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9 _-]*$/', $manifest['name'])) {
            throw new \InvalidArgumentException('Invalid package name.');
        }
        if (!isset($manifest['type']) || !is_int($manifest['type']) || !in_array($manifest['type'], [0, 1, 2], true)) {
            throw new \InvalidArgumentException('Manifest type must be 0, 1, or 2.');
        }
        if (!isset($manifest['dependencies']) || !is_array($manifest['dependencies']) || !array_is_list($manifest['dependencies'])) {
            throw new \InvalidArgumentException('Manifest dependencies must be a list.');
        }
        $seen = [];
        foreach ($manifest['dependencies'] as $data) {
            if (!is_array($data)) throw new \InvalidArgumentException('Dependencies must be objects.');
            $dependency = Dependency::fromArray($data);
            if ($dependency->name === $manifest['name'] || isset($seen[$dependency->name])) {
                throw new \InvalidArgumentException('Self or duplicate dependency: ' . $dependency->name);
            }
            $seen[$dependency->name] = true;
        }
        foreach (['maximum_awt_version', 'author', 'description', 'icon', 'preview_image', 'license', 'license_url'] as $key) {
            if (isset($manifest[$key]) && !is_string($manifest[$key])) throw new \InvalidArgumentException("Manifest {$key} must be a string or null.");
        }
        if (isset($manifest['system_package']) && !is_bool($manifest['system_package']) && !in_array($manifest['system_package'], [0, 1], true)) {
            throw new \InvalidArgumentException('Manifest system_package must be boolean or 0/1.');
        }
        return $manifest;
    }
}
