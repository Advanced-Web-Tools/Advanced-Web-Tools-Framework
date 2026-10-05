<?php
/** Run with php tests/runtime_refactor.php. Uses temporary files and SQLite only. */
$root = dirname(__DIR__) . '/';
$temp = sys_get_temp_dir() . '/awt-refactor-test-' . bin2hex(random_bytes(6)) . '/';
mkdir($temp, 0755, true);
define('CLASSES', $root . 'awt_src/classes/');
define('PACKAGES', $temp . 'packages/');
define('DATA', $temp . 'data/');
define('CONFIG', $root . 'awt_data/config/');
define('COMPILED', $temp . 'compiled/');
define('PACKAGE_STORAGE', $temp . 'public/packages/');
define('HOSTNAME', 'http://localhost');
define('AWT_VERSION', '27.0.0');
define('DEBUG', true);
libxml_use_internal_errors(true);
error_reporting(E_ALL & ~E_DEPRECATED);
mkdir(PACKAGES, 0755, true);
mkdir(COMPILED, 0755, true);
require $root . 'awt_src/functions/awt_autoLoader.fun.php';

use database\DatabaseManager;
use database\interface\IProvider;
use event\EventDispatcher;
use package\model\InstalledPackage;
use runtime\Runtime;
use runtime\RuntimeExecutor;
use runtime\RuntimeOrchestrator;

$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function fails(callable $call, string $message): void {
    try { $call(); } catch (Throwable $e) {
        check(str_contains($e->getMessage(), $message), "Expected {$message}, got {$e->getMessage()}");
        return;
    }
    throw new RuntimeException("Expected failure: {$message}");
}
function package(string $name, array $dependencies = [], bool $active = true): InstalledPackage {
    static $id = 0;
    $p = new InstalledPackage();
    $p->fromArray(['id' => ++$id, 'name' => $name, 'version' => '1.0.0',
        'minimum_awt_version' => '27.0.0', 'type' => 1, 'status' => $active,
        'installation_date' => '2026-10-05 00:00:00', 'dependencies' => $dependencies]);
    $p->createDependencyCollection();
    return $p;
}
function runtime(InstalledPackage $package): Runtime {
    $runtime = Runtime::create($package);
    $runtime->setEventDispatcher(new EventDispatcher());
    return $runtime;
}
function fixture(string $name, string $php): void {
    mkdir(PACKAGES . $name, 0755, true);
    file_put_contents(PACKAGES . $name . '/main.php', "<?php\n" . $php);
}
function removeTree(string $path): void {
    if (is_link($path) || is_file($path)) { unlink($path); return; }
    foreach (array_diff(scandir($path), ['.', '..']) as $name) removeTree($path . '/' . $name);
    rmdir($path);
}
register_shutdown_function(fn() => removeTree(rtrim($temp, '/')));

// Real package, unchanged PHP, through the new runtime and legacy adapter.
function copyTree(string $source, string $destination): void {
    mkdir($destination, 0755, true);
    foreach (array_diff(scandir($source), ['.', '..']) as $name) {
        if (is_dir($source . '/' . $name)) copyTree($source . '/' . $name, $destination . '/' . $name);
        else copy($source . '/' . $name, $destination . '/' . $name);
    }
}
// Prefer an explicit fixture; otherwise test the local sample without changing it.
$packageSource = is_dir(__DIR__ . '/fixtures/Branislav10')
    ? __DIR__ . '/fixtures/Branislav10' : $root . 'awt_packages/Branislav10';
