<?php

namespace App\Checks;

final readonly class CheckResult
{
    public function __construct(
        public bool $passed,
        public string $reason,
    ) {}

    public static function pass(string $reason): self
    {
        return new self(true, $reason);
    }

    public static function fail(string $reason): self
    {
        return new self(false, $reason);
    }
}
