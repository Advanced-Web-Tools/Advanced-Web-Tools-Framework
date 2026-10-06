<?php

namespace cli\commands;

use cli\interfaces\CLICommand;
use installer\framework\FrameworkInstallService;

class InstallCommand implements CLICommand
{
    private string $result = '';

    /** @var array<string, string> */
    private array $installationArguments = [];

    public function __construct(private FrameworkInstallService $installer = new FrameworkInstallService()) {}

    public function getCommand(): string
    {
        return 'install';
    }

    public function getHelp(): string
    {
        return 'Installation of the framework is available only when DB_TYPE is empty. Supply all seven arguments in order.'
            . PHP_EOL . 'Use "" or two single quotes for an empty argument; quote values containing spaces.'
            . PHP_EOL . 'Example: install mysql awt "" root "" "My Website" ""';
    }

    public function getArguments(): array
    {
        return [
            "<database_type>" => "Database type. Valid options are mysql, postgresql, sqlite",
            "<database_name>" => "Database name",
            "<host>" => "Database host. Leave empty if you want to use the default host which is 'localhost'.",
            "<username>" => "Database username",
            "<password>" => "Database password. Can be empty but not suggested for security reasons.",
            "<website_name>" => "Website name",
            "<host_path>" => "Host path. Leave empty if you want to use the default path which is '/'."
        ];
    }

    public function execute(string $command, array $args = []): void
    {
        $this->result = '';
        $this->installationArguments = [];

        if (count($args) !== count($this->getArguments())) {
            $this->result = 'Invalid number of arguments. Type `help install` for help.';
            return;
        }

        $configuration = array_combine(
            ['database_type', 'database_name', 'host', 'username', 'password', 'website_name', 'host_path'],
            array_values($args)
        );

        try {
            $this->installationArguments = $this->installer->validateConfiguration($configuration);
            $this->installer->install($this->installationArguments);
            $this->configAdmin();
            $this->installPackage();
            $this->result = 'Installation completed.';
        } catch (\InvalidArgumentException | \RuntimeException $exception) {
            $this->result = $exception->getMessage();
        }
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
