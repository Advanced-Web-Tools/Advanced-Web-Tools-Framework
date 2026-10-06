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
            "update" => "Updates an installed package",
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
            $this->lastResult = "No action given.\nSpecify one of the following actions: install, update, remove, enable, disable, list.\n";
            return;
        }

        switch (strtolower($action)) {
            case 'install':
            case 'update':
                if (empty($pathOrId)) {
                    $this->lastResult = "No path given.\nSpecify the path or URL to a zip package.\n";
                    return;
                }
                $this->install($pathOrId, strtolower($action) === 'update');
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
                $this->lastResult = "Unknown action: {$action}\nUse install, update, remove, enable, disable, or list.\n";
                break;
        }
    }

    private function install(string $path, bool $updating = false): void
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
            if ($updating) {
                if (!$installer->update()) throw new \RuntimeException('Package update failed.');
            } else {
                $installer->execute();
            }

            $this->lastResult = $updating ? "Package updated successfully." : "Package installed successfully.";

        } catch (Throwable $e) {
            $this->lastResult = ($updating ? "Failed to update package: " : "Failed to install package: ") . $e->getMessage();
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
            (new PackageFacade())->enablePackage((int) $packageId);
            $this->lastResult = "Package {$packageId} enabled.";
        } catch (Throwable $e) {
            $this->lastResult = "Failed to enable package: {$e->getMessage()}";
        }
    }

    private function disable(string $packageId): void
    {
        try {
            (new PackageFacade())->disablePackage((int) $packageId);
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
            'system'     => "\033[1;33m",
            'type'       => "\033[1;34m",
            'status'     => "\033[1;36m",
            'reset'      => "\033[0m"
        ];

        $headers = [
            'id' => 'ID',
            'name' => 'Name',
            'version' => 'Version',
            'min' => 'MinAWT',
            'max' => 'MaxAWT',
            'license' => 'License',
            'author' => 'Author',
            'system' => 'System',
            'type' => 'Type',
            'status' => 'Status'
        ];

        $displayWidth = static fn(string $value): int => function_exists('mb_strwidth')
            ? mb_strwidth($value, 'UTF-8')
            : strlen($value);
        $colWidths = array_map($displayWidth, $headers);
        $rows = [];
        $packages = (new PackageFacade())->getService()->getInstalled();

        foreach ($packages as $package) {
            if (!is_object($package)) continue;

            $info = $package->toArray();
            $row = [
                'id' => $info['id'] ?? 'N/A',
                'name' => $info['name'] ?? 'N/A',
                'version' => $info['version'] ?? 'N/A',
                'min' => $info['minimum_awt_version'] ?? 'N/A',
                'max' => $info['maximum_awt_version'] ?? 'N/A',
                'license' => $info['license'] ?? 'N/A',
                'author' => $info['author'] ?? 'N/A',
                'system' => $package->getSystem() ? 'Yes' : 'No',
                'type' => $info['type'] ?? 'N/A',
                'status' => $package->getStatus() ? 'Enabled' : 'Disabled'
            ];

            foreach ($row as $column => $value) {
                $row[$column] = (string) $value;
                $colWidths[$column] = max($colWidths[$column], $displayWidth($row[$column]));
            }
            $rows[] = $row;
        }

        $pad = static fn(string $value, string $column): string => $value
            . str_repeat(' ', $colWidths[$column] - $displayWidth($value));

        $headerCells = [];
        foreach ($headers as $column => $label) {
            $headerCells[] = $pad($label, $column);
        }

        $this->lastResult = "Installed packages:\n";
        $this->lastResult .= implode(' ', $headerCells) . "\n";
        $this->lastResult .= str_repeat('-', array_sum($colWidths) + count($headers) - 1) . "\n";

        foreach ($rows as $row) {
            $cells = [];
            foreach ($row as $column => $value) {
                $cells[] = $colors[$column] . $pad($value, $column) . $colors['reset'];
            }
            $this->lastResult .= implode(' ', $cells) . "\n";
        }
    }


    public function result(): string
    {
        return $this->lastResult;
    }
}
