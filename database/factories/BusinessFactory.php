<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Business>
 */
class BusinessFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 999999),
            'type' => 'clinic',
            'description' => fake()->sentence(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'website' => fake()->url(),
            'country_code' => 'TR',
            'city' => 'Manisa',
            'district' => 'Salihli',
            'address' => fake()->address(),
            'postal_code' => fake()->postcode(),
            'latitude' => 38.48,
            'longitude' => 28.14,
            'status' => 'active',
            'is_verified' => true,
            'verified_at' => now(),
        ];
    }
}