<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\CatalogPhase;
use App\Domain\Catalog\Models\CatalogCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogCategory>
 */
class CatalogCategoryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'phase' => CatalogPhase::Foundation,
            'sort_order' => 0,
        ];
    }
}
