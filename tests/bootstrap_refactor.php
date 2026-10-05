<?php
/** End-to-end CLI bootstrap with the actual framework configuration and a temporary DB. */
$root = dirname(__DIR__) . '/';
$temp = sys_get_temp_dir() . '/awt-bootstrap-test-' . bin2hex(random_bytes(6)) . '/';
mkdir($temp, 0755, true);
define('BASE_PATH', $root);
define('ROOT', rtrim($root, '/'));
define('CLASSES', $root . 'awt_src/classes/');
define('FUNCTIONS', $root . 'awt_src/functions/');
define('CONFIG', $root . 'awt_data/config/');
define('DATA', $temp . 'data/');
define('PACKAGES', $temp . 'packages/');
define('COMPILED', $temp . 'compiled/');
define('ERRORS_DIRECTORY', $root . 'public/errors/');
define('CACHE', DATA . 'storage/framework/cache/');
define('TEMP', DATA . 'storage/framework/temp/');
define('PACKAGE_STORAGE', DATA . 'storage/public/packages/');
mkdir(PACKAGES, 0755, true);
mkdir(COMPILED, 0755, true);
$packageSource = is_dir(__DIR__ . '/fixtures/Branislav10')
    ? __DIR__ . '/fixtures/Branislav10' : $root . 'awt_packages/Branislav10';
if (!is_file($packageSource . '/main.php')) throw new RuntimeException('Provide Branislav10 in awt_packages/ or tests/fixtures/ to run bootstrap checks.');
symlink($packageSource, PACKAGES . 'Branislav10');
libxml_use_internal_errors(true);
function removeBootstrapTree(string $path): void {
    if (is_link($path) || is_file($path)) { unlink($path); return; }
    foreach (array_diff(scandir($path), ['.', '..']) as $name) removeBootstrapTree($path . '/' . $name);
    rmdir($path);
}
register_shutdown_function(fn() => removeBootstrapTree(rtrim($temp, '/')));
$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE awt_package (
    id INTEGER PRIMARY KEY, name TEXT, author TEXT, description TEXT, icon TEXT,
    preview_image TEXT, license TEXT, license_url TEXT, version TEXT,
    minimum_awt_version TEXT, maximum_awt_version TEXT, type INTEGER,
    system_package INTEGER, status INTEGER, installation_date TEXT, dependencies TEXT,
    store_id INTEGER, installed_by INTEGER
);
INSERT INTO awt_package(id,name,version,minimum_awt_version,type,system_package,status,installation_date,dependencies)
VALUES(1,'AWT','27.0.0','27.0.0',0,1,1,'2026-10-05','[]'),(2,'Branislav10','1.0.0','27.0.0',1,0,1,'2026-10-05','[]');
CREATE TABLE awt_setting(id INTEGER PRIMARY KEY,package_id INTEGER,name TEXT,value TEXT,value_type TEXT,category TEXT,required_permission_level INTEGER);
INSERT INTO awt_setting VALUES(1,1,'hostname path','','text','System',1),(2,1,'website name','Test AWT','text','System',1);");
$shared = ['DBEngine' => ['PDO' => $pdo]];
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['argv'] = ['awt', 'routes'];
ob_start();
require $root . 'bootstrap/boot.php';
$output = ob_get_clean();
if (!$loader instanceof runtime\RuntimeExecutor || count($routerManager->getRoutes()) !== 10) {
    throw new RuntimeException('Full bootstrap did not register package and default routes.');
}
if (!str_contains($output, '/products/reed_knife') || WEB_NAME !== 'Test AWT') {
    throw new RuntimeException('CLI route command or database-backed settings failed.');
}
$html = $routerManager->getRouteByPath('/products/reed_knife')->route()->render();
if (!str_contains($html, 'Branislav10 | Reed Knife')) throw new RuntimeException('Full bootstrap failed to render the real product page.');
echo "Passed full bootstrap, CLI routes, settings, and rendering checks.\n";
