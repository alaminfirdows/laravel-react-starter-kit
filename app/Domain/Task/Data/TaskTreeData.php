<?php

namespace App\Domain\Task\Data;

use Illuminate\Contracts\Support\Arrayable;

/**
 * @implements Arrayable<string, mixed>
 */
final readonly class TaskTreeData implements Arrayable
{
    /**
     * @param  list<TaskGroupData>  $groups
     */
    public function __construct(
        public int $progressPct,
        public array $groups,
    ) {}

    /**
     * @return array{progressPct: int, groups: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'progressPct' => $this->progressPct,
            'groups' => array_map(fn (TaskGroupData $group): array => $group->toArray(), $this->groups),
        ];
    }
}
