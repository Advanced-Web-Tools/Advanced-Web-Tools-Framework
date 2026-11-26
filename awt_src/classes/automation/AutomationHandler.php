<?php

namespace automation;

use automation\interfaces\AutomationJob;
use object\ObjectHandler;

final class AutomationHandler
{
    /** @var array<string, array<string, array{AutomationJob, CronSchedule}>> */
    private array $jobs = [];

    /** @param class-string<AutomationJob> $className */
    public function addJob(string $package, string $className): void
    {
        if (!is_subclass_of($className, AutomationJob::class)) {
            throw new \InvalidArgumentException("Automation job {$className} must implement " . AutomationJob::class);
        }

        $job = ObjectHandler::createObject($className);
        $name = trim($job->name());
        if ($name === '') {
            throw new \InvalidArgumentException("Automation job {$className} must have a name.");
        }
        if (isset($this->jobs[$package][$name])) {
            throw new \InvalidArgumentException("Duplicate automation job {$package}/{$name}.");
        }

        try {
            $schedule = new CronSchedule($job->on());
        } catch (\InvalidArgumentException $exception) {
            throw new \InvalidArgumentException(
                "Invalid schedule for {$package}/{$name}. " . $exception->getMessage(),
                0,
                $exception,
            );
        }
        $this->jobs[$package][$name] = [$job, $schedule];
    }

    /** @return list<string> Failure messages for jobs due at the given time. */
    public function runDue(\DateTimeImmutable $time): array
    {
        $failures = [];
        foreach ($this->jobs as $package => $jobs) {
            foreach ($jobs as $name => [$job, $schedule]) {
                if (!$schedule->isDue($time)) {
                    continue;
                }
                try {
                    $job->run();
                } catch (\Throwable $exception) {
                    $failures[] = "{$package}/{$name} failed: " . $exception->getMessage();
                }
            }
        }
        return $failures;
    }
}
