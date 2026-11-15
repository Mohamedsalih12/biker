<?php

namespace Tests\Feature;

use App\Livewire\Admin\AllBookingTable;
use App\Livewire\Admin\BookingTable;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookingWorkflowTest extends TestCase
{
    public function test_return_notes_are_appended_to_customer_notes_once_on_create_and_edit(): void
    {
        $this->otherUser->forceFill(['notes' => 'Existing customer note'])->save();
        $payload = $this->payload(Vehicle::factory()->create()) + ['return_notes' => 'First return note'];

        $this->actingAsUser()->post(route('xpress.bookings.store'), $payload)->assertSessionHasNoErrors();
        $booking = Booking::query()->sole();
        $this->assertSame("Existing customer note\nFirst return note", $this->otherUser->fresh()->notes);
        $this->put(route('xpress.bookings.update', $booking), $payload)->assertSessionHasNoErrors();
        $this->assertSame("Existing customer note\nFirst return note", $this->otherUser->fresh()->notes);
        $this->put(route('xpress.bookings.update', $booking), array_replace($payload, ['return_notes' => 'Updated note']))
            ->assertSessionHasNoErrors();
        $this->assertSame("Existing customer note\nFirst return note\nUpdated note", $this->otherUser->fresh()->notes);
        $this->put(route('xpress.bookings.update', $booking), array_replace($payload, ['return_notes' => null]))
            ->assertSessionHasNoErrors();
        $this->assertSame("Existing customer note\nFirst return note\nUpdated note", $this->otherUser->fresh()->notes);
    }

    #[DataProvider('occupiedStatuses')]
    public function test_active_vehicle_cannot_be_booked_again_but_its_booking_can_be_edited(string $status): void
    {
        $booking = Booking::factory()->create(['booking_status' => $status]);
        $payload = $this->payload($booking->vehicle);

        $this->actingAsUser()->post(route('xpress.bookings.store'), $payload)
            ->assertSessionHasErrors(['vehicle_id' => 'This vehicle already has a taken or reserved booking.']);
        $this->assertDatabaseCount('bookings', 1);
        $this->put(route('xpress.bookings.update', $booking), $payload)->assertSessionHasNoErrors();
        $this->assertSame($this->otherUser->id, $booking->fresh()->customer_id);
    }

    public static function occupiedStatuses(): array
    {
        return [['taken'], ['reserved']];
    }

    public function test_reassigning_a_booking_to_an_occupied_vehicle_is_rejected(): void
    {
        $occupied = Booking::factory()->create(['booking_status' => 'reserved']);
        $booking = Booking::factory()->create();

        $this->actingAsUser()->put(route('xpress.bookings.update', $booking), $this->payload($occupied->vehicle))
            ->assertSessionHasErrors('vehicle_id');

        $this->assertNotSame($occupied->vehicle_id, $booking->fresh()->vehicle_id);
    }

    public function test_occupied_vehicles_are_not_offered_for_new_bookings_but_current_vehicle_remains_editable(): void
    {
        $booking = Booking::factory()->create(['booking_status' => 'taken']);
        $available = Vehicle::factory()->create();

        $this->actingAsUser()->get(route('xpress.bookings.create'))
            ->assertSee($available->registration_number)->assertDontSee($booking->vehicle->registration_number);
        $this->get(route('xpress.bookings.edit', $booking))->assertSee($booking->vehicle->registration_number);
    }

    public function test_customer_note_save_failure_rolls_back_booking_creation(): void
    {
        $payload = $this->payload(Vehicle::factory()->create()) + ['return_notes' => 'Customer return note'];
        User::saving(fn (User $user): ?bool => $user->isDirty('notes') ? false : null);

        $this->actingAsUser()->post(route('xpress.bookings.store'), $payload)->assertSessionHasErrors('return_notes');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertNull($this->otherUser->fresh()->notes);
    }

    public function test_stale_booking_edit_does_not_append_the_same_return_notes_twice(): void
    {
        $booking = Booking::factory()->create(['customer_id' => $this->otherUser->id, 'return_notes' => 'Original note']);
        $staleBooking = $booking->fresh();
        $payload = $this->payload($booking->vehicle) + ['return_notes' => 'Changed note'];
        $this->actingAsUser()->put(route('xpress.bookings.update', $booking), $payload)->assertSessionHasNoErrors();
        Route::bind('booking', fn (string $id): Booking => $staleBooking);

        $this->put(route('xpress.bookings.update', $booking), $payload)->assertSessionHasNoErrors();

        $this->assertSame('Changed note', $this->otherUser->fresh()->notes);
    }

    public function test_return_notes_are_saved_for_a_customer_without_previous_notes(): void
    {
        $payload = $this->payload(Vehicle::factory()->create()) + ['return_notes' => 'First note'];

        $this->actingAsUser()->post(route('xpress.bookings.store'), $payload)->assertSessionHasNoErrors();

        $this->assertSame('First note', $this->otherUser->fresh()->notes);
        $this->assertSame('First note', Booking::query()->sole()->return_notes);
    }

    public function test_returned_and_cancelled_bookings_do_not_block_new_bookings(): void
    {
        $vehicle = Vehicle::factory()->create();
        Booking::factory()->create(['vehicle_id' => $vehicle->id, 'booking_status' => 'returned']);
        Booking::factory()->create(['vehicle_id' => $vehicle->id, 'booking_status' => 'cancelled']);

        $this->actingAsUser()->post(route('xpress.bookings.store'), $this->payload($vehicle))->assertSessionHasNoErrors();

        $this->assertDatabaseCount('bookings', 3);
    }

    #[DataProvider('rentalCharges')]
    public function test_payment_status_uses_full_rounded_rental_charge(string $type, string $start, string $end, ?string $returned, int $units): void
    {
        $booking = Booking::factory()->create([
            'rental_type' => $type, 'rate_per_unit' => '100.01', 'start_date' => $start,
            'end_date' => $end, 'actual_return_date' => $returned, 'payment_status' => 'paid',
        ]);
        Payment::factory()->for($booking)->create(['amount_received' => $units * 100.01 - 0.01]);

        $this->assertSame('unpaid', Booking::query()->withCurrentPaymentStatus()->findOrFail($booking->id)->current_payment_status);
        Payment::factory()->for($booking)->create(['amount_received' => '0.01']);
        $this->assertSame('paid', Booking::query()->withCurrentPaymentStatus()->findOrFail($booking->id)->current_payment_status);
    }

    public static function rentalCharges(): array
    {
        return [
            'same day minimum' => ['daily', '2026-11-01', '2026-11-01', null, 1],
            'daily duration' => ['daily', '2026-11-01', '2026-11-04', null, 3],
            'exact week' => ['weekly', '2026-11-01', '2026-11-08', null, 1],
            'partial week' => ['weekly', '2026-11-01', '2026-11-09', null, 2],
            'exact month' => ['monthly', '2026-11-01', '2026-12-01', null, 1],
            'partial month' => ['monthly', '2026-11-01', '2026-12-02', null, 2],
            'short calendar month' => ['monthly', '2027-01-31', '2027-02-28', null, 1],
            'actual return' => ['weekly', '2026-11-01', '2026-11-08', '2026-11-09', 2],
        ];
    }

    #[DataProvider('tables')]
    public function test_both_grids_filter_sort_and_refresh_receipt_based_status_and_link_agreements(string $table): void
    {
        $paid = Booking::factory()->create(['payment_status' => 'pending']);
        $unpaid = Booking::factory()->create(['payment_status' => 'paid']);
        $payment = Payment::factory()->for($paid)->create(['amount_received' => '2500.00']);
        Payment::factory()->for($unpaid)->create(['amount_received' => '100.00']);
        $grid = Livewire::actingAs($this->user)->test($table);

        $grid->assertSee(route('xpress.bookings.agreement', $paid), false)
            ->set('filters.select.bookings.current_payment_status', 'paid')
            ->assertSee($paid->booking_code)->assertDontSee($unpaid->booking_code);
        $grid->set('filters.select.bookings.current_payment_status', 'unpaid')
            ->assertSee($unpaid->booking_code)->assertDontSee($paid->booking_code);
        $grid->set('filters.select.bookings.current_payment_status', '')->call('sortBy', 'bookings.current_payment_status', 'asc')
            ->assertSeeInOrder([$paid->booking_code, $unpaid->booking_code]);
        $payment->delete();
        $grid->set('filters.select.bookings.current_payment_status', 'paid')->assertDontSee($paid->booking_code);
    }

    public static function tables(): array
    {
        return [[BookingTable::class], [AllBookingTable::class]];
    }

    public function test_returned_booking_leaves_active_list_only_when_receipts_cover_charge(): void
    {
        $booking = Booking::factory()->create(['booking_status' => 'returned', 'payment_status' => 'paid']);
        $grid = Livewire::actingAs($this->user)->test(BookingTable::class)->assertSee($booking->booking_code);

        Payment::factory()->for($booking)->create(['amount_received' => '2500.00']);

        $grid->call('$refresh')->assertDontSee($booking->booking_code);
        Livewire::test(AllBookingTable::class)->assertSee($booking->booking_code);
    }

    public function test_only_receipts_for_the_booking_count_and_receipt_edits_recalculate_status(): void
    {
        $booking = Booking::factory()->create();
        $otherBooking = Booking::factory()->create(['customer_id' => $booking->customer_id]);
        Payment::factory()->for($otherBooking)->create(['amount_received' => '9999.00']);
        $this->assertSame('unpaid', Booking::query()->withCurrentPaymentStatus()->findOrFail($booking->id)->current_payment_status);
        $payment = Payment::factory()->for($booking)->create(['amount_received' => '2500.01']);
        $this->assertSame('paid', Booking::query()->withCurrentPaymentStatus()->findOrFail($booking->id)->current_payment_status);

        $payment->update(['amount_received' => '2499.99']);

        $this->assertSame('unpaid', Booking::query()->withCurrentPaymentStatus()->findOrFail($booking->id)->current_payment_status);
    }

    /** @return array<string, mixed> */
    private function payload(Vehicle $vehicle): array
    {
        return [
            'customer_id' => $this->otherUser->id, 'vehicle_id' => $vehicle->id,
            'rate_per_unit' => '100.00', 'start_date' => '2026-11-01', 'end_date' => '2026-11-08',
            'start_odometer' => 100,
        ];
    }
}
