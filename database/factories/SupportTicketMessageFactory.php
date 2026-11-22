<?php

namespace Database\Factories;

use App\Models\SupportTicket;
use App\Models\SupportTicketMessage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupportTicketMessage>
 */
class SupportTicketMessageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'support_ticket_id' => SupportTicket::factory(),
            'from_support' => false,
            'body' => fake()->paragraph(),
        ];
    }
}
