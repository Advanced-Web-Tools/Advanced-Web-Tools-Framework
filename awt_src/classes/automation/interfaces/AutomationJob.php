<?php

namespace automation\interfaces;

interface AutomationJob
{
    /** Stable name unique within the package. */
    public function name(): string;

    /** Five-field cron expression evaluated in PHP's default timezone. */
    public function on(): string;

    public function run(): void;
}
