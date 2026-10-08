<?php

namespace Database\Factories;

use App\Domain\Catalog\Enums\ResourceType;
use App\Domain\Catalog\Models\CatalogResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CatalogResource>
 */
class CatalogResourceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'key' => fake()->unique()->slug(2),
            'type' => ResourceType::Article,
            'title' => fake()->sentence(3),
            'url' => fake()->url(),
        ];
    }
}
