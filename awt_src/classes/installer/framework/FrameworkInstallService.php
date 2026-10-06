<?php

namespace installer\framework;

use admin\model\AdminModel;
use database\creator\ColumnCreator;
use database\creator\TableWizard;
use exception\AWTException;
use package\model\InstalledPackage;
use PDO;
use setting\Config;
use setting\Settings;

/** Framework installation operations shared by CLI and web entry points. */
class FrameworkInstallService
{
    private string $name;
    private string $hostpath;

    public function assertAvailable(): void
    {
        if (DB_TYPE !== '') {
            throw new \RuntimeException('Database is already set. Installation can not be proceeded for security reasons.');
        }
    }

    /**
     * @param array<string, string> $configuration
     * @return array<string, string> Validated configuration with defaults applied.
     */
    public function validateConfiguration(array $configuration): array
    {
        $this->assertAvailable();

        foreach (['database_type', 'database_name', 'host', 'username', 'password', 'website_name', 'host_path'] as $key) {
            if (!isset($configuration[$key]) || !is_string($configuration[$key])) {
                throw new \InvalidArgumentException("Missing or invalid installation field: {$key}.");
            }
        }

        $configuration['database_type'] = strtolower(trim($configuration['database_type']));
        if (!in_array($configuration['database_type'], ['mysql', 'postgresql', 'sqlite'], true)) {
            throw new \InvalidArgumentException('Invalid database type. Valid options are mysql, postgresql, sqlite.');
        }
        if (trim($configuration['database_name']) === '' || trim($configuration['username']) === '' || trim($configuration['website_name']) === '') {
            throw new \InvalidArgumentException('Database name, database username, and website name must not be empty.');
        }

        $configuration['host'] = trim($configuration['host']) === '' ? 'localhost' : trim($configuration['host']);
        $configuration['host_path'] = trim($configuration['host_path']) === '' ? '/' : trim($configuration['host_path']);
        return $configuration;
    }

    /** @param array<string, string> $configuration */
    public function install(array $configuration): void
    {
        $configuration = $this->validateConfiguration($configuration);
        $this->name = $configuration['website_name'];
        $this->hostpath = $configuration['host_path'];

        if (!$this->testDbConfig($configuration['host'], $configuration['database_name'], $configuration['username'], $configuration['password'], $configuration['database_type'])) {
            throw new \RuntimeException('Database configuration is invalid.');
        }
        $this->migrate();

        $this->writeDbConfig($configuration['host'], $configuration['database_name'], $configuration['username'], $configuration['password'], $configuration['database_type']);
    }

    /** @param array{email: string, password: string, username: string, firstname: string, lastname: string} $data */
    public function createAdmin(array $data): void
    {
        $admin = new AdminModel(null, true);
        $admin->setEmail($data['email']);
        $admin->setUsername($data['username']);
        $admin->setFirstname($data['firstname']);
        $admin->setLastname($data['lastname']);
        $admin->setPassword($data['password']);
        $admin->setProfilePicture(null);
        $admin->setPermLevel(0);
        $admin->createToken();
        if ($admin->register() === false) {
            throw new \RuntimeException('Admin account creation failed.');
        }
    }

    private function writeDbConfig($host, $database, $username, $password, $type): void {
        $file = CONFIG . 'awt_db.php';

        // Wrap in double quotes, escaping \ " and $ so special characters can't break the generated file
        $q = fn($v) => '"' . addcslashes((string)$v, "\\\"$") . '"';

        $lines = [
            '<?php',
            'const DB_NAME = '     . $q($database) . ';',
            'const DB_HOSTNAME = '     . $q($host === '' ? 'localhost' : $host) . ';',
            'const DB_USERNAME = ' . $q($username) . ';',
            'const DB_PASSWORD = ' . $q($password) . ';',
            'const DB_TYPE = '     . $q($type) . ';',
        ];

        if (file_put_contents($file, implode(PHP_EOL, $lines) . PHP_EOL) === false) {
            throw new \RuntimeException('Failed to write database configuration.');
        }
    }

