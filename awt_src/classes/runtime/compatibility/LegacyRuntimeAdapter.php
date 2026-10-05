<?php
namespace runtime\compatibility;

use packages\runtime\api\RuntimeAPI;
use packages\runtime\Runtime;
use runtime\interface\IRuntime;

/** Translates metadata and registries without modifying legacy package classes. */
final class LegacyRuntimeAdapter
{
    public static function installedPackage(Runtime $origin): \package\model\InstalledPackage
    {
        $package = new \package\model\InstalledPackage();
        $package->fromArray([
            'id' => $origin->getId() ?? 0, 'name' => $origin->name,
            'version' => $origin->getVersion() ?? '0.0.0',
            'minimum_awt_version' => $origin->getMinimumAwtVersion(),
            'maximum_awt_version' => $origin->getMaximumAwtVersion(),
            'description' => $origin->description, 'author' => $origin->author,
            'icon' => $origin->icon, 'preview_image' => $origin->previewImage,
            'license' => $origin->license, 'license_url' => $origin->licenseUrl,
            'system_package' => $origin->systemPackage,
            'type' => isset($origin->packageType) ? match ($origin->packageType) {
                \packages\enums\EPackageType::System => 0,
                \packages\enums\EPackageType::Theme => 2,
                default => 1,
            } : 1,
            'status' => $origin->packageStatus === \packages\enums\EPackageStatus::Active,
            'installation_date' => $origin->getInstallationDate() ?? '',
            'store_id' => $origin->getStoreId(), 'installed_by' => $origin->getInstalledBy(),
            'dependencies' => $origin->dependencies,
        ]);
        $package->createDependencyCollection();
        return $package;
    }

    public static function initialize(RuntimeAPI $api, IRuntime $runtime): void
    {
        $package = $runtime->getPackage();
        $origin = new Runtime();
        $origin->setId($package->getId());
        $origin->name = $package->getName();
        $origin->setVersion($package->getVersion());
        $origin->setMinimumAwtVersion($package->getMinimumAwtVersion());
        $origin->setMaximumAwtVersion($package->getMaximumAwtVersion());
        $origin->description = $package->description;
        $origin->author = $package->author;
        $origin->icon = $package->icon;
        $origin->previewImage = $package->preview_image;
        $origin->license = $package->license;
        $origin->licenseUrl = $package->license_url;
        $origin->systemPackage = $package->system_package;
        $origin->dependencies = $package->getDependencies();
        $origin->packageType = match ($package->type) {
            2 => \packages\enums\EPackageType::Theme,
            default => \packages\enums\EPackageType::Plugin,
        };
        $origin->packageStatus = $package->status
            ? \packages\enums\EPackageStatus::Active : \packages\enums\EPackageStatus::Disabled;
        $origin->setStoreId($package->dynamicData['store_id'] ?? null);
        $origin->setInstalledBy($package->dynamicData['installed_by'] ?? null);
        $origin->setInstallationDate($package->installation_date);
        $origin->installedByUsername = (string) ($package->dynamicData['installed_by'] ?? 'AWT');
        $api->setInfo($origin);
    }

    public static function inject(RuntimeAPI $api, IRuntime $runtime, array $shared, array $passables): void
    {
        $api->setSharable($shared);
        $api->passable = $passables;
        $api->eventDispatcher = $runtime->getEventDispatcher();
    }
}
