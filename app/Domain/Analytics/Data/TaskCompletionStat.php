<?php

namespace App\Domain\Analytics\Data;

/**
 * Completion numbers for one catalog task, summed over all workspaces.
 */
final readonly class TaskCompletionStat
{
    public function __construct(
        public string $catalogTaskKey,
        public string $title,
        public int $projects,
        public int $started,
        public int $completed,
        public ?float $avgHoursToComplete,
    ) {}

    public function completionRate(): float
    {
        return $this->started === 0 ? 0.0 : round($this->completed / $this->started, 2);
    }
}
