<?php

namespace cli\commands;

use cli\interfaces\CLICommand;
use installer\package\Extractor;
use installer\package\PackageInstaller;
use installer\package\PackageMover;
use installer\package\PackageStorageTreeGenerator;
use package\facade\PackageFacade;
use Throwable;
use uninstaller\PackageUninstaller;
use vfs\storage\services\LocalFileSystemService;
use vfs\storage\StorageRepository;
use vfs\transient\TransientStorageEntry;
use ZipArchive;

class PackageManagerCommand implements CLICommand
{
    private string $lastResult = '';

    public function getCommand(): string
    {
        return 'pm';
    }

    public function getHelp(): string
    {
        return "Initiates package manager\nUse it to manage packages.";
    }

    public function getArguments(): array
    {
        return [
            "install" => "Installs a package",
            "remove" => "Removes a package",
            "enable" => "Enables a package",
            "disable" => "Disables a package",
            "<path> or <id>" => "For installation, provide a path or URL to a package. For removal, enabling, or disabling, provide package ID.",
            "list" => "Lists all installed packages."
        ];
    }

    public function execute(string $command, array $args = []): void
    {
        $this->lastResult = '';
        $action = $args[0] ?? '';
        $pathOrId = $args[1] ?? '';

        if (empty($action)) {
            $this->lastResult = "No action given.\nSpecify one of the following actions: install, remove, enable, disable, list.\n";
            return;
        }

        switch (strtolower($action)) {
            case 'install':
                if (empty($pathOrId)) {
                    $this->lastResult = "No path given.\nSpecify the path or URL to a zip package.\n";
                    return;
                }
                $this->install($pathOrId);
                break;

            case 'remove':
                if (empty($pathOrId)) {
                    $this->lastResult = "No package ID given for removal.\n";
                    return;
                }
                $this->remove($pathOrId);
                break;

            case 'list':
                $this->listPackages();
                break;
            case 'enable':
                if (empty($pathOrId)) {
                    $this->lastResult = "No package ID given for enabling.\n";
                    return;
                }
                $this->enable($pathOrId);
                break;
            case 'disable':
                if (empty($pathOrId)) {
                    $this->lastResult = "No package ID given for disabling.\n";
                    return;
                }
                $this->disable($pathOrId);
                break;
            default:
                $this->lastResult = "Unknown action: {$action}\nUse install, remove, enable, disable, or list.\n";
                break;
        }
    }

    private function install(string $path): void
    {
        if(str_contains($path, '"'))
            $path = str_replace('"', '', $path);

        if (!str_starts_with($path, 'http') && !file_exists($path)) {
            $this->lastResult = "File not found: {$path}";
            return;
        }

        $tmpFile = tempnam(TEMP, "awt_zip_");
        if ($tmpFile === false) {
            $this->lastResult = "Failed to create temporary file.";
            return;
        }

        try {
            if (str_starts_with($path, 'http')) {
                $fp = fopen($tmpFile, 'w');
                if (!$fp) {
                    $this->lastResult = "Failed to create temporary file.";
                    return;
                }

                $ch = curl_init($path);
                curl_setopt($ch, CURLOPT_FILE, $fp);
                curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
                curl_setopt($ch, CURLOPT_FAILONERROR, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 60);

                if (!curl_exec($ch)) {
                    $error = curl_error($ch);
                    curl_close($ch);
                    fclose($fp);
                    unlink($tmpFile);
                    $this->lastResult = "Failed to download package: {$error}";
                    return;
                }

                curl_close($ch);
                fclose($fp);
            } else {
                if (!copy($path, $tmpFile)) {
                    throw new \RuntimeException("Failed to copy package archive.");
                }
            }

            $facade = new PackageFacade();
            $installer = new PackageInstaller(
                new Extractor(new ZipArchive(), new TransientStorageEntry(basename($tmpFile), $tmpFile), TEMP . 'installer'),
                new PackageMover('', PACKAGES),
                new PackageStorageTreeGenerator(new LocalFileSystemService(), new StorageRepository()),
                $facade->getRepository()
            );
            $installer->execute();

            $this->lastResult = "Package installed successfully.";

        } catch (Throwable $e) {
            $this->lastResult = "Failed to install package: {$e->getMessage()}";
        } finally {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        }
    }

    private function remove(string $packageId): void
    {
        try {
            $uninstaller = new PackageUninstaller();
            if (!$uninstaller->uninstall((int)$packageId)) {
                $this->lastResult = "Failed to remove package: " . implode("\n", $uninstaller->getErrors());
                return;
            }
            $this->lastResult = "Package {$packageId} removed successfully.";
        } catch (Throwable $e) {
            $this->lastResult = "Failed to remove package: {$e->getMessage()}";
        }
    }

