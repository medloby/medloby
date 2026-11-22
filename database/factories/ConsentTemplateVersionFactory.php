<?php

namespace Database\Factories;

use App\Models\ConsentTemplate;
use App\Models\ConsentTemplateVersion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ConsentTemplateVersion>
 */
class ConsentTemplateVersionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'consent_template_id' => ConsentTemplate::factory(),
            'revision' => 1,
            'title' => fake()->sentence(4),
            'content' => fake()->paragraph(),
        ];
    }
}