if (!is_file($packageSource . '/main.php')) {
    throw new RuntimeException('Provide Branislav10 in awt_packages/ or tests/fixtures/ to run package integration checks.');
}
copyTree($packageSource, PACKAGES . 'Branislav10');
// Exercise both generations using identical package code in a temporary copy.
$main = file_get_contents(PACKAGES . 'Branislav10/main.php');
if (!in_array('--native', $argv, true)) {
    $main = str_replace('use runtime\\api\\RuntimeLinkerAPI;', 'use packages\\runtime\\api\\RuntimeLinkerAPI;', $main);
    $main = str_replace('use runtime\\enums\\ERuntimeFlags;', 'use packages\\runtime\\handler\\enums\\ERuntimeFlags;', $main);
} else {
    $main = str_replace('use packages\\runtime\\api\\RuntimeLinkerAPI;', 'use runtime\\api\\RuntimeLinkerAPI;', $main);
    $main = str_replace('use packages\\runtime\\handler\\enums\\ERuntimeFlags;', 'use runtime\\enums\\ERuntimeFlags;', $main);
}
file_put_contents(PACKAGES . 'Branislav10/main.php', $main);
$executor = new RuntimeExecutor();
$branislav = runtime(package('Branislav10'));
(new RuntimeOrchestrator($executor, [$branislav]))->run();
check(count($executor->routers) === 1, 'Legacy router provider must execute exactly once.');
$routes = $executor->routers[0]->getRouters();
check(count($routes) === 9, 'All nine real package routes must be retained.');
check(count($executor->passableInstances['Branislav10']) === 3, 'Linked runtimes remain passable.');
$_SERVER['REQUEST_URI'] = '/products/reed_knife';
$manager = new router\manager\RouterManager();
$manager->eventDispatcher = $branislav->getEventDispatcher();
$manager->loadRouters($routes);
$html = $manager->startRouter()->render();
check(str_contains($html, 'Branislav10 | Reed Knife'), 'Real product title must render.');
check(str_contains($html, 'http://localhost/awt_packages/Branislav10/views/assets/css/reed-knife.css'), 'Asset directive must retain its URL.');
// Loading a declared package class a second time must still work.
$second = new RuntimeExecutor();
(new RuntimeOrchestrator($second, [runtime(package('Branislav10'))]))->run();
check(count($second->routers[0]->getRouters()) === 9, 'Repeated class loading must work.');
foreach ($routes as $route) {
    $view = $route->route();
    check(strlen($view->render()) > 0, 'Every registered real page must render: ' . $route->path);
}

// Native API and legacy API share objects across packages; declared dependencies
// are scheduled first even when consumers occur first in the database results.
fixture('Provider', <<<'CODE'
final class TestProvider extends \runtime\api\RuntimeAPI {
    public function environmentSetup(): void { $GLOBALS['order'][] = 'provider.env'; $this->setRuntimeFlag(\runtime\enums\ERuntimeFlags::CreatePassable); }
    public function setup(): void { $GLOBALS['order'][] = 'provider.setup'; }
    public function main(): void { $GLOBALS['order'][] = 'provider.main'; $this->setShared('service', (object)['value' => 42]); }
}
CODE);
fixture('Consumer', <<<'CODE'
final class TestConsumer extends \packages\runtime\api\RuntimeAPI {
    public function environmentSetup(): void { $GLOBALS['order'][] = 'consumer.env'; }
    public function setup(): void {
        $GLOBALS['order'][] = 'consumer.setup';
        if ($this->getShared('Provider', 'service')->value !== 42) throw new \RuntimeException('Missing shared service');
        if (!$this->getPassable('Provider', 'TestProvider')) throw new \RuntimeException('Missing passable instance');
    }
    public function main(): void { $GLOBALS['order'][] = 'consumer.main'; }
}
CODE);
$GLOBALS['order'] = [];
$consumer = package('Consumer', [['name' => 'Provider', 'version' => '>=1.0.0 <2.0.0']]);
$provider = package('Provider');
$executor = new RuntimeExecutor();
$loaded = (new RuntimeOrchestrator($executor, [runtime($consumer), runtime($provider)]))->run();
check(array_keys($loaded) === ['Provider', 'Consumer'], 'Manifest dependencies must control execution order.');
check($GLOBALS['order'] === ['provider.env', 'provider.setup', 'provider.main', 'consumer.env', 'consumer.setup', 'consumer.main'], 'Lifecycle order must be preserved.');
check(count($executor->routers) === 0, 'Flags must not leak between components or packages.');

// Missing, disabled, mismatched, and cyclic declarations fail before any package lifecycle.
$GLOBALS['order'] = [];
fails(fn() => (new RuntimeOrchestrator(new RuntimeExecutor(), [runtime($consumer)]))->run(), 'missing package Provider');
check($GLOBALS['order'] === [], 'Missing manifest dependency must prevent execution.');
fails(fn() => (new RuntimeOrchestrator(new RuntimeExecutor(), [runtime($consumer)], [$consumer, package('Provider', [], false)]))->run(), 'disabled package Provider');
$provider->version = '2.0.0';
fails(fn() => (new RuntimeOrchestrator(new RuntimeExecutor(), [runtime($consumer), runtime($provider)]))->run(), 'installed 2.0.0');
$provider->version = '1.0.0';
$provider->dependencies = [['name' => 'Consumer', 'version' => '*']];
fails(fn() => (new RuntimeOrchestrator(new RuntimeExecutor(), [runtime($consumer), runtime($provider)]))->run(), 'Circular manifest dependency');
$provider->dependencies = [];
check($GLOBALS['order'] === [], 'Manifest preflight must not initialize packages.');

