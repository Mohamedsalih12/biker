<?php

namespace Database\Factories;

use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<MaintenanceLog> */
class MaintenanceLogFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'vehicle_id' => Vehicle::factory(), 'user_id' => User::factory()->admin(),
            'service_description' => fake()->sentence(), 'delivered_date' => '2026-11-16',
            'expected_completion_date' => '2026-11-20', 'cost' => '100.00',
            'odometer_reading' => fn (array $attributes): ?int => Vehicle::query()->findOrFail($attributes['vehicle_id'])->odometer_reading,
            'vendor_name' => 'vendor_1', 'status' => 'in_progress',
        ];
    }
}