    private function enable(string $packageId): void
    {
        try {
            $package = (new PackageFacade())->getPackageById((int)$packageId);
            if ($package->getStatus() !== true) {
                $package->setStatus(true);
                if (!$package->save()) {
                    throw new \RuntimeException('Failed to persist package status.');
                }
            }
            $this->lastResult = "Package {$packageId} enabled.";
        } catch (Throwable $e) {
            $this->lastResult = "Failed to enable package: {$e->getMessage()}";
        }
    }

    private function disable(string $packageId): void
    {
        try {
            $package = (new PackageFacade())->getPackageById((int)$packageId);
            if ($package->getStatus() !== false) {
                $package->setStatus(false);
                if (!$package->save()) {
                    throw new \RuntimeException('Failed to persist package status.');
                }
            }
            $this->lastResult = "Package {$packageId} disabled.";
        } catch (Throwable $e) {
            $this->lastResult = "Failed to disable package: {$e->getMessage()}";
        }
    }

    private function listPackages(): void
    {
        $colors = [
            'id'         => "\033[1;34m",
            'name'       => "\033[1;32m",
            'version'    => "\033[1;33m",
            'min'        => "\033[1;34m",
            'max'        => "\033[1;34m",
            'license'    => "\033[1;36m",
            'author'     => "\033[1;35m",
            'installed'  => "\033[1;32m",
            'system'     => "\033[1;33m",
            'type'       => "\033[1;34m",
            'status'     => "\033[1;36m",
            'reset'      => "\033[0m"
        ];

        $colWidths = [
            'id'          => 5,
            'name'        => 20,
            'version'     => 10,
            'minAwt'      => 10,
            'maxAwt'      => 10,
            'license'     => 12,
            'author'      => 15,
            'installedBy' => 12,
            'system'      => 8,
            'type'        => 10,
            'status'      => 12
        ];

        $this->lastResult = "Installed packages:\n";

        // Header row
        $this->lastResult .= sprintf(
            "%-{$colWidths['id']}s ".
            "%-{$colWidths['name']}s ".
            "%-{$colWidths['version']}s ".
            "%-{$colWidths['minAwt']}s ".
            "%-{$colWidths['maxAwt']}s ".
            "%-{$colWidths['license']}s ".
            "%-{$colWidths['author']}s ".
            "%-{$colWidths['installedBy']}s ".
            "%-{$colWidths['system']}s ".
            "%-{$colWidths['type']}s ".
            "%-{$colWidths['status']}s\n",

            'ID', 'Name', 'Version', 'MinAWT', 'MaxAWT',
            'License', 'Author', 'InstalledBy', 'System', 'Type', 'Status'
        );

        $this->lastResult .= str_repeat('-', array_sum($colWidths) + 20) . "\n";

        $packages = (new PackageFacade())->getService()->getInstalled();

        foreach ($packages as $package) {
            if (!is_object($package)) continue;

            $info = $package->toArray();

            $this->lastResult .= sprintf(
                "%s%-{$colWidths['id']}s%s ".
                "%s%-{$colWidths['name']}s%s ".
                "%s%-{$colWidths['version']}s%s ".
                "%s%-{$colWidths['minAwt']}s%s ".
                "%s%-{$colWidths['maxAwt']}s%s ".
                "%s%-{$colWidths['license']}s%s ".
                "%s%-{$colWidths['author']}s%s ".
                "%s%-{$colWidths['installedBy']}s%s ".
                "%s%-{$colWidths['system']}s%s ".
                "%s%-{$colWidths['type']}s%s ".
                "%s%-{$colWidths['status']}s%s\n",

                $colors['id'],        $info['id'] ?? 'N/A',                $colors['reset'],
                $colors['name'],      $info['name'] ?? 'N/A',              $colors['reset'],
                $colors['version'],   $info['version'] ?? 'N/A',           $colors['reset'],
                $colors['min'],       $info['minimum_awt_version'] ?? 'N/A', $colors['reset'],
                $colors['max'],       $info['maximum_awt_version'] ?? 'N/A', $colors['reset'],
                $colors['license'],   $info['license'] ?? 'N/A',           $colors['reset'],
                $colors['author'],    $info['author'] ?? 'N/A',            $colors['reset'],
                $colors['installed'], $info['installed_by'] ?? 'AWT',       $colors['reset'],
                $colors['system'],    ($package->getSystem() ? 'Yes' : 'No'),            $colors['reset'],
                $colors['type'],      $info['type'] ?? 'N/A',              $colors['reset'],
                $colors['status'],    ($package->getStatus() ? 'Enabled' : 'Disabled'),            $colors['reset']
            );
        }
    }



    public function result(): string
    {
        return $this->lastResult;
    }
}
