<?php

namespace installer\web;

use installer\framework\FrameworkInstallService;
use installer\framework\InstallationRequirements;
use installer\package\Extractor;
use installer\package\PackageDownload;
use installer\package\PackageInstaller;
use installer\package\PackageMover;
use installer\package\PackageStorageTreeGenerator;
use installer\profiles\CmsProfile;
use installer\profiles\FrameworkProfile;
use installer\profiles\IProfile;
use package\facade\PackageFacade;
use PDO;
use vfs\storage\services\LocalFileSystemService;
use vfs\storage\StorageRepository;
use vfs\transient\TransientStorageEntry;

final class WebInstaller
{
    private const JOURNAL = CONFIG . '.awt-installation.php';
    private array $state = [];
    private FrameworkInstallService $installer;
    private ?PDO $pdo = null;
    private bool $mutating = false;

    public function __construct()
    {
        $this->installer = new FrameworkInstallService();
    }

    public function handle(): void
    {
        if (DB_TYPE !== '' || file_exists(CONFIG . 'awt_db.php')) {
            http_response_code(404);
            return;
        }
        if (parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) !== '/') {
            http_response_code(404);
            echo 'The installer is available at /.';
            return;
        }
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: no-referrer');
        if (!extension_loaded('session')) {
            http_response_code(503);
            echo 'Enable the PHP session extension, then reload the installer.';
            return;
        }
        session_name('AWTInstaller');
        session_start(['use_strict_mode' => 1, 'cookie_httponly' => 1, 'cookie_samesite' => 'Strict', 'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
        $_SESSION['awt_install_token'] ??= bin2hex(random_bytes(32));
        $_SESSION['awt_install_csrf'] ??= bin2hex(random_bytes(32));

        $lock = @fopen(CONFIG . '.awt-install.lock.php', 'c+');
        if ($lock === false || !flock($lock, LOCK_EX)) {
            http_response_code(503);
            echo 'Make the configuration directory writable, then reload the installer.';
            return;
        }
        try {
            if (filesize(CONFIG . '.awt-install.lock.php') === 0) fwrite($lock, '<?php exit;');
            chmod(CONFIG . '.awt-install.lock.php', 0600);
            // A competing request may have completed while this one waited for the lock.
            if (file_exists(CONFIG . 'awt_db.php')) {
                $this->respond(['error' => 'Installation has already completed. Reload to open your website.'], 409);
                return;
            }
            if (is_file(self::JOURNAL)) {
                $this->state = json_decode(substr(file_get_contents(self::JOURNAL), strlen("<?php exit; ?>\n")), true, 512, JSON_THROW_ON_ERROR);
                if (!hash_equals($this->state['owner'], hash('sha256', $_SESSION['awt_install_token']))) {
                    http_response_code(409);
                    echo 'Installation is in progress in another browser. Continue there to finish or restart setup.';
                    return;
                }
            } else {
                $this->state = $this->initialState();
            }
            // A durable marker survives a worker crash or a timed-out request.
            if (isset($this->state['operation'])) {
                try {
                    $this->rollback();
                    $this->state['notice'] = 'The previous setup stopped before it finished. Its changes have been rolled back. You can start again.';
                } catch (\Throwable $error) {
                    error_log('AWT interrupted setup recovery: ' . $error->getMessage());
                    $this->state['rollback_error'] = true;
                }
                unset($this->state['operation']);
                $this->persist();
            }
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $this->post();
                return;
            }
            if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
                http_response_code(405);
                header('Allow: GET, POST');
                return;
            }
            $initial = $this->publicState();
            $csrf = $_SESSION['awt_install_csrf'];
            require ROOT . '/bootstrap/installer/view.php';
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function initialState(string $profile = 'cms'): array
    {
        return ['owner' => hash('sha256', $_SESSION['awt_install_token']), 'step' => 1, 'profile' => $profile, 'packages' => [], 'packages_done' => false, 'admin_created' => false];
    }

    private function persist(): void
    {
        $temporary = self::JOURNAL . '.tmp.php';
        $contents = "<?php exit; ?>\n" . json_encode($this->state, JSON_THROW_ON_ERROR);
        $handle = fopen($temporary, 'wb');
        if ($handle === false) throw new \RuntimeException('Could not save installation progress.');
        try {
            chmod($temporary, 0600);
            if (fwrite($handle, $contents) !== strlen($contents)) throw new \RuntimeException('Could not save installation progress.');
        } finally {
            fclose($handle);
        }
        if (!rename($temporary, self::JOURNAL)) throw new \RuntimeException('Could not save installation progress.');
    }

    private function profile(): IProfile
    {
        return $this->state['profile'] === 'cms' ? new CmsProfile() : new FrameworkProfile();
    }

    private function post(): void
    {
        try {
            $input = json_decode(file_get_contents('php://input'), true, 32, JSON_THROW_ON_ERROR);
            if (!is_array($input) || !is_string($input['csrf'] ?? null) || !hash_equals($_SESSION['awt_install_csrf'], $input['csrf'])) {
                $this->respond(['error' => 'Your session could not be verified. Reload the page and try again.'], 403);
                return;
            }
            if ((int) ($input['step'] ?? 0) !== $this->state['step']) {
                $this->respond(['error' => 'This page is out of date. Reload to continue.'], 409);
                return;
            }
            if (isset($this->state['rollback_error']) && ($input['action'] ?? '') !== 'restart') {
                throw new \InvalidArgumentException('Rollback needs attention. Choose Start again to retry recovery.');
            }
            if (isset($this->state['configuration'])) $this->pdo = $this->installer->connectDatabase($this->state['configuration']);
            switch ($input['action'] ?? '') {
                case 'back':
                    if ($this->state['step'] <= 3) {
                        $step = max(1, $this->state['step'] - 1);
                        $this->rollback();
                        $this->state['step'] = $step;
                    } else {
                        $this->state['step']--;
                    }
                    break;
                case 'restart':
                    $this->rollback();
                    break;
                case 'packages':
                    if ($this->state['step'] !== 5) throw new \InvalidArgumentException('Complete the previous steps first.');
                    $this->installPackages($input);
                    break;
                case 'next':
                    $this->next($input);
                    break;
                default:
                    throw new \InvalidArgumentException('Choose a valid installation action.');
            }
            unset($this->state['operation']);
            if ($this->state['step'] !== 6) $this->persist();
            $this->respond($this->publicState());
        } catch (\Throwable $error) {
            if (!$this->mutating && ($error instanceof \InvalidArgumentException || $error instanceof \JsonException)) {
                $this->respond(['error' => $error->getMessage()], 422);
                return;
            }
            error_log('AWT installer: ' . $error->getMessage());
            try {
                $this->rollback();
                $this->persist();
                $message = $error->getMessage() . ' Setup changes have been rolled back. You can try again.';
            } catch (\Throwable $rollbackError) {
                error_log('AWT installer rollback: ' . $rollbackError->getMessage());
                $this->state['rollback_error'] = true;
                $this->persist();
                $message = 'Installation stopped. Recovery could not finish. Check server permissions and database access, then choose Start again to retry recovery.';
            }
            $this->respond([...$this->publicState(), 'error' => $message], 500);
        }
    }

    private function next(array $input): void
    {
        switch ($this->state['step']) {
            case 1:
                $profile = $this->text($input, 'profile');
                if (!in_array($profile, ['cms', 'framework'], true)) throw new \InvalidArgumentException('Choose an installation profile.');
                InstallationRequirements::assertSatisfied();
                $profilePackages = $profile === 'cms' ? (new CmsProfile())->packages() : [];
                foreach ([CONFIG, TEMP, ROOT, PACKAGES, PUBLIC_STORAGE, CACHE] as $path) {
                    if (!is_writable($path)) throw new \InvalidArgumentException('Make this directory writable before continuing: ' . $path);
                }
                $this->state['profile'] = $profile;
                $this->state['profile_packages'] = $profilePackages;
                $this->state['step'] = 2;
                break;
            case 2:
                $configuration = $this->installer->validateConfiguration([
                    'database_type' => $this->text($input, 'database_type'),
                    'database_name' => $this->text($input, 'database_name'),
                    'host' => $this->text($input, 'host'),
                    'username' => $this->text($input, 'username'),
                    'password' => $this->text($input, 'password', false),
                    'website_name' => 'AWT', 'host_path' => '/',
                ]);
                InstallationRequirements::assertSatisfied($configuration['database_type']);
                // Never run migrations or rollback against someone else's existing tables.
                $sqliteExisted = file_exists(ROOT . '/awt_db.sqlite');
                $this->pdo = $this->installer->connectDatabase($configuration);
                if (InstallationRollback::tables($this->pdo) !== []) throw new \InvalidArgumentException('Choose an empty database. Existing data will not be changed.');
                $this->state['configuration'] = $configuration;
                $this->state['database_armed'] = true;
                $this->state['sqlite_created'] = $configuration['database_type'] === 'sqlite' && !$sqliteExisted;
                $this->state['snapshot_directory'] = TEMP . 'web-install-' . bin2hex(random_bytes(12));
                $this->beginOperation('snapshot');
                $this->state['snapshot'] = InstallationRollback::snapshot($this->state['snapshot_directory']);
                $this->state['snapshot']['sqlite_existed'] = $sqliteExisted;
                $this->beginOperation('migrations');
                $this->installer->prepareDatabase($configuration);
                $this->state['step'] = 3;
                break;
            case 3:
                $values = $input['settings'] ?? [];
                if (!is_array($values)) throw new \InvalidArgumentException('Enter your website settings.');
                $name = $this->text($values, 'Website Name');
                if ($name === '') throw new \InvalidArgumentException('Enter a website name.');
                foreach ($this->settings() as $setting) {
                    $value = $this->text($values, $setting['name'], false);
                    if (strlen($value) > 255) throw new \InvalidArgumentException($setting['name'] . ' must be 255 characters or fewer.');
                    if ($setting['value_type'] === 'boolean' && !in_array($value, ['true', 'false'], true)) throw new \InvalidArgumentException('Choose a value for ' . $setting['name'] . '.');
                    if ($setting['value_type'] === 'number' && $value !== '' && (!ctype_digit($value) || (int) $value < 1)) throw new \InvalidArgumentException('Enter a positive number for ' . $setting['name'] . '.');
                    if ($setting['name'] === 'Contact Email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) throw new \InvalidArgumentException('Enter a valid contact email.');
                    if ($setting['name'] === 'Hostname Path' && $value !== '' && (!str_starts_with($value, '/') || str_starts_with($value, '//') || preg_match('/[?#\\\\\x00-\x20]/', $value))) throw new \InvalidArgumentException('Use a website path such as / or /my-site/.');
                }
                $this->beginOperation('settings');
                foreach ($this->settings() as $setting) {
                    (new \database\DatabaseManager())->table('awt_setting')->where(['id' => $setting['id'], 'package_id' => 1])->update(['value' => $this->text($values, $setting['name'], false)]);
                }
                $this->state['configuration']['website_name'] = $name;
                $this->state['configuration']['host_path'] = $this->text($values, 'Hostname Path') ?: '/';
                $this->state['step'] = 4;
                break;
            case 4:
                if (($input['skip_admin'] ?? false) === true) {
                    if ($this->profile()->requiresAdmin()) throw new \InvalidArgumentException('AWT CMS needs an administrator account.');
                    $this->beginOperation('administrator');
                    $this->pdo->exec('DELETE FROM awt_admin');
                    $this->state['admin_created'] = false;
                } else {
                    $admin = [];
                    foreach (['email', 'username', 'firstname', 'lastname', 'password'] as $key) $admin[$key] = $this->text($input, $key, $key !== 'password');
                    if (!filter_var($admin['email'], FILTER_VALIDATE_EMAIL) || $admin['username'] === '') throw new \InvalidArgumentException('Enter a valid email and a username.');
                    if (strlen($admin['password']) < 12 || strlen($admin['password']) > 72) throw new \InvalidArgumentException('Choose a password between 12 and 72 bytes.');
                    if ($admin['password'] !== $this->text($input, 'password_confirmation', false)) throw new \InvalidArgumentException('Your passwords do not match.');
                    foreach (['email', 'username', 'firstname', 'lastname'] as $key) if (strlen($admin[$key]) > 255) throw new \InvalidArgumentException('Account fields must be 255 characters or fewer.');
                    $this->beginOperation('administrator');
                    $this->pdo->beginTransaction();
                    try {
                        $this->pdo->exec('DELETE FROM awt_admin');
                        $this->installer->createAdmin($admin);
                        $this->pdo->commit();
                    } catch (\Throwable $error) {
                        if ($this->pdo->inTransaction()) $this->pdo->rollBack();
                        throw $error;
                    }
                    $this->state['admin_created'] = true;
                }
                $this->state['step'] = 5;
                break;
            case 5:
                if (!$this->state['packages_done']) throw new \InvalidArgumentException('Finish the package step before continuing.');
                if ($this->profile()->requiresAdmin() && !$this->state['admin_created']) throw new \InvalidArgumentException('Create an administrator before continuing.');
                $this->beginOperation('completion');
                $this->installer->completeInstallation($this->state['configuration']);
                // Completion is now durable. Cleanup failures must not roll back a live installation.
                $this->state['step'] = 6;
                try {
                    if (isset($this->state['snapshot'])) InstallationRollback::remove($this->state['snapshot']['directory']);
                    if (is_file(self::JOURNAL)) unlink(self::JOURNAL);
                } catch (\Throwable $error) { error_log('AWT installer cleanup: ' . $error->getMessage()); }
                unset($_SESSION['awt_install_token'], $_SESSION['awt_install_csrf']);
                break;
        }
    }

    private function settings(): array
    {
        if ($this->pdo === null || $this->state['step'] < 3 || ($this->state['rollback_error'] ?? false)) return [];
        return $this->pdo->query('SELECT id, name, value, value_type, category FROM awt_setting WHERE package_id = 1 ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    }

    private function installPackages(array $input): void
    {
        if ($this->state['packages_done']) return;
        $urls = $this->state['profile_packages'] ?? $this->profile()->packages();
        if (!$this->profile()->requiresAdmin()) {
            $url = $this->text($input, 'package_url');
            $urls = $url === '' ? [] : [$url];
        }
        foreach ($urls as $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL) || !in_array(strtolower(parse_url($url, PHP_URL_SCHEME) ?? ''), ['http', 'https'], true)) throw new \InvalidArgumentException('Enter an HTTP or HTTPS package ZIP URL.');
        }
        $this->beginOperation('packages');
        if (!defined('HOSTNAME')) define('HOSTNAME', '/');
        if (!defined('WEB_NAME')) define('WEB_NAME', $this->state['configuration']['website_name']);
        $GLOBALS['settings'] = (new \setting\Settings())->fetchSettings()->initSettings();
        $GLOBALS['shared']['AWT']['Settings'] = $GLOBALS['settings'];
        $GLOBALS['eventDispatcher'] = new \event\EventDispatcher();
        foreach ($urls as $url) {
            $archive = tempnam(TEMP, 'web-package-');
            if ($archive === false) throw new \RuntimeException('Could not create a temporary package file.');
            try {
                (new PackageDownload())->download($url, $archive);
                $facade = new PackageFacade();
                $installer = new PackageInstaller(
                    new Extractor(new \ZipArchive(), new TransientStorageEntry(basename($archive), $archive), TEMP . 'web-extract-'),
                    new PackageMover('', PACKAGES),
                    new PackageStorageTreeGenerator(new LocalFileSystemService(), new StorageRepository()),
                    $facade->getRepository(),
                );
                if (!$installer->install(true)) throw new \RuntimeException('Package installation could not complete.');
            } finally { if (is_file($archive)) unlink($archive); }
        }
        $facade = new PackageFacade();
        foreach ($facade->getInstalled() as $package) {
            $facade->enablePackage($package->getId());
            if ($package->getId() !== 1) $this->state['packages'][] = $package->getName();
        }
        $this->state['packages_done'] = true;
    }

    private function rollback(): void
    {
        if (($this->state['database_armed'] ?? false) && $this->pdo === null) $this->pdo = $this->installer->connectDatabase($this->state['configuration']);
        InstallationRollback::restore($this->state, $this->pdo);
        $this->pdo = null;
        unset($GLOBALS['shared']['DBEngine']['PDO']);
        if (isset($this->state['snapshot'])) InstallationRollback::remove($this->state['snapshot']['directory']);
        elseif (isset($this->state['snapshot_directory'])) InstallationRollback::remove($this->state['snapshot_directory']);
        $this->state = $this->initialState($this->state['profile']);
    }

    private function beginOperation(string $operation): void
    {
        $this->mutating = true;
        $this->state['operation'] = $operation;
        $this->persist();
    }

    private function publicState(): array
    {
        if ($this->pdo === null && isset($this->state['configuration']) && $this->state['step'] < 6 && !($this->state['rollback_error'] ?? false)) {
            try { $this->pdo = $this->installer->connectDatabase($this->state['configuration']); }
            catch (\Throwable $error) {
                error_log('AWT installer reconnect: ' . $error->getMessage());
                $this->state['rollback_error'] = true;
                $this->persist();
            }
        }
        return [
            'step' => $this->state['step'], 'profile' => $this->state['profile'],
            'requirements' => InstallationRequirements::checks(),
            'drivers' => extension_loaded('PDO') ? PDO::getAvailableDrivers() : [],
            'profiles' => array_map(static fn(IProfile $profile): array => ['id' => $profile->id(), 'name' => $profile->name(), 'description' => $profile->description(), 'icon' => $profile->icon()], [new CmsProfile(), new FrameworkProfile()]),
            'settings' => $this->state['step'] < 6 ? $this->settings() : [],
            'admin_created' => $this->state['admin_created'], 'packages_done' => $this->state['packages_done'],
            'packages' => $this->state['packages'], 'rollback_error' => $this->state['rollback_error'] ?? false,
            'notice' => $this->state['notice'] ?? '',
        ];
    }

    private function text(array $input, string $key, bool $trim = true): string
    {
        $value = $input[$key] ?? '';
        if (!is_string($value)) throw new \InvalidArgumentException('Enter a text value for ' . $key . '.');
        return $trim ? trim($value) : $value;
    }

    private function respond(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_THROW_ON_ERROR | JSON_HEX_TAG | JSON_HEX_AMP);
    }
}
