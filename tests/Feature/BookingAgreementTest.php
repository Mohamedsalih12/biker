<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookingAgreementTest extends TestCase
{
    public function test_agreement_displays_print_pdf_and_back_actions(): void
    {
        $booking = Booking::factory()->create();

        $this->actingAsUser()->get("/admin/bookings/{$booking->id}/agreement")
            ->assertSeeText('Print')->assertSee('window.print()', false)
            ->assertSeeText('Download as PDF')
            ->assertSee('href="'.url("/admin/bookings/{$booking->id}/agreement/pdf").'"', false)
            ->assertSeeText('Back')
            ->assertSee('href="'.route('xpress.bookings.index').'"', false);
    }

    public function test_agreement_downloads_a_pdf(): void
    {
        $booking = Booking::factory()->create();

        $response = $this->actingAsUser()->get("/admin/bookings/{$booking->id}/agreement/pdf")
            ->assertOk()->assertHeader('Content-Type', 'application/pdf')
            ->assertDownload("agreement-{$booking->booking_code}.pdf");

        $this->assertStringStartsWith('%PDF-', $response->getContent());
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'booking', 'subject_id' => $booking->id,
            'description' => 'Booking agreement PDF downloaded',
        ]);
    }

    public function test_pdf_template_excludes_interactive_controls_and_feedback(): void
    {
        $booking = Booking::factory()->create();
        $this->actingAsUser()->withSession(['message' => 'Payment saved successfully.']);

        $this->view('admin.agreement.agreement', [
            'booking' => $booking, 'isPdf' => true, 'errors' => new ViewErrorBag,
        ])
            ->assertSeeText($booking->booking_code)
            ->assertSeeText($booking->customer->name)
            ->assertSeeText($booking->vehicle->registration_number)
            ->assertDontSee('<button', false)->assertDontSee('<form', false)
            ->assertDontSee('<a ', false)->assertDontSeeText('Payment saved successfully.');
    }

    public function test_agreement_pdf_requires_authentication_and_admin_access(): void
    {
        $booking = Booking::factory()->create();
        $url = "/admin/bookings/{$booking->id}/agreement/pdf";

        $this->get($url)->assertRedirect('/login');
        $this->actingAs($this->otherUser)->withSession(['otp_verified' => true])->get($url)->assertForbidden();
    }

    public function test_missing_booking_pdf_returns_not_found(): void
    {
        $this->actingAsUser()->get('/admin/bookings/999999/agreement/pdf')->assertNotFound();
    }

    public function test_agreement_displays_payment_inputs_and_pay_button(): void
    {
        $booking = Booking::factory()->create();

        $this->actingAsUser()->get("/admin/bookings/{$booking->id}/agreement")
            ->assertSee('name="amount_received"', false)
            ->assertSee('name="payment_method"', false)
            ->assertSeeText('Pay')->assertSeeText('Cash')->assertSeeText('UPI')->assertSeeText('Other')
            ->assertDontSee('value="bank"', false);
    }

    public function test_agreement_payment_uses_booking_details_today_and_logged_in_user(): void
    {
        $this->travelTo('2026-11-15 12:00:00');
        $booking = Booking::factory()->create();
        $other = Booking::factory()->create();

        $this->actingAsUser()->post("/admin/bookings/{$booking->id}/agreement/payments", [
            'amount_received' => '1250.50', 'payment_method' => 'upi',
            'booking_id' => $other->id, 'customer_id' => $other->customer_id,
            'vehicle_id' => $other->vehicle_id, 'user_id' => $this->otherUser->id,
            'payment_date' => '2020-01-01',
        ])->assertRedirect("/admin/bookings/{$booking->id}/agreement")
            ->assertSessionHas('message', 'Payment saved successfully.');

        $payment = Payment::query()->sole();
        $this->assertSame($booking->id, $payment->booking_id);
        $this->assertSame($booking->customer_id, $payment->customer_id);
        $this->assertSame($booking->vehicle_id, $payment->vehicle_id);
        $this->assertSame($this->user->id, $payment->user_id);
        $this->assertSame('2026-11-15', $payment->payment_date->format('Y-m-d'));
        $this->assertSame('1250.50', $payment->amount_received);
        $this->assertSame('upi', $payment->payment_method);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'payment', 'event' => 'created', 'subject_id' => $payment->id]);
        $this->get("/admin/bookings/{$booking->id}/agreement")->assertSeeText('Payment saved successfully.');
    }

    public function test_agreement_rejects_invalid_payment_and_preserves_input(): void
    {
        $booking = Booking::factory()->create();
        $url = "/admin/bookings/{$booking->id}/agreement";

        $this->actingAsUser()->from($url)->post("{$url}/payments", [
            'amount_received' => '-1', 'payment_method' => 'bank',
        ])->assertRedirect($url)->assertSessionHasErrors(['amount_received', 'payment_method'])
            ->assertSessionHasInput('amount_received', '-1');

        $this->assertDatabaseCount('payments', 0);
        $this->get($url)->assertSee('value="-1"', false)->assertSeeText('The amount received field must be greater than 0.');
    }

    public function test_agreement_payment_requires_authentication_and_admin_access(): void
    {
        $booking = Booking::factory()->create();
        $url = "/admin/bookings/{$booking->id}/agreement/payments";

        $this->post($url, [])->assertRedirect('/login');
        $this->actingAs($this->otherUser)->withSession(['otp_verified' => true])->post($url, [])->assertForbidden();

        $this->assertDatabaseCount('payments', 0);
    }

    public function test_agreement_payment_for_missing_booking_returns_not_found(): void
    {
        $this->actingAsUser()->post('/admin/bookings/999999/agreement/payments', [
            'amount_received' => '100', 'payment_method' => 'cash',
        ])->assertNotFound();

        $this->assertDatabaseCount('payments', 0);
    }

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
