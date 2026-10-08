<?php

namespace App\Checks;

/**
 * A machine check against the project's website. Never throws for network trouble:
 * an unreachable host is a failed result with a reason.
 */
interface Check
{
    public function label(): string;

    public function run(string $url): CheckResult;
}
