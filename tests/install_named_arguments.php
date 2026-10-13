<?php

/** Run with php tests/install_named_arguments.php. Does not install or connect. */
declare(strict_types=1);

use cli\CLIHandler;
use cli\commands\InstallCommand;
use installer\framework\FrameworkInstallService;

define('DB_TYPE', '');
define('ROOT', dirname(__DIR__));
spl_autoload_register(static function (string $class): void {
    $path = dirname(__DIR__) . '/awt_src/classes/' . str_replace('\\', '/', $class) . '.php';
    if (is_file($path)) require_once $path;
});

function check(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$installer = new class extends FrameworkInstallService {
    public int $calls = 0;
    public function install(array $configuration): void
    {
        ++$this->calls;
        throw new RuntimeException('Test installation reached.');
    }
};
$command = new InstallCommand($installer);
$parser = new CLIHandler();
$tokens = $parser->parseInput('install db:"awt" name:"My Website" password:"secret:with:colons" type:"mysql" username:"root"');
$command->execute(array_shift($tokens), $tokens);
check($command->result() === 'Test installation reached.', 'Named arguments accepted in any order');
check($command->getInstallationArguments() === [
    'database_type' => 'mysql', 'database_name' => 'awt', 'host' => 'localhost',
    'username' => 'root', 'password' => 'secret:with:colons',
    'website_name' => 'My Website', 'host_path' => '/',
], 'Configuration mapping and defaults');

// argv tokens from the shell have already had quotes removed.
$command->execute('install', ['type:sqlite', 'name:SQLite site']);
check($command->getInstallationArguments()['database_name'] === ROOT . '/awt_db.sqlite', 'SQLite uses root database without db argument');
check($command->getInstallationArguments()['username'] === '', 'SQLite credentials optional');
$command->execute('install', ['db_type:sqlite', 'db:other.sqlite', 'name:SQLite site']);
check($command->getInstallationArguments()['database_name'] === ROOT . '/awt_db.sqlite', 'SQLite ignores custom database path and accepts db_type alias');
$tokens = $parser->parseInput('install type:"mysql" db:"awt" username:"root" name:"Site" host:"db.local" path:"/app/" password:""');
$command->execute(array_shift($tokens), $tokens);
check($command->getInstallationArguments()['host_path'] === '/app/', 'Explicit path');
check($command->getInstallationArguments()['host'] === 'db.local', 'Explicit host');
check($command->getInstallationArguments()['password'] === '', 'Explicit empty password');

$calls = $installer->calls;
foreach ([
    [[], 'Missing required installation argument: type.'],
    [['type:sqlite', 'db:test'], 'Missing required installation argument: name.'],
    [['type:mysql', 'db:', 'name:Site'], 'Missing required installation argument: db.'],
    [['type:sqlite', 'type:mysql', 'db:test', 'name:Site'], 'Duplicate installation argument: type.'],
    [['type:sqlite', 'db_type:sqlite', 'name:Site'], 'Duplicate installation argument: db_type.'],
    [['unknown:secret'], 'Unknown installation argument.'],
    [['mysql', 'awt', '', 'root', '', 'Site', ''], 'Use named arguments'],
    [['type:mysql', 'db:awt', 'name:Site'], 'Database username must not be empty.'],
    [['type:invalid', 'db:awt', 'name:Site'], 'Invalid database type.'],
] as [$args, $expected]) {
    $command->execute('install', $args);
    check(str_starts_with($command->result(), $expected), $expected);
    check($command->getInstallationArguments() === [], 'Invalid input clears configuration');
}
check($installer->calls === $calls, 'Invalid input never starts installation');
echo "Named installation argument checks passed.\n";
