<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\CatalogAction;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Task\Enums\ActionType;
use App\Domain\Task\Enums\Executor;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogAction>
 */
class CatalogActionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'catalog_task_id' => CatalogTask::factory(),
            'key' => fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
            'type' => ActionType::Manual,
            'executor' => Executor::User,
            'instructions_md' => fake()->paragraph(),
            'is_required' => true,
            'requires_approval' => false,
            'sort_order' => 0,
        ];
    }
}
