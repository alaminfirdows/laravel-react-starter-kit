<?php

namespace App\Domain\Analytics\Data;

/**
 * Started tasks with no progress for a while, per catalog task.
 */
final readonly class TaskDropOff
{
    public function __construct(
        public string $catalogTaskKey,
        public string $title,
        public int $started,
        public int $stalled,
    ) {}

    public function dropOffRate(): float
    {
        return $this->started === 0 ? 0.0 : round($this->stalled / $this->started, 2);
    }
}
