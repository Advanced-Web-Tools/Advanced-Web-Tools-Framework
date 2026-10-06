<?php

namespace installer\package;

use package\facade\PackageFacade;
use vfs\storage\enums\EOwnerType;
use vfs\storage\interfaces\IStorageRepository;
use vfs\storage\StorageEntry;
use vfs\storage\StorageRepository;

final readonly class PackageAssetLinks
{
    public function __construct(
        private IStorageRepository $storage = new StorageRepository(),
        private PackageFacade $packages = new PackageFacade(),
    ) {}

    public function register(int $packageId, string $storageDirectory, array $manifest): void
    {
        $entries = $this->storage->fetchByOwnerTypeAndOwner(EOwnerType::PACKAGE, $packageId);
        $links = [];
        foreach (['icon', 'preview_image'] as $field) {
            $reference = $manifest[$field] ?? null;
            if ($reference === null || $reference === '') continue;

            $path = rtrim($storageDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $reference;
            $matches = array_values(array_filter($entries, static fn(StorageEntry $entry): bool => $entry->getPath() === $path));
            if ($matches === [] && basename($reference) === $reference) {
                $matches = array_values(array_filter($entries, static fn(StorageEntry $entry): bool => $entry->getName() === $reference));
            }
            if (count($matches) > 1) {
                throw new \RuntimeException("Ambiguous package {$field}: {$reference}. Use a path relative to the data directory.");
            }
            if ($matches !== []) $links[$field] = $matches[0]->getUrl();
        }

        if ($links === []) return;

        $package = $this->packages->getPackageById($packageId);
        $changed = false;
        if (isset($links['icon']) && $package->getIcon() !== $links['icon']) {
            $package->setIcon($links['icon']);
            $changed = true;
        }
        if (isset($links['preview_image']) && $package->getPreviewImage() !== $links['preview_image']) {
            $package->setPreviewImage($links['preview_image']);
            $changed = true;
        }
        if ($changed && !$package->save()) {
            throw new \RuntimeException('Failed to persist package asset links.');
        }
    }
}