// Dynamic waits work without manifest dependencies and preserve each lifecycle once.
fixture('Waiter', <<<'CODE'
final class TestWaiter extends \packages\runtime\api\RuntimeAPI {
    public function environmentSetup(): void { $GLOBALS['order'][] = 'waiter.env'; $this->waitForRuntime('Provider'); }
    public function setup(): void { $GLOBALS['order'][] = 'waiter.setup'; if (!$this->getShared('Provider', 'service')) throw new \RuntimeException('Wait failed'); }
    public function main(): void { $GLOBALS['order'][] = 'waiter.main'; }
}
CODE);
$GLOBALS['order'] = [];
(new RuntimeOrchestrator(new RuntimeExecutor(), [runtime(package('Waiter')), runtime($provider)]))->run();
check($GLOBALS['order'] === ['waiter.env', 'provider.env', 'provider.setup', 'provider.main', 'waiter.setup', 'waiter.main'], 'Runtime wait must execute dependency before setup.');
fails(fn() => (new RuntimeOrchestrator(new RuntimeExecutor(), [runtime(package('Waiter'))]))->run(), 'Cannot wait for missing');
fixture('CycleA', <<<'CODE'
final class TestCycleA extends \runtime\api\RuntimeAPI {
    public function environmentSetup(): void { $this->waitForPackage('CycleB'); }
    public function main(): void { throw new \RuntimeException('Should not execute'); }
}
CODE);
fixture('CycleB', <<<'CODE'
final class TestCycleB extends \runtime\api\RuntimeAPI {
    public function environmentSetup(): void { $this->waitForPackage('CycleA'); }
    public function main(): void { throw new \RuntimeException('Should not execute'); }
}
CODE);
fails(fn() => (new RuntimeOrchestrator(new RuntimeExecutor(), [runtime(package('CycleA')), runtime(package('CycleB'))]))->run(), 'Circular runtime wait');

// Setup waits are scheduled without restarting lifecycle hooks.
fixture('SetupWaiter', <<<'CODE'
final class TestSetupWaiter extends \runtime\api\RuntimeAPI {
    public function setup(): void { $GLOBALS['order'][] = 'setupwait.setup'; $this->waitForPackage('Provider'); }
    public function main(): void { $GLOBALS['order'][] = 'setupwait.main'; if (!$this->getShared('Provider', 'service')) throw new \RuntimeException('Missing dependency'); }
}
CODE);
$GLOBALS['order'] = [];
(new RuntimeOrchestrator(new RuntimeExecutor(), [runtime(package('SetupWaiter')), runtime($provider)]))->run();
check($GLOBALS['order'] === ['setupwait.setup', 'provider.env', 'provider.setup', 'provider.main', 'setupwait.main'], 'Setup waits must not rerun setup.');

fixture('CommandPackage', <<<'CODE'
final class TestCommandRuntime extends \packages\runtime\api\RuntimeAPI {
    public function environmentSetup(): void {
        $this->setRuntimeFlag(\packages\runtime\handler\enums\ERuntimeFlags::CommandProvider);
        $this->addCommand(new TestPackageCommand());
    }
    public function setup(): void {}
    public function main(): void {}
}
final class TestPackageCommand implements \cli\interfaces\CLICommand {
    public function getCommand(): string { return 'package-test'; }
    public function getHelp(): string { return 'Test'; }
    public function getArguments(): array { return []; }
    public function execute(string $command, array $args = []): void {}
    public function result(): string { return 'package command works'; }
}
CODE);
$commandExecutor = new RuntimeExecutor();
$commandExecutor->CLIHandler = new cli\CLIHandler();
(new RuntimeOrchestrator($commandExecutor, [runtime(package('CommandPackage'))]))->run();
check(in_array('package-test', $commandExecutor->CLIHandler->getCommands(), true), 'Package commands must be registered during environment setup.');
ob_start();
$commandExecutor->CLIHandler->execute('package-test');
check(trim(ob_get_clean()) === 'package command works', 'Package-provided CLI command must execute.');

