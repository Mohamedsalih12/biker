<?php

namespace Database\Factories;

use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Vehicle> */
class VehicleFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'registration_number' => fake()->unique()->bothify('EV-####-???'),
            'brand' => fake()->randomElement(['Ather', 'Ola', 'TVS']),
            'model' => fake()->bothify('Model-##'),
            'chassis_number' => fake()->unique()->bothify('CH-############'),
            'battery_serial_number' => fake()->unique()->bothify('BAT-########'),
            'odometer_reading' => fake()->numberBetween(0, 100000),
            'status' => 'available',
            'purchase_date' => '2026-01-01',
            'purchase_cost' => fake()->randomFloat(2, 10000, 200000),
            'insurance_policy_number' => fake()->bothify('INS-########'),
            'insurance_expires_at' => '2027-01-01',
        ];
    }
}
