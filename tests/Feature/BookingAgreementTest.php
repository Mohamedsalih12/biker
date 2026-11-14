<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookingAgreementTest extends TestCase
{
    #[DataProvider('rentalTypes')]
    public function test_agreement_draft_displays_customer_vehicle_and_booking_terms(string $rentalType, string $feeLabel, string $unit): void
    {
        $this->travelTo('2026-11-14 10:00:00');
        $customer = User::factory()->create([
            'role' => 'customer', 'name' => 'Agreement Renter', 'aadhaar' => '123456789012',
            'address' => '12 Rental Street', 'phone' => '9876543210',
        ]);
        $vehicle = Vehicle::factory()->create([
            'brand' => 'Ather', 'model' => '450X', 'registration_number' => 'TN49AB1234', 'odometer_reading' => 999,
        ]);
        $booking = Booking::factory()->for($customer, 'customer')->for($vehicle)->create([
            'user_id' => $this->user->id, 'rental_type' => $rentalType, 'rate_per_unit' => '2500.50',
            'start_date' => '2026-11-01', 'end_date' => '2026-12-01', 'start_odometer' => 100,
            'created_at' => '2026-10-20 10:00:00',
        ]);

        $this->actingAsUser()->get("/admin/bookings/{$booking->id}/agreement")
            ->assertViewIs('admin.agreement.agreement')
            ->assertSeeText('Electric Vehicle Rental Agreement')
            ->assertSeeText('Draft')
            ->assertSeeText($booking->booking_code)
            ->assertSeeText('14-Nov-2026')
            ->assertSeeText('Agreement Renter')->assertSeeText('123456789012')
            ->assertSeeText('12 Rental Street')->assertSeeText('9876543210')
            ->assertSeeText('Ather - 450X')->assertSeeText('TN49AB1234')
            ->assertSeeText('100 km')->assertDontSeeText('999 km')
            ->assertSeeText('From 01-Nov-2026 to 01-Dec-2026')
            ->assertSeeText($feeLabel)->assertSeeText("₹2,500.50 / {$unit}")
            ->assertSeeText('Mohamed Salih')->assertSeeText('Prohibited Inter-District Travel')
            ->assertSeeText('LESSOR (Owner Signature)')->assertSeeText('LESSEE (Renter Signature)')
            ->assertDontSeeText($this->user->name);
    }

    public static function rentalTypes(): array
    {
        return [
            'daily' => ['daily', 'Daily Rental Fee:', 'day'],
            'weekly' => ['weekly', 'Weekly Rental Fee:', 'week'],
            'monthly' => ['monthly', 'Monthly Rental Fee:', 'month'],
        ];
    }

    public function test_agreement_escapes_customer_and_vehicle_details(): void
    {
        $customer = User::factory()->create([
            'role' => 'customer', 'name' => '<script>name</script>', 'address' => '<script>address</script>',
            'phone' => '<b>phone</b>', 'aadhaar' => '<b>id</b>',
        ]);
        $vehicle = Vehicle::factory()->create([
            'brand' => '<b>brand</b>', 'model' => '<b>model</b>', 'registration_number' => '<b>registration</b>',
        ]);
        $booking = Booking::factory()->for($customer, 'customer')->for($vehicle)->create();

        $response = $this->actingAsUser()->get("/admin/bookings/{$booking->id}/agreement");

        foreach ([$customer->name, $customer->address, $customer->phone, $customer->aadhaar, $vehicle->brand, $vehicle->model, $vehicle->registration_number] as $value) {
            $response->assertSee($value)->assertDontSee($value, false);
        }
    }

    #[DataProvider('restrictedRoles')]
    public function test_non_admin_cannot_view_agreement_drafts(string $role): void
    {
        $booking = Booking::factory()->create();
        $actor = User::factory()->create(['role' => $role]);

        $this->actingAs($actor)->get("/admin/bookings/{$booking->id}/agreement")->assertForbidden();
    }

    public static function restrictedRoles(): array
    {
        return ['staff' => ['staff'], 'customer' => ['customer']];
    }

    public function test_guest_must_sign_in_to_view_an_agreement_draft(): void
    {
        $booking = Booking::factory()->create();

        $this->get("/admin/bookings/{$booking->id}/agreement")->assertRedirect('/login');
    }

    public function test_missing_booking_agreement_returns_not_found(): void
    {
        $this->actingAsUser()->get('/admin/bookings/999999/agreement')->assertNotFound();
    }
}
