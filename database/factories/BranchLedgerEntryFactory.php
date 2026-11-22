<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchLedgerEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchLedgerEntry>
 */
class BranchLedgerEntryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'request_key' => bin2hex(random_bytes(16)),
            'entry_date' => now()->toDateString(),
            'kind' => 'income',
            'amount_minor' => 10000,
            'currency' => 'TRY',
            'description' => fake()->sentence(4),
        ];
    }
}