// Optional linker, with native controllers and routers.
fixture('Native', <<<'CODE'
final class TestNative extends \runtime\api\RuntimeLinkerAPI {
    public function main(): void { $this->createLink('controller', '/controller.php'); $this->createLink('router', '/router.php'); }
}
CODE);
file_put_contents(PACKAGES . 'Native/controller.php', <<<'CODE'
<?php
final class TestNativeControllers extends \runtime\api\RuntimeControllerAPI {
    public function main(): void {
        $factory = new \object\ObjectFactory();
        $factory->setClassName(TestNativeController::class)->setType(\controller\Controller::class);
        $factory->setProperties(['controllerName' => 'native']);
        $this->addController($factory, 'native');
    }
}
final class TestNativeController extends \controller\Controller {
    public function index(array|string $params): \response\Response { return \response\Response::make()->data(['ok' => true]); }
}
CODE);
file_put_contents(PACKAGES . 'Native/router.php', <<<'CODE'
<?php
final class TestNativeRoutes extends \runtime\api\RuntimeRouterAPI {
    public function main(): void {
        $controller = $this->getPassable(TestNativeControllers::class)->getController('native');
        $this->addRouter(new \router\Router('/native', 'index', $controller));
    }
}
CODE);
$executor = new RuntimeExecutor();
(new RuntimeOrchestrator($executor, [runtime(package('Native'))]))->run();
check(count($executor->routers) === 1, 'Native linker/controller/router APIs must work.');
check($executor->routers[0]->getRouters()[0]->route() instanceof response\Response, 'Native response routing must work.');

// Homepage dispatch and reusable factories.
class CountingController extends controller\Controller {
    public int $calls = 0;
    public function index(array|string $params): response\Response { $this->calls++; return response\Response::make(); }
}
$controller = new CountingController();
$_SERVER['REQUEST_URI'] = '/';
$manager = new router\manager\RouterManager();
$manager->eventDispatcher = new EventDispatcher();
$other = new router\Router('/other', 'index', $controller);
check($other->match('/') === null, 'Root path must not match every route.');
$manager->addRouter($other);
$manager->addRouter(new router\Router('/', 'index', $controller));
$manager->startRouter();
check($controller->calls === 1, 'Homepage must execute once.');
$factory = (new object\ObjectFactory())->setClassName(CountingController::class);
check($factory->create() instanceof CountingController && $factory->create() instanceof CountingController, 'Factories must be reusable.');

// Database caching tested against actual prepared SQLite queries and the disk cache.
class SQLiteProvider implements IProvider {
    public int $executions = 0;
    public function __construct(public PDO $pdo) {}
    public function execute(string $sql, array $bindings = []): PDOStatement {
        $this->executions++;
        $stmt = $this->pdo->prepare($sql);
        foreach ($bindings as $key => $value) $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        $stmt->execute();
        return $stmt;
    }
    public function lastInsertId(): int { return (int)$this->pdo->lastInsertId(); }
}
$provider = new SQLiteProvider(new PDO('sqlite::memory:'));
$provider->pdo->exec("CREATE TABLE users(id INTEGER PRIMARY KEY, name TEXT); INSERT INTO users VALUES (1,'Alice'),(2,'Bob'); CREATE TABLE tags(user_id INTEGER, title TEXT); INSERT INTO tags VALUES (1,'old');");
$db = new DatabaseManager($provider);
$read = fn(int $id) => $db->table('users')->select()->where(['id' => $id])->get();
check($read(1)[0]['name'] === 'Alice', 'First bound query must return Alice.');
check($read(2)[0]['name'] === 'Bob', 'Different bindings must return Bob.');
$count = $provider->executions;
check($read(1)[0]['name'] === 'Alice' && $provider->executions === $count, 'Equivalent query must hit the cache.');
$first = $db->table('users')->select()->get(1, 0);
$second = $db->table('users')->select()->get(1, 1);
check($first[0]['id'] !== $second[0]['id'], 'LIMIT/OFFSET bindings must affect the cache key.');
$db->table('users')->select()->where(['name' => 'Alice'])->get();
$db->table('users')->where(['id' => 1])->update(['name' => 'Ann']);
check($db->table('users')->select()->where(['name' => 'Alice'])->get() === [], 'Mutation by ID must invalidate cached name predicates.');
check($read(1)[0]['name'] === 'Ann', 'Update must invalidate cached rows.');
$db->table('users')->where(['id' => 2])->delete();
check($read(2) === [], 'Delete must invalidate cached rows.');
$db->table('users')->insert(['id' => 3, 'name' => 'Cara'])->executeInsert();
check($read(3)[0]['name'] === 'Cara', 'Insert must invalidate table cache.');
$join = fn() => $db->table('users')->select(['users.id', 'tags.title'])->join('tags', 'tags.user_id = users.id')->get();
check($join()[0]['title'] === 'old', 'Joined query must work.');
$db->table('tags')->where(['user_id' => 1])->update(['title' => 'new']);
check($join()[0]['title'] === 'new', 'Joined query must not reuse stale primary-table cache.');

