<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Artisan;
use PHPUnit\Framework\Attributes\TestWith;
use RuntimeException;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class ExpireUnpaidBookingsTest extends TestCase
{
    #[TestWith(['2026-11-17 09:59:59', 'reserved'])]
    #[TestWith(['2026-11-17 10:00:00', 'cancelled'])]
    #[TestWith(['2026-11-18 10:00:00', 'cancelled'])]
    public function test_unpaid_online_reservations_expire_at_the_payment_deadline(string $time, string $expectedStatus): void
    {
        $booking = $this->onlineReservation();
        $this->travelTo($time);

        $this->artisan('bookings:expire-unpaid')->assertSuccessful();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'booking_status' => $expectedStatus]);
    }

    #[TestWith(['0.01'])]
    #[TestWith(['2500.00'])]
    public function test_any_recorded_payment_protects_the_reservation(string $amount): void
    {
        $booking = $this->onlineReservation();
        Payment::factory()->for($booking)->create(['amount_received' => $amount]);
        $this->travelTo('2026-11-18 10:00:00');

        $this->artisan('bookings:expire-unpaid')->assertSuccessful();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'booking_status' => 'reserved']);
        $this->assertSame(0, Activity::query()->forSubject($booking)->where('event', 'cancelled')->count());
    }

    public function test_another_bookings_receipt_does_not_prevent_expiry(): void
    {
        $booking = $this->onlineReservation();
        $otherBooking = Booking::factory()->create(['customer_id' => $booking->customer_id]);
        Payment::factory()->for($otherBooking)->create(['amount_received' => '2500.00']);
        $this->travelTo('2026-11-18 10:00:00');

        $this->artisan('bookings:expire-unpaid')->assertSuccessful();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'booking_status' => 'cancelled']);
    }

    #[TestWith(['taken'])]
    #[TestWith(['returned'])]
    #[TestWith(['cancelled'])]
    public function test_bookings_no_longer_reserved_are_untouched(string $status): void
    {
        $booking = $this->onlineReservation();
        $booking->update(['booking_status' => $status]);
        $this->travelTo('2026-11-18 10:00:00');

        $this->artisan('bookings:expire-unpaid')->assertSuccessful();

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'booking_status' => $status]);
        $this->assertSame(0, Activity::query()->forSubject($booking)->where('event', 'cancelled')->count());
    }

    public function test_existing_and_admin_reservations_without_a_deadline_are_untouched(): void
    {
        $this->travelTo('2026-11-16 10:00:00');
        $booking = Booking::factory()->create(['booking_status' => 'reserved', 'payment_status' => 'pending']);
        $this->travelTo('2026-12-16 10:00:00');

        $this->artisan('bookings:expire-unpaid')->assertSuccessful();

        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id, 'booking_status' => 'reserved', 'payment_due_at' => null,
        ]);
    }

    public function test_expiry_is_logged_once_and_releases_the_bike_for_booking(): void
    {
        $booking = $this->onlineReservation();
        $this->travelTo('2026-11-17 10:00:00');

        $this->artisan('bookings:expire-unpaid')->expectsOutputToContain('Cancelled 1 expired unpaid reservation(s).')
            ->assertSuccessful();
        $this->artisan('bookings:expire-unpaid')->expectsOutputToContain('Cancelled 0 expired unpaid reservation(s).')
            ->assertSuccessful();

        $activity = Activity::query()->forSubject($booking)->where('event', 'cancelled')->sole();
        $this->assertSame('payment_deadline_exceeded', $activity->getProperty('reason'));
        $this->assertSame('2026-11-17 10:00:00', $activity->getProperty('payment_due_at'));
        $this->assertNull($activity->causer_id);
        $this->get("/bikes/{$booking->vehicle_id}/book")->assertSee('Confirm reservation');
        $this->assertSame('available', Vehicle::query()->withCurrentStatus()->findOrFail($booking->vehicle_id)->current_status);
    }

    public function test_a_payment_recorded_after_candidate_selection_is_rechecked(): void
    {
        $booking = $this->onlineReservation();
        $this->travelTo('2026-11-17 10:00:00');
        $paymentRecorded = false;
        Booking::retrieved(function (Booking $candidate) use ($booking, &$paymentRecorded): void {
            if ($candidate->id === $booking->id && ! $paymentRecorded) {
                $paymentRecorded = true;
                Payment::factory()->for($booking)->create(['amount_received' => '100.00']);
            }
        });

        $this->artisan('bookings:expire-unpaid')->assertSuccessful();

        $this->assertTrue($paymentRecorded);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'booking_status' => 'reserved']);
    }

    public function test_failed_cancellation_does_not_claim_success_or_log_a_cancellation(): void
    {
        $booking = $this->onlineReservation();
        $this->travelTo('2026-11-17 10:00:00');
        Booking::saving(fn (Booking $candidate): ?bool => $candidate->booking_status === 'cancelled' ? false : null);

        try {
            $this->expectException(RuntimeException::class);
            Artisan::call('bookings:expire-unpaid');
        } finally {
            $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'booking_status' => 'reserved']);
            $this->assertSame(0, Activity::query()->forSubject($booking)->where('event', 'cancelled')->count());
        }
    }

    #[TestWith(['booking_status', 'taken'])]
    #[TestWith(['payment_due_at', '2026-11-18 10:00:00'])]
    #[TestWith(['payment_due_at', null])]
    public function test_status_and_deadline_changes_after_selection_are_rechecked(string $field, ?string $value): void
    {
        $booking = $this->onlineReservation();
        $this->travelTo('2026-11-17 10:00:00');
        $bookingChanged = false;
        Booking::retrieved(function (Booking $candidate) use ($booking, $field, $value, &$bookingChanged): void {
            if ($candidate->id === $booking->id && ! $bookingChanged) {
                $bookingChanged = true;
                Booking::query()->whereKey($booking->id)->update([$field => $value]);
            }
        });

        $this->artisan('bookings:expire-unpaid')->assertSuccessful();

        $this->assertTrue($bookingChanged);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id, 'booking_status' => 'reserved', $field => $value,
        ]);
        $this->assertSame(0, Activity::query()->forSubject($booking)->where('event', 'cancelled')->count());
    }

    private function onlineReservation(): Booking
    {
        $this->travelTo('2026-11-16 10:00:00');
        $vehicle = Vehicle::factory()->create(['rate_per_unit' => 2500]);
        $this->post("/bikes/{$vehicle->id}/book", [
            'name' => 'Reservation Customer', 'phone' => '+919876543210', 'aadhaar' => '123456789012',
            'start_date' => '2026-11-18', 'end_date' => '2026-12-18',
        ])->assertSessionHasNoErrors()->assertRedirect(route('frontend.index'));

        return Booking::query()->sole();
    }
}
