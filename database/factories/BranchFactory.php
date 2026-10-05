<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Business;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Branch>
 */
class BranchFactory extends Factory
{
    protected $model = Branch::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company() . ' Şubesi';

        return [
            'business_id' => Business::factory(),
            'name' => $name,
            'slug' => Str::slug($name) . '-' . fake()->unique()->numberBetween(1, 999999),
            'phone' => fake()->phoneNumber(),
            'email' => fake()->unique()->safeEmail(),
            'country_code' => 'TR',
            'city' => fake()->city(),
            'district' => fake()->citySuffix(),
            'address' => fake()->address(),
            'postal_code' => fake()->postcode(),
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'status' => 'active',
        ];
    }
}