<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Payment> */
class PaymentFactory extends Factory
{
    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'customer_id' => fn (array $attributes): int => Booking::query()->findOrFail($attributes['booking_id'])->customer_id,
            'vehicle_id' => fn (array $attributes): int => Booking::query()->findOrFail($attributes['booking_id'])->vehicle_id,
            'user_id' => User::factory()->admin(),
            'payment_date' => today()->toDateString(),
            'amount_received' => fake()->randomFloat(2, 1, 9999),
            'payment_method' => fake()->randomElement(Payment::METHODS),
            'notes' => null,
        ];
    }
}
