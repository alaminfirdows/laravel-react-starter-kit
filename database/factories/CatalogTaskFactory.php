<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\CatalogStatus;
use App\Domain\Catalog\Models\CatalogCategory;
use App\Domain\Catalog\Models\CatalogTask;
use App\Domain\Task\Enums\TaskPriority;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CatalogTask>
 */
class CatalogTaskFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);

        return [
            'key' => 'test.'.Str::slug($title),
            'category_id' => CatalogCategory::factory(),
            'title' => $title,
            'summary' => fake()->sentence(),
            'body_md' => "## Why\n\n".fake()->paragraph(),
            'priority_default' => TaskPriority::P2,
            'version' => 1,
            'content_hash' => hash('sha256', $title),
            'status' => CatalogStatus::Published,
            'published_at' => now(),
            'sort_order' => 0,
        ];
    }

    public function childOf(CatalogTask $parent): static
    {
        return $this->state(['parent_id' => $parent->id, 'category_id' => $parent->category_id]);
    }
}