// Dependency hydration and validation of installation manifests.
$p = package('Encoded');
$p->dependencies = '[{"name":"Provider","version":"*","url":""}]';
$p->createDependencyCollection();
$p->createDependencyCollection();
check(count($p->getDependenciesCollection()->toArray()) === 1, 'JSON dependencies must hydrate without duplicate collection entries.');
$manifest = ['name' => 'Example', 'version' => '1.0.0', 'minimum_awt_version' => '27.0.0', 'type' => 1, 'dependencies' => [['name' => 'Provider', 'version' => '*']]];
check(package\manifest\reader\ManifestReader::validate($manifest) === $manifest, 'New manifest must validate.');
$manifest['dependencies'][0]['name'] = 'Example';
fails(fn() => package\manifest\reader\ManifestReader::validate($manifest), 'Self or duplicate');
fails(fn() => package\dependency\Dependency::matchesVersion('1.0.0', '^1'), 'Unsupported dependency');

// Actual ZIP installation and repository/service/bootstrap integration.
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE awt_package (
    id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT UNIQUE, author TEXT,
    description TEXT, icon TEXT, preview_image TEXT, license TEXT, license_url TEXT,
    version TEXT, minimum_awt_version TEXT, maximum_awt_version TEXT,
    type INTEGER, system_package INTEGER DEFAULT 0, status INTEGER DEFAULT 0,
    installation_date TEXT, dependencies TEXT, store_id INTEGER, installed_by INTEGER
);");
$pdo->exec("CREATE TABLE awt_storage(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,path TEXT,url TEXT,size INTEGER,middleware TEXT,lastModified INTEGER,ownerId INTEGER,ownerType TEXT);
CREATE TABLE awt_data(id INTEGER PRIMARY KEY AUTOINCREMENT,ownerType TEXT,ownerName TEXT,ownerId INTEGER,dataType TEXT,dataName TEXT);");
$shared = ['DBEngine' => ['PDO' => $pdo]];
$eventDispatcher = new EventDispatcher();
$repo = new package\model\repository\PackageRepository();
$repo->newPackage(['name' => 'Provider', 'version' => '1.0.0', 'type' => 1, 'minimum_awt_version' => '27.0.0']);
$repo->table('awt_package')->where(['name' => 'Provider'])->update(['status' => 1]);
$archive = $temp . 'InstallExample.zip';
$zip = new ZipArchive();
check($zip->open($archive, ZipArchive::CREATE) === true, 'Test ZIP must be created.');
$manifest = ['name' => 'InstallExample', 'version' => '1.0.0', 'type' => 1,
    'minimum_awt_version' => '27.0.0', 'dependencies' => [['name' => 'Provider', 'version' => '>=1.0.0 <2.0.0']]];
$zip->addFromString('InstallExample/data/icon/icon.txt', 'test package data');
$zip->addFromString('InstallExample/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
$zip->addFromString('InstallExample/main.php', <<<'CODE'
<?php
final class TestInstalledRuntime extends \runtime\api\RuntimeAPI {
    public function main(): void { $this->setShared('installed', (object)['ok' => true]); }
}
CODE);
$zip->addFromString('InstallExample/install.php', <<<'CODE'
<?php
final class TestLegacyInstallHook implements \packages\installer\interface\IPackageInstall {
    public function postInstall(int $id, string $name): bool { $GLOBALS['installed_hook'] = [$id, $name]; return true; }
}
CODE);
$zip->close();
$extractor = new installer\package\Extractor(new ZipArchive(), new vfs\transient\TransientStorageEntry('archive', $archive), $temp . 'extract/');
$tree = new installer\package\PackageStorageTreeGenerator(new vfs\storage\services\LocalFileSystemService(), new vfs\storage\StorageRepository());
$installer = new installer\package\PackageInstaller($extractor, new installer\package\PackageMover('', ''), $tree, $repo);
check($installer->install(), 'New installer must accept a directory-wrapped ZIP.');
check(is_file(PACKAGES . 'InstallExample/main.php'), 'Installer must move runtime files.');
$row = $repo->getPackage('InstallExample');
check(json_decode($row['dependencies'], true)[0]['name'] === 'Provider', 'Installer must persist declared dependencies.');
check(file_get_contents(PACKAGE_STORAGE . 'InstallExample/icon/icon.txt') === 'test package data', 'Installer must copy package data into canonical storage.');
check(file_get_contents(DATA . 'media/packages/icon/InstallExample/icon.txt') === 'test package data', 'Legacy @data URL location must remain available.');
$stored = (new vfs\storage\StorageRepository())->fetchByOwner($row['id']);
check(count($stored) === 1 && $stored[0]->getOwnerType() === vfs\storage\enums\EOwnerType::PACKAGE, 'Registered package files must hydrate with their owner type.');
fails(fn() => (new vfs\storage\StorageRepository())->fetchById(99999), 'Storage entry not found');
check($pdo->query('SELECT COUNT(*) FROM awt_data')->fetchColumn() == 1, 'Legacy DataManager metadata must be registered.');
check($GLOBALS['installed_hook'] === [$row['id'], 'InstallExample'], 'Legacy post-install hook signature must be retained.');
$hydrated = (new package\model\service\PackageService($repo))->getPackage('InstallExample');
check($hydrated->getDependencies()[0]['version'] === '>=1.0.0 <2.0.0', 'Installed dependency constraints must survive hydration.');
$legacyManifest = (new packages\ManifestReader(PACKAGES . 'InstallExample'))->readManifest()->createPackage();
check($legacyManifest->dependencies[0]['name'] === 'Provider', 'Legacy manifest reader API must accept the new manifest.');
$repo->table('awt_package')->where(['name' => 'InstallExample'])->update(['status' => 1]);
$realManifest = json_decode(file_get_contents(PACKAGES . 'Branislav10/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
$repo->newPackage($realManifest);
$repo->table('awt_package')->where(['name' => 'Branislav10'])->update(['status' => 1]);
$cliHandler = new cli\CLIHandler();
$bootLoader = require $root . 'bootstrap/packages/loader.php';
check($bootLoader instanceof RuntimeExecutor, 'Bootstrap must use the new executor.');
check(count($bootLoader->routers[0]->getRouters()) === 9, 'Database-backed bootstrap must load the real package.');
check($shared['InstallExample']['installed']->ok, 'Database-backed bootstrap must execute the installed native package.');
check($bootLoader->CLIHandler === $cliHandler, 'CLI handler must be available during package initialization.');

$legacyLoader = new packages\manager\loader\Loader();
$legacyLoader->eventDispatcher = $eventDispatcher;
$legacyLoader->sharedObjects = $shared;
$legacyLoader->CLIHandler = $cliHandler;
$legacyLoader->load();
check(count($legacyLoader->routers[0]->getRouters()) === 9, 'Legacy loader entry point must delegate to the new runtime.');
check(isset($legacyLoader->loaded['Branislav10']), 'Legacy loaded registry must remain available.');

fixture('Standalone', <<<'CODE'
final class TestStandalone extends \packages\runtime\api\RuntimeAPI {
    public function environmentSetup(): void { $GLOBALS['standalone'][] = 'env'; }
    public function setup(): void { $GLOBALS['standalone'][] = 'setup'; }
    public function main(): void { $GLOBALS['standalone'][] = 'main'; }
}
CODE);
$origin = new packages\runtime\Runtime();
$origin->name = 'Standalone';
$origin->setId(30);
$origin->setVersion('1.0.0');
$origin->setMinimumAwtVersion('27.0.0');
$handler = new packages\runtime\handler\RuntimeHandler();
$handler->eventDispatcher = $eventDispatcher;
$standaloneAPI = object\ObjectHandler::createObjectFromFile(PACKAGES . 'Standalone/main.php');
$handler->runtimeCreator($standaloneAPI, $origin);
$handler->execute();
check($GLOBALS['standalone'] === ['env', 'setup', 'main'], 'Legacy runtime handler must preserve its lifecycle through the new executor.');

ob_start();
response\Response::make(201)->data(['ok' => true])->asJson()->send();
$json = json_decode(ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
check($json === ['status' => 201, 'message' => 'OK', 'data' => ['ok' => true]], 'JSON response envelope must remain unchanged.');
echo "Passed {$checks} checks.\n";
