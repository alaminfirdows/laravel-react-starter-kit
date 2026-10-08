<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Catalog\Models\Pack;
use App\Domain\Project\Enums\ProjectPhase;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Pack>
 */
class PackFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => 'pack-'.fake()->unique()->slug(2),
            'name' => fake()->words(3, true),
            'audience' => ['phase' => ProjectPhase::Planning->value],
            'is_default' => false,
            'version' => 1,
            'status' => CatalogStatus::Published,
        ];
    }

    public function defaultFor(ProjectPhase $phase): static
    {
        return $this->state(['audience' => ['phase' => $phase->value], 'is_default' => true]);
    }

    public function withTasks(CatalogTask ...$tasks): static
    {
        return $this->afterCreating(function (Pack $pack) use ($tasks): void {
            foreach (array_values($tasks) as $i => $task) {
                $pack->items()->create(['catalog_task_id' => $task->id, 'include_subtree' => true, 'sort_order' => $i]);
            }
        });
    }
}
