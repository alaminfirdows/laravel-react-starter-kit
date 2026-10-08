<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\Skill;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Skill>
 */
class SkillFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = fake()->unique()->slug(2);

        return [
            'key' => $key,
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'version' => '1.0.0',
            'source_path' => "resources/skills/{$key}",
            'content_hash' => hash('sha256', $key),
        ];
    }
}
