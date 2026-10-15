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
        return 'Installation of the framework is available only when DB_TYPE is empty. Supply named arguments in any order.'
            . PHP_EOL . 'Use key:"value"; quote values containing spaces. type and name are required; db is required for MySQL and PostgreSQL.'
            . PHP_EOL . 'Example: install db:"awt" type:"mysql" username:"root" name:"My Website"'
            . PHP_EOL . 'SQLite: install type:"sqlite" name:"My Website" (creates awt_db.sqlite in the project root).';
    }

    public function getArguments(): array
    {
        return [
            'type:"type"' => 'Required. Database type: mysql, postgresql, sqlite. Alias: db_type.',
            'db:"name"' => 'Required for MySQL and PostgreSQL. SQLite always uses awt_db.sqlite in the project root.',
            'host:"host"' => "Optional. Database host; defaults to 'localhost'.",
            'username:"user"' => 'Required for MySQL and PostgreSQL; optional for SQLite.',
            'password:"password"' => 'Optional. Database password; defaults to empty.',
            'name:"website name"' => 'Required. Website name.',
            'path:"/"' => "Optional. Host path; defaults to '/'.",
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
