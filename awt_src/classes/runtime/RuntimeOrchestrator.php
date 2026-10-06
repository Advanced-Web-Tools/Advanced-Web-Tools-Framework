<?php
namespace runtime;

use runtime\enums\ERuntimeStatus;
use runtime\interface\IRuntime;
use runtime\interface\IRuntimeExecutor;
use runtime\interface\IRuntimeOrchestrator;

/** Validates the installed dependency graph before running any package code. */
final class RuntimeOrchestrator implements IRuntimeOrchestrator
{
    private array $byName = [];
    private array $stack = [];
    private array $loaded = [];

    public function __construct(private IRuntimeExecutor $runtimeExecutor, private array $runtimes, private array $installed = [])
    {
        foreach ($runtimes as $runtime) {
            $name = $runtime->getRuntimeName();
            if (isset($this->byName[$name])) throw new \runtime\exceptions\RuntimeException("Duplicate runtime package: {$name}");
            $this->byName[$name] = $runtime;
        }
        if ($installed === []) {
            $this->installed = array_map(fn($runtime) => $runtime->getPackage(), $runtimes);
        }
        $this->runtimeExecutor->setDependencyRunner($this->runPackage(...));
    }

    public function getRuntimes(): array { return $this->runtimes; }
    public function run(): array
    {
        $this->validate();
        foreach (array_keys($this->byName) as $name) $this->runPackage($name);
        return $this->loaded;
    }

    private function validate(): void
    {
        $installed = [];
        foreach ($this->installed as $package) $installed[$package->getName()] = $package;
        foreach ($this->byName as $name => $runtime) {
            $package = $runtime->getPackage();
            if (!is_file($runtime->getRootPath() . 'main.php')) {
                throw new \runtime\exceptions\RuntimeException("Package {$name} has no main.php entry point.");
            }
            if (defined('AWT_VERSION') && (version_compare(AWT_VERSION, $package->minimum_awt_version, '<')
                || ($package->maximum_awt_version !== null && version_compare(AWT_VERSION, $package->maximum_awt_version, '>')))) {
                throw new \runtime\exceptions\RuntimeException("Package {$name} is incompatible with AWT " . AWT_VERSION);
            }
            foreach ($package->getDependencies() as $dependency) {
                $target = $installed[$dependency['name']] ?? null;
                if ($target === null) throw new \runtime\exceptions\RuntimeException("Package {$name} requires missing package {$dependency['name']}.");
                if (!$target->getStatus()) throw new \runtime\exceptions\RuntimeException("Package {$name} requires disabled package {$dependency['name']}.");
                if (!\package\dependency\Dependency::matchesVersion($target->getVersion(), $dependency['version'])) {
                    throw new \runtime\exceptions\RuntimeException("Package {$name} requires {$dependency['name']} {$dependency['version']}; installed {$target->version}.");
                }
                if ($target->type !== 0 && !isset($this->byName[$target->name])) {
                    throw new \runtime\exceptions\RuntimeException("Dependency {$target->name} has no executable runtime.");
                }
            }
        }
        $visited = [];
        $visiting = [];
        $visit = function (string $name) use (&$visit, &$visited, &$visiting): void {
            if (isset($visiting[$name])) throw new \runtime\exceptions\RuntimeException('Circular manifest dependency: ' . implode(' -> ', array_keys($visiting)) . ' -> ' . $name);
            if (isset($visited[$name])) return;
            $visiting[$name] = true;
            foreach ($this->byName[$name]->getPackage()->getDependencies() as $dep) {
                if (isset($this->byName[$dep['name']])) $visit($dep['name']);
            }
            unset($visiting[$name]);
            $visited[$name] = true;
        };
        foreach (array_keys($this->byName) as $name) $visit($name);
    }

    private function runPackage(string $name): void
    {
        if (isset($this->loaded[$name])) return;
        $runtime = $this->byName[$name] ?? throw new \runtime\exceptions\RuntimeException("Cannot wait for missing, disabled, or non-runtime package {$name}.");
        if (isset($this->stack[$name])) throw new \runtime\exceptions\RuntimeException('Circular runtime wait: ' . implode(' -> ', array_keys($this->stack)) . ' -> ' . $name);
        $this->stack[$name] = true;
        $runtime->setRuntimeStatus(ERuntimeStatus::RUNNING);
        try {
            foreach ($runtime->getPackage()->getDependencies() as $dep) {
                if (isset($this->byName[$dep['name']])) $this->runPackage($dep['name']);
            }
            $this->runtimeExecutor->run($runtime);
            $runtime->setRuntimeStatus(ERuntimeStatus::EXECUTED);
            $this->loaded[$name] = $runtime;
        } catch (\Throwable $e) {
            $runtime->setRuntimeStatus(ERuntimeStatus::STOPPED);
            throw $e;
        } finally {
            unset($this->stack[$name]);
        }
    }
}
