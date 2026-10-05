<?php
/** Run with php tests/vfs.php. All files and the SQLite database are isolated. */
error_reporting(E_ALL);
$root = dirname(__DIR__) . '/';
$temp = sys_get_temp_dir() . '/awt-vfs-' . bin2hex(random_bytes(6)) . '/';
mkdir($temp);
define('CLASSES', $root . 'awt_src/classes/');
define('CONFIG', $root . 'awt_data/config/');
define('DATA', $temp . 'data/');
define('PACKAGES', $temp . 'packages/');
define('HOSTNAME', 'http://localhost');
require $root . 'awt_src/functions/awt_autoLoader.fun.php';
require $root . 'bootstrap/controllers/StorageController.php';
register_shutdown_function(function () use ($temp) {
    $remove = function ($path) use (&$remove) {
        if (is_link($path) || is_file($path)) { unlink($path); return; }
        foreach (array_diff(scandir($path), ['.', '..']) as $name) $remove($path . '/' . $name);
        rmdir($path);
    };
    $remove(rtrim($temp, '/'));
});
$checks = 0;
function check(bool $condition, string $message): void {
    global $checks;
    if (!$condition) throw new RuntimeException($message);
    $checks++;
}
function fails(callable $callback, string $class): void {
    try { $callback(); } catch (Throwable $e) { check($e instanceof $class, 'Unexpected exception: ' . $e); return; }
    throw new RuntimeException('Expected ' . $class);
}
function status(response\Response $response): int {
    return (new ReflectionProperty($response, 'statusCode'))->getValue($response);
}
class CountingProvider extends database\provider\DatabaseProvider {
    public int $queries = 0;
    public function execute(string $sql, array $bindings = []): PDOStatement { $this->queries++; return parent::execute($sql, $bindings); }
}
class FailingRepository extends vfs\storage\StorageRepository {
    public bool $failUpdate = false;
    public bool $throwUpdate = false;
    public bool $failCreate = false;
    public bool $failDelete = false;
    public function update(vfs\storage\StorageEntry $entry): bool {
        if ($this->throwUpdate) throw new RuntimeException('Injected database failure.');
        return !$this->failUpdate && parent::update($entry);
    }
    public function create(vfs\storage\StorageEntry $entry): vfs\storage\StorageEntry {
        if ($this->failCreate) throw new RuntimeException('Injected creation failure.');
        return parent::create($entry);
    }
    public function delete(vfs\storage\StorageEntry $entry): bool { return !$this->failDelete && parent::delete($entry); }
}
class FailingFileSystem extends vfs\storage\services\LocalFileSystemService {
    public function move(string $sourcePath, string $destinationPath): bool { return false; }
}
class TestMiddleware implements middleware\IMiddleware {
    public static int $calls = 0;
    public static bool $allowed = true;
    public function handle() { self::$calls++; return self::$allowed; }
}
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec('CREATE TABLE awt_storage(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT,path TEXT,url TEXT,size INTEGER,middleware TEXT,lastModified INTEGER,ownerId INTEGER,ownerType TEXT);
CREATE TABLE awt_table(id INTEGER PRIMARY KEY,name TEXT); CREATE TABLE awt_table_structure(table_id INTEGER,column_name TEXT);');
$shared = ['DBEngine' => ['PDO' => $pdo]];
$provider = new CountingProvider();
$repo = new FailingRepository(new database\DatabaseManager($provider));
$fs = new vfs\storage\services\LocalFileSystemService();
$strategy = new vfs\storage\strategies\PackageOwnerStorageStrategy($fs, $repo, $temp . 'public');
$manager = new vfs\storage\StorageManager($repo, $fs, [$strategy]);
$entries = [];
foreach (['first content', 'second content'] as $i => $content) {
    $path = $temp . 'upload' . $i . '.txt'; file_put_contents($path, $content);
    $entry = (new vfs\storage\StorageEntry())->setPath($path)->setName('same.txt')->setOwnerType(vfs\storage\enums\EOwnerType::PACKAGE)->setOwnerId(1)->setMiddleware(TestMiddleware::class);
    check($strategy->register($entry, 'Demo'), 'Registration failed.'); $entries[] = $entry;
}
check($entries[0]->path !== $entries[1]->path && file_get_contents($entries[0]->path) === 'first content', 'Same-name uploads overwrite each other.');
$first = $entries[0];
$copy1 = $manager->copy($first->id); $copy2 = $manager->copy($first->id);
check($copy1->path !== $copy2->path && file_get_contents($copy1->path) === 'first content', 'Copies share physical files.');
check($copy1->getMiddleware() === TestMiddleware::class, 'Copy loses middleware.');
check(!$fs->copy($first->path, $copy1->path) && !$fs->move($first->path, $copy1->path), 'Filesystem overwrites destinations.');
$loaded = $repo->fetchById($first->id); $loaded->save(); $loaded->save();
check($repo->fetchById($first->id)->getOwnerType() === vfs\storage\enums\EOwnerType::PACKAGE, 'Save changes ownership.');
$new = (new vfs\storage\StorageEntry())->setName('system')->setPath($first->path)->setOwnerType(vfs\storage\enums\EOwnerType::SYSTEM);
$new->saveModel(); $new->save();
check($repo->fetchById($new->id)->getOwnerType() === vfs\storage\enums\EOwnerType::SYSTEM, 'Repeated persistence changes system ownership.');
$before = $provider->queries; $all = $repo->fetchAll();
check(count($all) === 5 && $provider->queries - $before === 1, 'Collection hydration has N+1 queries: rows=' . count($all) . ', queries=' . ($provider->queries - $before));
$failedManager = new vfs\storage\StorageManager($repo, new FailingFileSystem());
check(!$failedManager->move($first->id, $temp . 'failure') && !$failedManager->rename($first->id, 'failure')
    && $repo->fetchById($first->id)->path === $first->path, 'Failed filesystem move/rename changed metadata.');
$repo->failUpdate = true;
check(!$manager->move($first->id, $temp . 'moved') && is_file($first->path) && $repo->fetchById($first->id)->path === $first->path, 'Failed update leaves file moved.');
$repo->failUpdate = false; $repo->throwUpdate = true;
fails(fn() => $manager->rename($first->id, 'changed.txt'), RuntimeException::class);
check(is_file($first->path), 'Thrown update leaves file renamed.');
$repo->throwUpdate = false;
file_put_contents($temp . 'not-directory', 'block');
check(!$manager->move($first->id, $temp . 'not-directory') && $repo->fetchById($first->id)->path === $first->path, 'Failed filesystem operation changes database.');
$repo->failDelete = true;
check(!$manager->delete($copy2->id) && is_file($copy2->path), 'Failed record deletion loses file.');
$repo->failDelete = false;
check($manager->delete($copy2->id) && !is_file($copy2->path), 'Delete fails to remove file.');
fails(fn() => $repo->fetchById($copy2->id), OutOfBoundsException::class);
$repo->failCreate = true;
$before = glob(dirname($first->path) . '/*');
fails(fn() => $manager->copy($first->id), RuntimeException::class);
check(glob(dirname($first->path) . '/*') === $before, 'Failed copy leaves an orphan.');
$staged = $temp . 'failed.txt'; file_put_contents($staged, 'failed');
$failedEntry = (new vfs\storage\StorageEntry())->setName('failed')->setPath($staged)->setOwnerId(1)->setOwnerType(vfs\storage\enums\EOwnerType::PACKAGE);
fails(fn() => $strategy->register($failedEntry, 'Demo'), RuntimeException::class);
check($failedEntry->path === $staged && is_file($staged), 'Failed registration loses staged source.');
$repo->failCreate = false;
fails(fn() => $strategy->register($failedEntry, '../escape'), InvalidArgumentException::class);
// Failure after insert must roll back the row and leave the entry reusable.
$repo->failUpdate = true;
$count = $pdo->query('SELECT COUNT(*) FROM awt_storage')->fetchColumn();
fails(fn() => $repo->create($failedEntry), RuntimeException::class);
check($failedEntry->id === null && $pdo->query('SELECT COUNT(*) FROM awt_storage')->fetchColumn() === $count, 'Partial registration was committed.');
$repo->failUpdate = false;
check($manager->rename($first->id, 'renamed file.txt'), 'Rename failed.');
$renamed = $repo->fetchById($first->id);
check($renamed->name === 'renamed file.txt' && str_ends_with($renamed->url, 'renamed%20file.txt') && is_file($renamed->path), 'Rename metadata is stale.');
check($manager->move($first->id, $temp . 'moved'), 'Move failed.');
// Middleware must run on every request even after warming database caches.
$controller = new bootstrap\controllers\StorageController();
check(status($controller->index(['id' => $first->id])) === 200, 'Authorized request failed.');
TestMiddleware::$allowed = false;
check(status($controller->index(['id' => $first->id])) === 403 && TestMiddleware::$calls === 2, 'Cached download bypasses authorization.');
fails(fn() => (new vfs\storage\Storage($temp . 'public/', $manager, $fs))->download($first->id), vfs\storage\StorageAccessDenied::class);
check(status($controller->index(['id' => 'bad'])) === 404, 'Invalid ID accepted.');
TestMiddleware::$allowed = true;
// Recursive caches, missing paths, hash mode, per-entry mode, and corrupt caches.
$watchedFile = $temp . 'watched.txt'; file_put_contents($watchedFile, 'old');
$pool = new vfs\cache\CachePool('watch');
$pool->createConfig(vfs\cache\enums\ECacheValidation::MODIFIED, [$watchedFile])->setCache('file', ['old']);
file_put_contents($watchedFile, 'new and longer');
check($pool->getCache('file') === false, 'File change was not detected.');
$missing = $temp . 'missing';
$pool->createConfig(vfs\cache\enums\ECacheValidation::MODIFIED, [$missing])->setCache('missing', []);
check($pool->getCache('missing') === [], 'Missing watched path cannot be cached.');
mkdir($missing);
check($pool->getCache('missing') === false, 'Creation of missing watched path was not detected.');
$pool->createConfig(vfs\cache\enums\ECacheValidation::HASH, [$watchedFile])->setCache('hash', []);
$mtime = filemtime($watchedFile); file_put_contents($watchedFile, 'xxx and longer'); touch($watchedFile, $mtime);
check($pool->getCache('hash') === false, 'Hash mode misses same-size modification.');
$pool->createConfig(vfs\cache\enums\ECacheValidation::MODIFIED, [$watchedFile])->setCache('mode', []);
$pool->createConfig(vfs\cache\enums\ECacheValidation::NONE, []);
unlink($watchedFile);
check($pool->getCache('mode') === false, 'Changing pool configuration changes existing entry validation.');
$pool->setCache('empty', []);
check($pool->getCache('empty') === [], 'Empty arrays are confused with cache misses.');
$bad = $pool->setCache('bad', [1]); file_put_contents($bad->getStorageEntry()->getPath(), '<?php broken syntax!');
check($pool->getCache('bad') === false, 'Corrupt cache crashes application.');
mkdir(DATA . 'storage/framework/cache/bad-config'); file_put_contents(DATA . 'storage/framework/cache/bad-config/config.json', 'invalid');
check((new vfs\cache\CachePool('bad-config'))->getCache('key') === false, 'Corrupt configuration crashes application.');
$expiring = $pool->createConfig(vfs\cache\enums\ECacheValidation::EXPIRE, [])->setCache('expired', []);
$payload = include $expiring->getStorageEntry()->getPath();
$payload['time'] = time() - 3601;
$expiring->getStorageEntry()->write($payload);
check($pool->getCache('expired') === false, 'Expired entry was served.');
$legacy = $pool->setCache('legacy', []);
$legacy->getStorageEntry()->write(['time' => time(), 'watched' => [], 'data' => []]);
check($pool->getCache('legacy') === false, 'Old cache schema did not rebuild.');
// Resource lookup stays compatible with runtime source lookup; HTTP serving is restricted.
mkdir(PACKAGES . 'Demo/views/assets/css/deep', 0755, true);
file_put_contents(PACKAGES . 'Demo/main.php', '<?php echo "private";');
file_put_contents(PACKAGES . 'Demo/views/assets/css/deep/style.css', 'body {}');
$resource = new vfs\resource\Resource('Demo');
check($resource->get('Demo:/main.php', true) === rtrim(PACKAGES, '/') . '/Demo/main.php', 'Internal source lookup broke.');
check($resource->get('Demo:style.css') !== null, 'Explicit package recursive lookup broke.');
file_put_contents(PACKAGES . 'Demo/views/assets/css/deep/new.css', 'new');
check($resource->get('views/assets/css/deep/new.css') !== null, 'Nested new resource not detected.');
unlink(PACKAGES . 'Demo/views/assets/css/deep/new.css');
check($resource->get('views/assets/css/deep/new.css') === null, 'Deleted resource remains cached.');
fails(fn() => $resource->get('missing', true), vfs\resource\exceptions\ResourceException::class);
check($resource->get('') === null && $resource->get('../main.php') === null, 'Malformed resource alias accepted.');
check(status($controller->Resource(['package' => 'Demo', 'file' => 'main.php'])) === 404, 'Source is publicly served.');
check(status($controller->Resource(['package' => 'Demo', 'file' => 'views/assets/css/deep/style.css'])) === 200, 'Public asset cannot be served.');
check(status($controller->Resource(['package' => 'Demo', 'resource0' => 'views', 'resource1' => 'assets', 'resource2' => 'css', 'resource3' => 'deep', 'file' => 'style.css'])) === 200, 'Nested route parameters cannot be served.');
file_put_contents(PACKAGES . 'Demo/config.json', '{"secret":true}');
check(status($controller->Resource(['package' => 'Demo', 'file' => 'config.json'])) === 404, 'Private data is publicly served.');
symlink($temp . 'failed.txt', PACKAGES . 'Demo/views/assets/external.css');
check(status($controller->Resource(['package' => 'Demo', 'file' => 'views/assets/external.css'])) === 404, 'External symlink is publicly served.');
check(status($controller->Resource(['package' => 'Demo', 'file' => 'views/assets/%2e%2e/main.php'])) === 404, 'Encoded traversal accepted.');
// Transient traversal, subpool selection, checked I/O, metadata and recursive enumeration.
$store = (new vfs\transient\TransientStorage())->setPool('temp')->setSubPool('scan');
$a = $store->createFile('a', vfs\transient\enums\ETransientType::TXT, 'a');
mkdir(DATA . 'storage/framework/temp/scan/sub'); file_put_contents(DATA . 'storage/framework/temp/scan/sub/b.txt', 'b');
check(count($store->getFiles()) === 2, 'Recursive scan loses root files.');
$store->setSubPool('other')->createFile('c', vfs\transient\enums\ETransientType::TXT, 'c');
check(is_file(DATA . 'storage/framework/temp/other/c.txt') && !is_dir(DATA . 'storage/framework/temp/scan/other'), 'Subpool switching nests paths.');
fails(fn() => $store->setPool('unknown'), InvalidArgumentException::class);
fails(fn() => $store->setSubPool('../escape'), InvalidArgumentException::class);
fails(fn() => $store->createFile('../escape', vfs\transient\enums\ETransientType::TXT, 'x'), InvalidArgumentException::class);
$a->write('a longer value'); check($a->size === 14, 'Write metadata is stale.');
$renamedTransient = $store->renameFile($a, 'renamed.txt');
check(is_file($renamedTransient->path) && !is_file($a->path), 'Transient rename failed.');
$store->copyFile($renamedTransient, 'copy.txt');
fails(fn() => $store->copyFile($renamedTransient, 'copy.txt'), RuntimeException::class);
fails(fn() => (new vfs\transient\TransientStorageEntry('bad', $temp . 'absent-dir/file.txt'))->write('x'), RuntimeException::class);
check(glob(DATA . 'storage/framework/temp/scan/.write-*') === [], 'Atomic write leaves temporary files.');
// Existing public JSON assets remain available, while package configuration stays private.
file_put_contents(PACKAGES . 'Demo/views/assets/locale.json', '{"hello":"Hello"}');
check(status($controller->Resource(['package' => 'Demo', 'file' => 'views/assets/locale.json'])) === 200, 'Public JSON asset was blocked.');
// Actual generated route preserves all path segments and ignores query strings.
define('BASE_PATH', rtrim($root, '/'));
$_SERVER['REQUEST_URI'] = '/awt_packages/Demo/views/assets/css/deep/style.css?v=1';
$routes = require $root . 'bootstrap/routes/default.php';
$params = $routes[1]->match(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
check($params !== null && status($controller->Resource($params)) === 200, 'Default resource route breaks nested asset URLs.');
// Explicit package replacements must continue to work with exclusive filesystem operations.
mkdir($temp . 'asset-source'); file_put_contents($temp . 'asset-source/asset.css', 'first');
$generator = new installer\package\PackageStorageTreeGenerator($fs, $repo);
$generator->setSource($temp . 'asset-source'); $generator->setDestination($temp . 'asset-output');
$generator->buildStorageTree()->generate();
file_put_contents($temp . 'asset-source/asset.css', 'replacement');
$generator->buildStorageTree()->generate();
check(file_get_contents($temp . 'asset-output/asset.css') === 'replacement' && count(glob($temp . 'asset-output/*')) === 1, 'Package asset replacement broke.');
// Custom URLs survive repository creation and rename.
$custom = (new vfs\storage\StorageEntry())->setName('custom')->setPath($temp . 'asset-output/asset.css')->setUrl('/assets/custom.css');
$repo->create($custom); $manager->rename($custom->id, 'new-name');
check($repo->fetchById($custom->id)->url === '/assets/custom.css', 'Custom URL was overwritten.');
// File writes serialize JSON arrays according to the file type.
$json = $store->createFile('json', vfs\transient\enums\ETransientType::JSON, ['ok' => true]);
$json->loadContent();
check(json_decode($json->content, true) === ['ok' => true], 'JSON array was written as PHP code.');
// Linked package roots are supported without descending through directory-link cycles.
mkdir($temp . 'linked-package'); file_put_contents($temp . 'linked-package/first.txt', 'first');
symlink($temp . 'linked-package', PACKAGES . 'Linked');
$linked = new vfs\resource\Resource('Linked'); check($linked->get('first.txt') !== null, 'Linked package root cannot be resolved.');
file_put_contents($temp . 'linked-package/new.txt', 'new');
check($linked->get('new.txt') !== null, 'Linked package root changes are not detected.');
symlink($temp . 'linked-package', $temp . 'linked-package/cycle');
check($linked->get('new.txt') !== null, 'Directory-link cycle breaks resource lookup.');
// Blade resources resolve aliases at render time and retain full package-relative URL paths.
mkdir($temp . 'compiled');
$blade = new render\TemplateEngine\BladeOne(PACKAGES, $temp . 'compiled', render\TemplateEngine\BladeOne::MODE_AUTO);
$blade->setPackageName('Demo');
$cssUrl = HOSTNAME . '/awt_packages/Demo/views/assets/css/deep/style.css';
foreach (["@resource('style.css')", '@resource("style.css")', "@resource('views/assets/css/deep/style.css')",
    "@resource('Demo:style.css')", "@resource('Demo:views/assets/css/deep/style.css')",
    "@resource('Demo:/views/assets/css/deep/style.css')"] as $directive) {
    check($blade->runString($directive) === $cssUrl, 'Blade resource alias failed: ' . $directive);
}
check($blade->runString('<link href="@resource($alias)">', ['alias' => 'style.css']) === '<link href="' . $cssUrl . '">', 'Resource variables cannot be used in attributes.');
check($blade->runString("@resource(sprintf('Demo:%s', 'style.css'))") === $cssUrl, 'Resource expression compilation broke.');
check($blade->runString("@resource('missing.css')") === '', 'Missing optional resource does not produce an empty string.');
fails(fn() => $blade->runString("@resource('missing.css', true)"), vfs\resource\exceptions\ResourceException::class);
file_put_contents(PACKAGES . 'Demo/views/assets/a&" b.css', 'body {}');
check($blade->runString('@resource($alias)', ['alias' => 'a&" b.css']) === HOSTNAME . '/awt_packages/Demo/views/assets/a%26%22%20b.css', 'Resource filenames are not URL encoded.');
mkdir(PACKAGES . 'Other/views/assets', 0755, true);
file_put_contents(PACKAGES . 'Other/views/assets/style.css', 'other');
$blade->setPackageName('Other');
check($blade->runString("@resource('style.css')") === HOSTNAME . '/awt_packages/Other/views/assets/style.css', 'Template package context is ignored.');
check($blade->runString("@resource('Demo:style.css')") === $cssUrl, 'Explicit package cannot override template context.');
$eventDispatcher = new event\EventDispatcher();
$switch = new vfs\resource\event\ContextSwitchEvent(); $switch->contextName = 'Demo';
$eventDispatcher->addListener('vfs.context.request', $switch);
$blade->setPackageName('');
check($blade->runString("@resource('style.css')") === $cssUrl, 'Active event context is ignored when the template package is absent.');
// Compiled templates cannot bake in the package context or retain obsolete directive code.
$views = $temp . 'blade-views'; mkdir($views);
file_put_contents($views . '/resource.awt.php', "@resource('style.css')");
$cachedBlade = new render\TemplateEngine\BladeOne($views, $temp . 'compiled', render\TemplateEngine\BladeOne::MODE_AUTO);
$cachedBlade->setFileExtension('.awt.php'); $cachedBlade->setPackageName('Demo');
check($cachedBlade->run('resource') === $cssUrl, 'File-based directive rendering failed.');
$compiled = $cachedBlade->getCompiledFile('resource');
$cachedBlade->setPackageName('Other');
check($cachedBlade->run('resource') === HOSTNAME . '/awt_packages/Other/views/assets/style.css', 'Cached directive uses stale package context.');
file_put_contents($compiled, 'obsolete vendor URL');
touch($compiled, filemtime(CLASSES . 'render/TemplateEngine/BladeOne.php') - 1);
clearstatcache(true, $compiled);
check($cachedBlade->run('resource') === HOSTNAME . '/awt_packages/Other/views/assets/style.css', 'Compiler update did not refresh the compiled template.');
echo "VFS: {$checks} checks passed.\n";
