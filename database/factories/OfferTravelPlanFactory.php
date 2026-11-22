<?php

namespace Database\Factories;

use App\Models\OfferTravelPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OfferTravelPlan>
 */
class OfferTravelPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel' => null,
            'transfers' => [],
            'revision' => 1,
        ];
    }
}
