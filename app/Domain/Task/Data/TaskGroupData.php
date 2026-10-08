<?php

namespace App\Domain\Task\Data;

use App\Domain\Task\Http\Resources\TaskNodeResource;
use App\Domain\Task\Models\Task;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Support\Collection;

/**
 * Root tasks of one catalog category, with their subtrees loaded as `children`.
 *
 * @implements Arrayable<string, mixed>
 */
final readonly class TaskGroupData implements Arrayable
{
    /**
     * @param  Collection<int, Task>  $tasks
     */
    public function __construct(
        public string $key,
        public string $name,
        public ?string $icon,
        public int $progressPct,
        public Collection $tasks,
    ) {}

    /**
     * @return array{key: string, name: string, icon: string|null, progressPct: int, tasks: mixed}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'name' => $this->name,
            'icon' => $this->icon,
            'progressPct' => $this->progressPct,
            'tasks' => TaskNodeResource::collection($this->tasks),
        ];
    }
}
