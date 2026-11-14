<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class BookingTest extends TestCase
{
    public function test_booking_form_includes_vehicle_odometer_readings(): void
    {
        $vehicle = Vehicle::factory()->create(['odometer_reading' => 4321]);

        $response = $this->actingAsUser()->get('/admin/bookings/create');

        $response->assertSee('value="'.$vehicle->id.'" data-odometer-reading="4321"', false);
    }

    public function test_booking_form_defaults_start_odometer_from_selected_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['odometer_reading' => 4321]);

        $response = $this->actingAsUser()->withSession(['_old_input' => ['vehicle_id' => $vehicle->id]])
            ->get('/admin/bookings/create');

        $response->assertSee('name="start_odometer" class="form-control " value="4321"', false);
    }

    public function test_booking_form_preserves_saved_and_old_start_odometer_values(): void
    {
        $booking = Booking::factory()->create(['start_odometer' => 123]);
        $booking->vehicle->update(['odometer_reading' => 4321]);

        $this->actingAsUser()->get("/admin/bookings/{$booking->id}/edit")
            ->assertSee('name="start_odometer" class="form-control " value="123"', false);
        $this->withSession(['_old_input' => ['start_odometer' => 0]])
            ->get("/admin/bookings/{$booking->id}/edit")
            ->assertSee('name="start_odometer" class="form-control " value="0"', false);
    }

    public function test_factory_creates_bookings_with_model_events_disabled(): void
    {
        Model::withoutEvents(fn () => Booking::factory()->count(2)->create());

        $this->assertSame(
            ['10000', '10001'],
            Booking::query()->orderBy('id')->pluck('booking_code')->all(),
        );
    }

    public function test_admin_can_create_bookings_with_generated_identity_defaults_and_nullable_returns(): void
    {
        $payload = $this->payload();
        $response = $this->actingAsUser()->post('/admin/bookings', $payload + ['booking_code' => 'SPOOFED', 'user_id' => $this->otherUser->id]);
        $first = Booking::query()->sole();
        $response->assertRedirect("/admin/bookings/{$first->id}/agreement");
        $this->post('/admin/bookings', $payload)->assertRedirect();

        $this->assertSame('10000', $first->booking_code);
        $this->assertSame(['10000', '10001'], Booking::query()->orderBy('id')->pluck('booking_code')->all());
        $this->assertSame(2, Booking::query()->distinct()->count('booking_code'));
        $this->assertSame($this->user->id, $first->user_id);
        $this->assertSame('monthly', $first->rental_type);
        $this->assertSame('reserved', $first->booking_status);
        $this->assertSame('2500.50', $first->rate_per_unit);
        $this->assertNull($first->actual_return_date);
        $this->assertNull($first->end_odometer);
        $this->assertNull($first->handover_notes);
        $this->assertNull($first->return_notes);
        $this->assertSame($payload['customer_id'], $first->customer->id);
        $this->assertSame($payload['vehicle_id'], $first->vehicle->id);
        $this->assertSame($this->user->id, $first->user->id);
        $this->assertTrue($first->customer->bookings->contains($first));
        $this->assertTrue($first->vehicle->bookings->contains($first));
        $this->assertTrue($this->user->createdBookings->contains($first));
        $this->assertDatabaseHas('activity_log', ['log_name' => 'booking', 'event' => 'created', 'subject_id' => $first->id]);
    }

    public function test_booking_creation_syncs_end_odometer_to_vehicle(): void
    {
        $payload = $this->payload();
        $vehicle = Vehicle::query()->findOrFail($payload['vehicle_id']);

        $response = $this->actingAsUser()->post('/admin/bookings', $payload + ['end_odometer' => 200]);
        $booking = Booking::query()->sole();

        $response->assertRedirect("/admin/bookings/{$booking->id}/agreement");
        $this->assertSame(200, $booking->end_odometer);
        $this->assertSame(200, $vehicle->fresh()->odometer_reading);
    }

    public function test_admin_can_view_update_and_delete_a_booking_without_changing_its_identity(): void
    {
        $booking = Booking::factory()->create(['user_id' => $this->user->id]);
        $code = $booking->booking_code;
        $this->actingAsUser()->get('/admin/bookings')->assertSeeLivewire('admin.booking-table');
        $this->get('/admin/bookings/create')->assertSee('Add Booking')->assertSee('Quick Add');
        $this->get("/admin/bookings/{$booking->id}/edit")->assertSee($code);
        $this->put("/admin/bookings/{$booking->id}", $this->payload() + [
            'booking_status' => 'returned', 'rental_type' => 'weekly', 'actual_return_date' => '2026-12-01',
            'end_odometer' => 200, 'handover_notes' => '<script>handover</script>', 'return_notes' => '<script>return</script>',
            'user_id' => $this->otherUser->id, 'booking_code' => 'CHANGED',
        ])->assertRedirect("/admin/bookings/{$booking->id}/agreement");

        $booking->refresh();
        $this->assertSame($code, $booking->booking_code);
        $this->assertSame($this->user->id, $booking->user_id);
        $this->assertSame('returned', $booking->booking_status);
        $this->assertSame('weekly', $booking->rental_type);
        $this->assertSame('2026-12-01', $booking->actual_return_date->toDateString());
        $this->assertSame(200, $booking->end_odometer);
        $this->assertSame(200, $booking->vehicle->fresh()->odometer_reading);
        $this->get("/admin/bookings/{$booking->id}")->assertSee('Returned')->assertSee('Paid')
            ->assertSee('<script>return</script>')->assertDontSee('<script>return</script>', false)
            ->assertSee('<script>handover</script>')->assertDontSee('<script>handover</script>', false);
        $this->delete("/admin/bookings/{$booking->id}/delete")->assertRedirect('/admin/bookings');
        $this->assertModelMissing($booking);
        $this->assertDatabaseHas('deleted_models', ['model' => Booking::class, 'key' => (string) $booking->id]);
        foreach (['view', 'updated', 'deleted'] as $event) {
            $this->assertDatabaseHas('activity_log', ['log_name' => 'booking', 'event' => $event]);
        }
    }

    public function test_booking_update_without_end_odometer_does_not_overwrite_vehicle_reading(): void
    {
        $booking = Booking::factory()->create(['end_odometer' => 150]);
        $vehicle = $booking->vehicle;
        $vehicle->update(['odometer_reading' => 900]);

        $this->actingAsUser()->put("/admin/bookings/{$booking->id}", [
            'customer_id' => $booking->customer_id, 'vehicle_id' => $booking->vehicle_id,
            'rate_per_unit' => '2500.00', 'start_date' => '2026-11-01', 'end_date' => '2026-12-01',
            'start_odometer' => 100,
        ])->assertRedirect("/admin/bookings/{$booking->id}/agreement");

        $this->assertSame(150, $booking->fresh()->end_odometer);
        $this->assertSame(900, $vehicle->fresh()->odometer_reading);
    }

    public function test_required_fields_cannot_be_omitted(): void
    {
        $this->actingAsUser()->post('/admin/bookings', [])->assertSessionHasErrors([
            'customer_id', 'vehicle_id', 'rate_per_unit', 'start_date', 'end_date', 'start_odometer',
        ]);
        $this->assertDatabaseCount('bookings', 0);
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_input_is_rejected_without_persisting_a_booking(string $field, mixed $value): void
    {
        $this->actingAsUser()->post('/admin/bookings', array_replace($this->payload(), [$field => $value]))->assertSessionHasErrors($field);
        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'booking', 'event' => 'created']);
    }

    public static function invalidValues(): array
    {
        return [
            'missing customer' => ['customer_id', 999999], 'missing vehicle' => ['vehicle_id', 999999],
            'invalid rental' => ['rental_type', 'hourly'], 'null rental' => ['rental_type', null],
            'invalid booking status' => ['booking_status', 'unknown'], 'null booking status' => ['booking_status', null],
            'invalid payment status' => ['payment_status', 'unknown'], 'null payment status' => ['payment_status', null],
            'negative rate' => ['rate_per_unit', -1], 'zero rate' => ['rate_per_unit', 0],
            'rate precision' => ['rate_per_unit', '1.001'], 'rate overflow' => ['rate_per_unit', '10000000000.00'],
            'invalid start' => ['start_date', '2026-02-30'], 'early end' => ['end_date', '2026-10-01'],
            'early return' => ['actual_return_date', '2026-10-01'], 'invalid return' => ['actual_return_date', 'not-a-date'],
            'negative start' => ['start_odometer', -1], 'fractional start' => ['start_odometer', 1.5],
            'end below start' => ['end_odometer', 99], 'fractional end' => ['end_odometer', 100.5],
            'long handover' => ['handover_notes', str_repeat('x', 10001)], 'notes type' => ['return_notes', ['bad']],
        ];
    }

    public function test_staff_and_admin_users_cannot_be_selected_as_customers(): void
    {
        $this->actingAsUser()->post('/admin/bookings', array_replace($this->payload(), ['customer_id' => $this->user->id]))
            ->assertSessionHasErrors('customer_id');
        $this->assertDatabaseCount('bookings', 0);
    }

    #[DataProvider('roles')]
    public function test_booking_abilities_are_admin_only(string $role, bool $allowed): void
    {
        $actor = User::factory()->create(['role' => $role]);
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export'] as $ability) {
            $this->assertSame($allowed, Gate::forUser($actor)->allows($ability, Booking::class), $ability);
        }
        $this->assertSame($allowed, Gate::forUser($actor)->allows('manage-bookings'));
    }

    public static function roles(): array
    {
        return ['admin' => ['admin', true], 'staff' => ['staff', false], 'customer' => ['customer', false]];
    }

    public function test_non_admin_cannot_use_any_booking_endpoint(): void
    {
        $booking = Booking::factory()->create();
        $this->actingAs($this->otherUser);
        foreach (['/admin/bookings', '/admin/bookings/create', "/admin/bookings/{$booking->id}", "/admin/bookings/{$booking->id}/edit"] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/admin/bookings', $this->payload())->assertForbidden();
        $this->put("/admin/bookings/{$booking->id}", $this->payload())->assertForbidden();
        $this->delete("/admin/bookings/{$booking->id}/delete")->assertForbidden();
        $this->assertModelExists($booking);
    }

    public function test_guest_redirects_and_missing_booking_returns_not_found(): void
    {
        $this->get('/admin/bookings')->assertRedirect('/login');
        $this->actingAsUser()->get('/admin/bookings/999999')->assertNotFound();
    }

    public function test_linked_customer_cannot_be_deleted(): void
    {
        $booking = Booking::factory()->create();
        $this->expectException(QueryException::class);
        $booking->customer->delete();
    }

    public function test_linked_vehicle_cannot_be_deleted(): void
    {
        $booking = Booking::factory()->create();
        $this->actingAsUser()->delete("/admin/vehicles/{$booking->vehicle_id}/delete")->assertSessionHasErrors('vehicle');
        $this->assertModelExists($booking->vehicle);
    }

    public function test_cancelled_deletion_does_not_report_success(): void
    {
        $booking = Booking::factory()->create();
        Booking::deleting(fn (Booking $record): bool => false);
        $this->actingAsUser()->delete("/admin/bookings/{$booking->id}/delete")->assertSessionHasErrors('booking');
        $this->assertModelExists($booking);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'booking', 'event' => 'deleted']);
    }

    #[DataProvider('notesFields')]
    public function test_notes_only_edits_are_audited_without_storing_note_contents(string $field): void
    {
        $booking = Booking::factory()->create();
        $this->actingAsUser()->put("/admin/bookings/{$booking->id}", [
            'customer_id' => $booking->customer_id, 'vehicle_id' => $booking->vehicle_id,
            'rate_per_unit' => '2500.00', 'start_date' => '2026-11-01', 'end_date' => '2026-12-01',
            'start_odometer' => 100, $field => 'Private note content',
        ])->assertRedirect("/admin/bookings/{$booking->id}/agreement");

        $this->assertSame('Private note content', $booking->fresh()->{$field});
        $activities = Activity::query()->where('log_name', 'booking')->where('event', 'updated')->get();
        $this->assertCount(1, $activities);
        $this->assertStringNotContainsString('Private note content', $activities->toJson());
    }

    public static function notesFields(): array
    {
        return ['handover' => ['handover_notes'], 'return' => ['return_notes']];
    }

    public function test_saving_unchanged_booking_does_not_log_a_misleading_update(): void
    {
        $booking = Booking::factory()->create();
        $this->travel(1)->minutes();
        $booking->save();
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'booking', 'event' => 'updated']);
    }

    #[DataProvider('linkedUserKeys')]
    public function test_linked_user_deletion_returns_a_friendly_refusal_without_deleting_or_logging_success(string $key): void
    {
        $booking = Booking::factory()->create();
        $userId = $booking->{$key};
        $this->actingAsUser()->delete("/admin/users/{$userId}/delete")->assertSessionHasErrors('user');
        $this->assertDatabaseHas('users', ['id' => $userId]);
        $this->assertDatabaseMissing('deleted_models', ['model' => User::class, 'key' => (string) $userId]);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'user', 'event' => 'deleted', 'subject_id' => $userId]);
    }

    public static function linkedUserKeys(): array
    {
        return ['customer' => ['customer_id'], 'creator' => ['user_id']];
    }

    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'customer_id' => User::factory()->create(['role' => 'customer', 'aadhaar' => '123456789012'])->id,
            'vehicle_id' => Vehicle::factory()->create()->id, 'rate_per_unit' => '2500.50',
            'start_date' => '2026-11-01', 'end_date' => '2026-12-01', 'start_odometer' => 100,
        ];
    }
}
