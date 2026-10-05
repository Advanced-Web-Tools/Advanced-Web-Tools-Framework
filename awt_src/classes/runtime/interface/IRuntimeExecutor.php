<?php
namespace runtime\interface;
interface IRuntimeExecutor
{
    public function setRuntime(IRuntime $runtime): void;
    public function initRuntime(): void;
    public function setupEnvironment(): void;
    public function setup(): void;
    public function execute(): void;
    public function run(IRuntime $runtime): void;
    public function setDependencyRunner(callable $runner): void;
}
