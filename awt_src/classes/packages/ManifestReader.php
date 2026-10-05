<?php
namespace packages;
use packages\enums\EPackageType;

/** Retains the old reader API, but reads the new installation manifest. */
class ManifestReader extends Package
{
    public array $manifest;
    public function __construct(private string $path) { parent::__construct(); }
    public function readManifest(): self
    {
        $this->manifest = \package\manifest\reader\ManifestReader::validate(
            json_decode(file_get_contents($this->path . '/manifest.json'), true, 512, JSON_THROW_ON_ERROR)
        );
        return $this;
    }
    public function getManifest(): array { return $this->manifest; }
    public function createPackage(): ?Package
    {
        $values = $this->manifest;
        $package = new Package();
        $package->name = $values['name'];
        $package->setStoreId($values['store_id'] ?? null);
        $package->description = $values['description'] ?? null;
        $package->setIcon($values['icon'] ?? null);
        $package->setMinimumAwtVersion($values['minimum_awt_version']);
        $package->setMaximumAwtVersion($values['maximum_awt_version'] ?? null);
        $package->setVersion($values['version']);
        $package->systemPackage = (bool) ($values['system_package'] ?? false);
        $package->author = $values['author'] ?? null;
        $package->license = $values['license'] ?? null;
        $package->licenseUrl = $values['license_url'] ?? null;
        $package->dependencies = $values['dependencies'];
        $package->setPackageType(match ($values['type']) {
            0 => EPackageType::System, 1 => EPackageType::Plugin, 2 => EPackageType::Theme,
        });
        $package->setPreviewImage($values['preview_image'] ?? null);
        return $package;
    }
}
