<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class FleetBookingTest extends TestCase
{
    public function test_book_now_opens_a_public_form_for_the_selected_bike(): void
    {
        $this->travelTo('2026-11-16');
        $vehicle = Vehicle::factory()->create(['brand' => 'Ather', 'model' => 'Rizta', 'rate_per_unit' => '6000.50']);

        $this->get('/')->assertSee('href="'.url("/bikes/{$vehicle->id}/book").'"', false)
            ->assertDontSee('Online booking is coming soon');
        $this->get("/bikes/{$vehicle->id}/book")->assertSee('Ather Rizta')->assertSee('₹6,000.50')
            ->assertSee('name="name"', false)->assertSee('name="phone"', false)
            ->assertSee('name="aadhaar"', false)->assertSee('name="start_date"', false)
            ->assertSee('name="end_date"', false)->assertSee('max="2026-11-18"', false)
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_guest_submission_saves_a_customer_and_reservation_with_server_owned_fields(): void
    {
        $this->travelTo('2026-11-16');
        $vehicle = Vehicle::factory()->create(['rate_per_unit' => 6000, 'odometer_reading' => 12345]);

        $response = $this->post("/bikes/{$vehicle->id}/book", $this->payload([
            'role' => 'admin', 'rate_per_unit' => 1, 'booking_status' => 'taken', 'payment_status' => 'paid',
            'user_id' => $this->user->id, 'customer_id' => $this->user->id, 'vehicle_id' => 999999,
            'password' => 'InjectedPassword', 'email' => 'injected@example.test',
        ]))->assertRedirect(route('frontend.index'))->assertSessionHasNoErrors();

        $customer = User::query()->where('aadhaar', '123456789012')->sole();
        $booking = Booking::query()->sole();
        $this->assertSame('Customer Name', $customer->name);
        $this->assertSame('+919876543210', $customer->phone);
        $this->assertSame('customer', $customer->role);
        $this->assertNull($customer->email);
        $this->assertNull($customer->password);
        $this->assertSame($customer->id, $booking->customer_id);
        $this->assertSame($customer->id, $booking->user_id);
        $this->assertSame($vehicle->id, $booking->vehicle_id);
        $this->assertSame('monthly', $booking->rental_type);
        $this->assertSame('6000.00', $booking->rate_per_unit);
        $this->assertSame(12345, $booking->start_odometer);
        $this->assertSame('reserved', $booking->booking_status);
        $this->assertSame('pending', $booking->payment_status);
        $this->assertSame('2026-11-18', $booking->start_date->toDateString());
        $this->assertSame('2026-12-20', $booking->end_date->toDateString());
        $this->assertGuest();
        $response->assertSessionHas('fleet_booking_code', $booking->booking_code);
        $this->get('/')->assertSee('Reservation received')->assertSee($booking->booking_code)
            ->assertSee('Not Available')->assertDontSee('123456789012');
    }

    #[TestWith(['name', ''])]
    #[TestWith(['phone', 'not-a-phone'])]
    #[TestWith(['aadhaar', '12345'])]
    #[TestWith(['aadhaar', ['123456789012']])]
    #[TestWith(['start_date', '2026-11-15'])]
    #[TestWith(['start_date', 'invalid'])]
    #[TestWith(['end_date', '2026-11-17'])]
    #[TestWith(['end_date', ''])]
    public function test_invalid_input_does_not_create_a_customer_or_booking(string $field, mixed $value): void
    {
        $this->travelTo('2026-11-16');
        $vehicle = Vehicle::factory()->create(['rate_per_unit' => 6000]);
        $userCount = User::query()->count();

        $this->from("/bikes/{$vehicle->id}/book")->post(
            "/bikes/{$vehicle->id}/book", $this->payload([$field => $value]),
        )->assertRedirect("/bikes/{$vehicle->id}/book")->assertSessionHasErrors($field)
            ->assertSessionMissing('_old_input.aadhaar');

        $this->assertDatabaseCount('users', $userCount);
        $this->assertDatabaseCount('bookings', 0);
    }

    #[TestWith(['2026-11-16', '2026-11-16'])]
    #[TestWith(['2026-11-16', '2026-11-17'])]
    #[TestWith(['2026-11-16', '2026-11-18'])]
    #[TestWith(['2026-12-30', '2027-01-01'])]
    public function test_start_dates_within_two_calendar_days_are_allowed(string $today, string $startDate): void
    {
        $this->travelTo($today);
        $vehicle = Vehicle::factory()->create(['rate_per_unit' => 6000]);

        $this->post("/bikes/{$vehicle->id}/book", $this->payload([
            'start_date' => $startDate, 'end_date' => '2027-02-01',
        ]))->assertRedirect(route('frontend.index'))->assertSessionHasNoErrors();

        $this->assertSame($startDate, Booking::query()->sole()->start_date->toDateString());
    }

    #[TestWith(['2026-11-16', '2026-11-19'])]
    #[TestWith(['2026-12-30', '2027-01-02'])]
    public function test_start_dates_beyond_two_days_are_rejected(string $today, string $startDate): void
    {
        $this->travelTo($today);
        $vehicle = Vehicle::factory()->create(['rate_per_unit' => 6000]);
        $userCount = User::query()->count();

        $this->from("/bikes/{$vehicle->id}/book")->post(
            "/bikes/{$vehicle->id}/book",
            $this->payload(['start_date' => $startDate, 'end_date' => '2027-02-01']),
        )->assertSessionHasErrors([
            'start_date' => 'The start date must be within the next 2 days.',
        ]);

        $this->assertDatabaseCount('users', $userCount);
        $this->assertDatabaseCount('bookings', 0);
    }

    #[TestWith(['123456789012'])]
    #[TestWith(['1234 5678 9012'])]
    #[TestWith(['1234-5678-9012'])]
    public function test_existing_customer_is_reused_without_creating_or_updating_a_customer(string $aadhaar): void
    {
        $this->travelTo('2026-11-16');
        $existing = User::factory()->create([
            'role' => 'customer', 'aadhaar' => $aadhaar, 'name' => 'Existing Customer', 'phone' => '9000000000',
        ]);
        $originalAttributes = $existing->fresh()->getRawOriginal();
        $vehicle = Vehicle::factory()->create(['rate_per_unit' => 6000]);
        $userCount = User::query()->count();

        $this->from("/bikes/{$vehicle->id}/book")->post(
            "/bikes/{$vehicle->id}/book", $this->payload(),
        )->assertRedirect(route('frontend.index'))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('users', $userCount);
        $booking = Booking::query()->sole();
        $this->assertSame($existing->id, $booking->customer_id);
        $this->assertSame('reserved', $booking->booking_status);
        $this->assertSame($originalAttributes, $existing->fresh()->getRawOriginal());
        $this->assertGuest();
    }

    #[TestWith(['admin', true])]
    #[TestWith(['staff', true])]
    #[TestWith(['customer', false])]
    public function test_noncustomer_or_inactive_records_cannot_be_reused(string $role, bool $active): void
    {
        $this->travelTo('2026-11-16');
        User::factory()->create(['role' => $role, 'active_status' => $active, 'aadhaar' => '123456789012']);
        $vehicle = Vehicle::factory()->create(['rate_per_unit' => 6000]);
        $userCount = User::query()->count();

        $this->from("/bikes/{$vehicle->id}/book")->post(
            "/bikes/{$vehicle->id}/book", $this->payload(),
        )->assertSessionHasErrors('aadhaar')->assertSessionMissing('_old_input.aadhaar');

        $this->assertDatabaseCount('users', $userCount);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_ambiguous_aadhaar_records_require_staff_assistance(): void
    {
        $this->travelTo('2026-11-16');
        User::factory()->count(2)->create(['role' => 'customer', 'aadhaar' => '123456789012']);
        $vehicle = Vehicle::factory()->create(['rate_per_unit' => 6000]);
        $userCount = User::query()->count();

        $this->from("/bikes/{$vehicle->id}/book")->post(
            "/bikes/{$vehicle->id}/book", $this->payload(),
        )->assertSessionHasErrors('aadhaar');

        $this->assertDatabaseCount('users', $userCount);
        $this->assertDatabaseCount('bookings', 0);
    }

    #[TestWith(['reserved'])]
    #[TestWith(['taken'])]
    public function test_a_bike_booked_after_the_form_opens_cannot_be_booked_again(string $status): void
    {
        $this->travelTo('2026-11-16');
        $vehicle = Vehicle::factory()->create(['rate_per_unit' => 6000]);
        $this->get("/bikes/{$vehicle->id}/book")->assertSee('Confirm reservation');
        Booking::factory()->for($vehicle)->create(['booking_status' => $status]);
        $userCount = User::query()->count();

        $this->from("/bikes/{$vehicle->id}/book")->post(
            "/bikes/{$vehicle->id}/book", $this->payload(),
        )->assertSessionHasErrors('booking');

        $this->assertDatabaseCount('users', $userCount);
        $this->assertDatabaseCount('bookings', 1);
        $this->get("/bikes/{$vehicle->id}/book")->assertSee('This bike is no longer available')
            ->assertDontSee('Confirm reservation');
    }

    public function test_decommissioned_bikes_cannot_be_booked_through_a_direct_url(): void
    {
        $vehicle = Vehicle::factory()->create(['status' => 'decommissioned']);
        $userCount = User::query()->count();

        $this->get("/bikes/{$vehicle->id}/book")->assertNotFound();
        $this->post("/bikes/{$vehicle->id}/book", $this->payload())->assertNotFound();

        $this->assertDatabaseCount('users', $userCount);
        $this->assertDatabaseCount('bookings', 0);
    }

    #[TestWith([null])]
    #[TestWith([0])]
    public function test_a_missing_or_zero_price_requires_staff_assistance(?int $rate): void
    {
        $this->travelTo('2026-11-16');
        $vehicle = Vehicle::factory()->create(['rate_per_unit' => $rate]);
        $userCount = User::query()->count();

        $this->from("/bikes/{$vehicle->id}/book")->post(
            "/bikes/{$vehicle->id}/book", $this->payload(),
        )->assertSessionHasErrors('booking');

        $this->assertDatabaseCount('users', $userCount);
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_a_failed_booking_save_rolls_back_the_customer_and_booking_code(): void
    {
        $this->travelTo('2026-11-16');
        $vehicle = Vehicle::factory()->create(['rate_per_unit' => 6000]);
        $userCount = User::query()->count();
        $lastCode = DB::table('booking_code_sequences')->value('last_code');
        Booking::creating(fn (Booking $booking): bool => false);

        $this->from("/bikes/{$vehicle->id}/book")->post(
            "/bikes/{$vehicle->id}/book", $this->payload(),
        )->assertSessionHasErrors('booking');

        $this->assertDatabaseCount('users', $userCount);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame($lastCode, DB::table('booking_code_sequences')->value('last_code'));
    }

    public function test_repeated_public_submissions_are_rate_limited(): void
    {
        $vehicle = Vehicle::factory()->create(['rate_per_unit' => 6000]);
        $userCount = User::query()->count();
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->postJson("/bikes/{$vehicle->id}/book", [])->assertUnprocessable();
        }

        $this->postJson("/bikes/{$vehicle->id}/book", [])->assertTooManyRequests();

        $this->assertDatabaseCount('users', $userCount);
        $this->assertDatabaseCount('bookings', 0);
    }

    /** @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Customer Name', 'phone' => '+91 98765 43210', 'aadhaar' => '1234 5678 9012',
            'start_date' => '2026-11-18', 'end_date' => '2026-12-20',
        ], $overrides);
    }
}