    private function testDbConfig($host, $database, $username, $password, $type): bool
    {
        global $shared;
        $host = trim($host) === '' ? 'localhost' : trim($host);
        $dsn = match (strtolower(trim($type))) {
            'mysql' => "mysql:host={$host};dbname={$database}",
            'postgresql', 'pgsql' => "pgsql:host={$host};dbname={$database};connect_timeout=5",
            'sqlite' => "sqlite:{$database}",
            default => null,
        };

        if ($dsn === null) {
            return false;
        }

        try {
            // PDO connects during construction and throws if the connection fails.
            $pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_PERSISTENT => true,
                PDO::ATTR_TIMEOUT => 5,
            ]);
            $shared['DBEngine']['PDO'] = $pdo;
            return true;
        } catch (\PDOException) {
            return false;
        }
    }


    private function migrate(): void {
        $this->migratePackageTable();
        $this->migrateTableInfo();
        $this->migrateTableStructure();
        $this->migrateStorageTable();
        $this->migrateSettings();
        $this->migrateAdmin();
    }

    private function migratePackageTable(): void {
        $id = ColumnCreator::INT("id", 255)->autoIncrement()->primary()->unique();
        $name = ColumnCreator::VARCHAR("name", 255)->index();
        $description = ColumnCreator::TEXT("description")->nullable();
        $icon = ColumnCreator::TEXT("icon")->nullable();
        $preview_image = ColumnCreator::TEXT("preview_image")->nullable();
        $license = ColumnCreator::VARCHAR("license", 255)->nullable();
        $license_url = ColumnCreator::VARCHAR("license_url", 255)->nullable();
        $author = ColumnCreator::VARCHAR("author", 128);
        $version = ColumnCreator::VARCHAR("version", 128);
        $minimum_awt_version = ColumnCreator::VARCHAR("minimum_awt_version", 128);
        $maximum_awt_version = ColumnCreator::VARCHAR("maximum_awt_version", 128)->nullable();
        $dependencies = ColumnCreator::TEXT("dependencies")->nullable();
        $type = ColumnCreator::TINYINT("type", 1)->default(1);
        $system_package = ColumnCreator::TINYINT("system_package", 1)->default(0);
        $status = ColumnCreator::TINYINT("status", 1)->default(0);
        $installation_date = ColumnCreator::DATETIME("installation_date")->default("CURRENT_TIMESTAMP");


        $wizard = new TableWizard();
        $wizard->headless();
        $wizard->addColumn($id);
        $wizard->addColumn($name);
        $wizard->addColumn($description);
        $wizard->addColumn($icon);
        $wizard->addColumn($preview_image);
        $wizard->addColumn($license);
        $wizard->addColumn($license_url);
        $wizard->addColumn($author);
        $wizard->addColumn($version);
        $wizard->addColumn($minimum_awt_version);
        $wizard->addColumn($maximum_awt_version);
        $wizard->addColumn($dependencies);
        $wizard->addColumn($type);
        $wizard->addColumn($system_package);
        $wizard->addColumn($status);
        $wizard->addColumn($installation_date);
        $res = $wizard->createTable("awt_package");
        if(!$res)
            throw new AWTException(new \Exception("Failed to create table awt_package"));

        $package = new InstalledPackage();
        $package->setId(1);
        $package->setName("AWT");
        $package->setVersion("27.0.0");
        $package->setAuthor("ElStefanos");
        $package->setMinimumAwtVersion(AWT_VERSION);
        $package->setMaximumAwtVersion(AWT_VERSION);
        $package->setDescription("Advanced Web Tools Framework - This package is used to map storage and settings. This is not the ordinary package and should not be removed.");
        $package->setLicense("GNU General Public License v3.0");
        $package->setIcon("https://github-production-user-asset-6210df.s3.amazonaws.com/46761434/562472824-0aff0cb0-4993-446b-b66e-f6d18350cd45.png?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Credential=AKIAVCODYLSA53PQK4ZA%2F20261006%2Fus-east-1%2Fs3%2Faws4_request&X-Amz-Date=20261006T130115Z&X-Amz-Expires=300&X-Amz-Signature=0ac601e919a6bd74e81bb8e05fccd12f916d8952a3a542b594bd30da7b35b608&X-Amz-SignedHeaders=host&response-content-type=image%2Fpng");
        $package->setLicenseUrl("https://github.com/Advanced-Web-Tools/Advanced-Web-Tools-Framework/blob/main/LICENSE");
        $package->setType("0");
        $package->setSystem(true);
        $package->saveModel();
    }

    private function migrateStorageTable(): void {
        $id = ColumnCreator::INT("id")->autoIncrement()->primary()->unique();
        $name = ColumnCreator::VARCHAR("name", 255)->index();
        $path = ColumnCreator::TEXT("path");
        $url = ColumnCreator::TEXT("url")->nullable();
        $size = ColumnCreator::INT("size")->default(0);
        $lastModified = ColumnCreator::INT("lastModified")->default(0);
        $middleware = ColumnCreator::VARCHAR("middleware", 255)->nullable();
        $ownerId = ColumnCreator::INT("ownerId")->nullable()->foreignKey("awt_package", "id", "SET NULL", "CASCADE")->index();
        $ownerType = ColumnCreator::VARCHAR("ownerType", 255)->nullable();

        $wizard = new TableWizard(1);
        $wizard->addColumn($id);
        $wizard->addColumn($name);
        $wizard->addColumn($path);
        $wizard->addColumn($url);
        $wizard->addColumn($size);
        $wizard->addColumn($lastModified);
        $wizard->addColumn($middleware);
        $wizard->addColumn($ownerId);
        $wizard->addColumn($ownerType);
        $wizard->createTable("awt_storage");
    }

    private function migrateTableInfo(): void {
        $id = ColumnCreator::INT("id", 255)->autoIncrement()->primary()->unique();
        $name = ColumnCreator::VARCHAR("name", 255)->index();
        $creation_date = ColumnCreator::DATETIME("creation_date")->default("CURRENT_TIMESTAMP");
        $creator = ColumnCreator::INT("creator", 255)->nullable()->foreignKey("awt_package", "id", "CASCADE", "CASCADE")->index();
        TableWizard::create(0)->headless()->addColumn($id)->addColumn($name)->addColumn($creation_date)->addColumn($creator)->createTable("awt_table");
    }

    private function migrateTableStructure(): void {
        $id = ColumnCreator::INT("id", 255)->autoIncrement()->primary()->unique();
        $table_id = ColumnCreator::INT("table_id", 255)->foreignKey("awt_table", "id", "CASCADE", "CASCADE")->index();
        $column_name = ColumnCreator::VARCHAR("column_name", 255);
        $column_type = ColumnCreator::VARCHAR("column_type", 255);
        TableWizard::create(0)->headless()->addColumn($id)->addColumn($table_id)->addColumn($column_name)->addColumn($column_type)->createTable("awt_table_structure");
    }

    private function migrateSettings(): void {
        $id = ColumnCreator::INT("id", 255)->autoIncrement()->primary()->unique();
        $package_id = ColumnCreator::INT("package_id", 255)->nullable()->foreignKey("awt_package", "id", "CASCADE", "CASCADE")->index();
        $name = ColumnCreator::VARCHAR("name", 255)->index();
        $value_type = ColumnCreator::VARCHAR("value_type", 16)->default("text");
        $value = ColumnCreator::VARCHAR("value", 255);
        $required_permission_level = ColumnCreator::INT("required_permission_level", 255)->default(0);
        $category = ColumnCreator::VARCHAR("category", 255)->default("Miscellaneous");
        TableWizard::create(1)->addColumn($id)->addColumn($package_id)->addColumn($name)->addColumn($value_type)->addColumn($value)->addColumn($required_permission_level)->addColumn($category)->createTable("awt_setting");

        $s = new Settings();
        $s->createSetting(1, "Website Name", $this->name, 0, "text", "General");
        $s->createSetting(1, "Hostname Path", $this->hostpath, 0, "text", "General");
        $s->createSetting(1, "Session HTTPS Only", 'true', 0, "boolean", "Security");
        $s->createSetting(1, "Session HTTP Only", 'true', 0, "boolean", "Security");
        $s->createSetting(1, "Session ID Regeneration Time", 900, 0, "number", "Security");
        $s->createSetting(1, "Session SameSite", 'true', 0, "boolean", "Security");
        $s->createSetting(1, "Contact Name", '', 0, "text", "Contact");
        $s->createSetting(1, "Contact Email", '', 0, "text", "Contact");
        $s->createSetting(1, "Phone Number", '', 0, "text", "Contact");
        $s->createSetting(1, "Address", '', 0, "text", "Contact");
        $s->fetchSettings()->initSettings();

        if (defined("SETT_AWT_WEBSITE_NAME")) {
            $val = Config::getConfig("AWT", "website name")->getValue();
            define("WEB_NAME", $val);
        } else {
            define("WEB_NAME", "AWT");
        }

    }

    private function migrateAdmin(): void {
        $id = ColumnCreator::INT("id", 255)->autoIncrement()->primary()->unique();
        $username = ColumnCreator::VARCHAR("username", 255)->unique()->index();
        $password = ColumnCreator::VARCHAR("password", 255);
        $email = ColumnCreator::VARCHAR("email", 255)->unique()->index();
        $profile_picture = ColumnCreator::VARCHAR("profile_picture", 255)->nullable();
        $firstname = ColumnCreator::VARCHAR("firstname", 255);
        $lastname = ColumnCreator::VARCHAR("lastname", 255);
        $permission_level = ColumnCreator::INT("permission_level", 255)->default(0);
        $last_logged_ip = ColumnCreator::VARCHAR("last_logged_ip", 255)->nullable();
        $token = ColumnCreator::VARCHAR("token", 255);
        TableWizard::create(1)->addColumn($id)->addColumn($username)->addColumn($password)->addColumn($email)->addColumn($firstname)->addColumn($lastname)->addColumn($permission_level)->addColumn($token)->addColumn($profile_picture)->addColumn($last_logged_ip)->createTable("awt_admin");
    }

}
