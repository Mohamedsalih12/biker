<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_code' => Booking::nextBookingCode(),
            'customer_id' => User::factory()->state(['role' => 'customer']),
            'vehicle_id' => Vehicle::factory(),
            'user_id' => User::factory()->admin(),
            'rental_type' => 'monthly', 'rate_per_unit' => '2500.00',
            'start_date' => '2026-11-01', 'end_date' => '2026-12-01',
            'start_odometer' => 100, 'status' => 'pending',
        ];
    }
}
