<?php

namespace Database\Factories;

use App\Models\Doctor;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    protected $model = Doctor::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'person_id' => Person::factory(),
            'license_number' => fake()->unique()->numerify('DR########'),
            'specialty' => fake()->randomElement([
                'Diş Hekimi',
                'Saç Ekimi Uzmanı',
                'Dermatoloji',
                'Estetik Hekimi',
            ]),
            'bio' => fake()->paragraph(),
            'profile_photo' => null,
            'status' => 'active',
            'is_public' => true,
        ];
    }
}