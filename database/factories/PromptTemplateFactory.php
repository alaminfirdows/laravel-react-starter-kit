<?php

namespace Database\Factories;

use App\Domain\Catalog\Models\PromptTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PromptTemplate>
 */
class PromptTemplateFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $body = 'Help {{ project.name }} with {{ task.title }}.';

        return [
            'key' => fake()->unique()->slug(2),
            'title' => fake()->sentence(3),
            'full_md' => $body,
            'target' => 'chat',
            'version' => 1,
            'content_hash' => hash('sha256', $body),
        ];
    }
}
