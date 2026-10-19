<?php

namespace cli\commands;

use cli\interfaces\CLICommand;
use installer\framework\FrameworkInstallService;

class InstallCommand implements CLICommand
{
    private string $result = '';

    /** @var array<string, string> */
    private array $installationArguments = [];

    private const ARGUMENT_FIELDS = [
        'type' => 'database_type',
        'db_type' => 'database_type',
        'db' => 'database_name',
        'host' => 'host',
        'username' => 'username',
        'password' => 'password',
        'name' => 'website_name',
        'path' => 'host_path',
    ];

    public function __construct(private FrameworkInstallService $installer = new FrameworkInstallService()) {}

    public function getCommand(): string
    {
        return 'install';
    }

    public function getHelp(): string
    {
        return implode(PHP_EOL, [
            'Install the framework and configure its database.',
            '',
            'Usage:',
            '  php awt install type:"<database type>" name:"<website name>" [key:"value" ...]',
            '  In the awt> prompt, omit "php awt".',
            '',
            'Examples:',
            '  SQLite:',
            '    install type:"sqlite" name:"My Website"',
            '  MySQL:',
            '    install type:"mysql" name:"My Website" db:"awt" username:"root"',
            '  PostgreSQL:',
            '    install type:"postgresql" name:"My Website" db:"awt" username:"postgres" password:"secret"',
            '',
            'Pass arguments as key:"value" in any order. Quote values containing spaces.',
            'MySQL and PostgreSQL require an existing database and database username.',
            'SQLite creates awt_db.sqlite in the project root.',
            'Installation is available only before a database has been configured.',
        ]);
    }

    public function getArguments(): array
    {
        return [
            'type:"database type"' => 'Required. mysql, postgresql, or sqlite. Alias: db_type.',
            'name:"website name"' => 'Required. Website name.',
            'db:"database name"' => 'Required for MySQL / PostgreSQL. Ignored for SQLite.',
            'username:"user"' => 'Required for MySQL / PostgreSQL. Unused for SQLite.',
            'password:"password"' => 'Optional. Database password. Default: empty. Unused for SQLite.',
            'host:"hostname"' => 'Optional. Database host. Default: localhost. Unused for SQLite.',
            'path:"/"' => 'Optional. Website URL path, such as / or /my-site/. Default: /.',
        ];
    }

    public function execute(string $command, array $args = []): void
    {
        $this->result = '';
        $this->installationArguments = [];

        try {
            $configuration = $this->parseArguments($args);
            $this->installationArguments = $this->installer->validateConfiguration($configuration);
            $this->installer->install($this->installationArguments);
            $this->configAdmin();
            $this->installPackage();
            $this->result = 'Installation completed.';
        } catch (\InvalidArgumentException | \RuntimeException $exception) {
            $this->result = $exception->getMessage();
        }
    }

    /** Tokens have already had their surrounding quotes removed by the CLI parser or shell. */
    private function parseArguments(array $args): array
    {
        $configuration = array_fill_keys(array_values(self::ARGUMENT_FIELDS), '');
        $seen = [];

        foreach ($args as $argument) {
            if (!is_string($argument) || !str_contains($argument, ':')) {
                throw new \InvalidArgumentException('Use named arguments such as db:"awt" type:"mysql". Type `help install` for help.');
            }
            [$key, $value] = explode(':', $argument, 2);
            if (!isset(self::ARGUMENT_FIELDS[$key])) {
                throw new \InvalidArgumentException('Unknown installation argument. Type `help install` for help.');
            }
            $field = self::ARGUMENT_FIELDS[$key];
            if (isset($seen[$field])) {
                throw new \InvalidArgumentException("Duplicate installation argument: {$key}.");
            }
            $seen[$field] = true;
            $configuration[$field] = $value;
        }

        $required = ['type', 'name'];
        if (strtolower(trim($configuration['database_type'])) !== 'sqlite') $required[] = 'db';
        foreach ($required as $key) {
            if (!isset($seen[self::ARGUMENT_FIELDS[$key]]) || trim($configuration[self::ARGUMENT_FIELDS[$key]]) === '') {
                throw new \InvalidArgumentException("Missing required installation argument: {$key}.");
            }
        }

        return $configuration;
    }

    /** @return array<string, string> Validated arguments, or an empty array after validation fails. */
    public function getInstallationArguments(): array
    {
        return $this->installationArguments;
    }

    private function configAdmin(): void
    {
        print("Do you want to create an admin account? Y/n" . PHP_EOL);
        $input = readline();

        if(strtolower($input) === "n") {
            print("Admin account creation skipped" . PHP_EOL);
            return;
        }

        $email = readline("Enter email: ");
        $password = readline("Enter password: ");
        $username = readline("Enter username: ");
        $firstname = readline("Enter firstname: ");
        $lastname = readline("Enter lastname: ");

        $this->installer->createAdmin(compact('email', 'password', 'username', 'firstname', 'lastname'));

        print("Admin account created" . PHP_EOL);
    }

    private function installPackage(): void
    {
        global $cliHandler;

        print("Do you want to install packages? Y/n" . PHP_EOL);
        $input = readline();

        if(strtolower($input) === "n") {
            print("Package installation skipped");
            return;
        }

        $cliHandler->addCommand(new PackageManagerCommand());

        print("Package manager command registered. After completion of this installation, you can run the package manager command to install packages. Type 'help pm' to list all commands. " . PHP_EOL);
    }

    /**
     * @inheritDoc
     */
    public function result(): string
    {
        return $this->result;
    }
}
