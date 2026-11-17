<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaintenanceLogTest extends TestCase
{
    #[DataProvider('blockingBookingStatuses')]
    public function test_creation_rejects_vehicle_with_reserved_or_taken_booking(string $bookingStatus): void
    {
        $vehicle = Vehicle::factory()->create(['odometer_reading' => 12345]);
        $booking = Booking::factory()->for($vehicle)->create(['booking_status' => $bookingStatus]);
        Booking::factory()->for($vehicle)->create(['booking_status' => 'returned']);

        $this->actingAsUser()->from('/admin/maintenance-logs/create')
            ->post('/admin/maintenance-logs', $this->payload($vehicle))
            ->assertRedirect('/admin/maintenance-logs/create')
            ->assertSessionHasErrors(['vehicle_id' => 'Cannot create a maintenance log for a vehicle with a reserved or taken booking.']);

        $this->assertDatabaseCount('maintenance_logs', 0);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'maintenance_log', 'event' => 'created']);
        $this->assertSame($bookingStatus, $booking->fresh()->booking_status);
        $this->assertSame(12345, $vehicle->fresh()->odometer_reading);
    }

    public static function blockingBookingStatuses(): array
    {
        return ['reserved' => ['reserved'], 'taken' => ['taken']];
    }

    #[DataProvider('nonBlockingBookingStatuses')]
    public function test_creation_accepts_vehicle_with_only_returned_or_cancelled_bookings(string $bookingStatus): void
    {
        $vehicle = Vehicle::factory()->create();
        Booking::factory()->for($vehicle)->create(['booking_status' => $bookingStatus]);
        Booking::factory()->create(['booking_status' => 'taken']);

        $this->actingAsUser()->post('/admin/maintenance-logs', $this->payload($vehicle))
            ->assertRedirect('/admin/maintenance-logs')->assertSessionHasNoErrors();

        $this->assertDatabaseHas('maintenance_logs', ['vehicle_id' => $vehicle->id]);
    }

    public static function nonBlockingBookingStatuses(): array
    {
        return ['returned' => ['returned'], 'cancelled' => ['cancelled']];
    }

    public function test_admin_creates_log_with_server_owned_creator_and_odometer(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 16)->setTime(12, 0));
        $vehicle = Vehicle::factory()->create(['odometer_reading' => 12345]);
        $payload = $this->payload($vehicle);
        unset($payload['delivered_date'], $payload['status']);

        $this->actingAsUser()->post('/admin/maintenance-logs', $payload + [
            'user_id' => $this->otherUser->id, 'odometer_reading' => 999,
        ])->assertRedirect('/admin/maintenance-logs');

        $log = MaintenanceLog::query()->sole();
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertSame(12345, $log->odometer_reading);
        $this->assertSame('2026-11-16', $log->delivered_date->format('Y-m-d'));
        $this->assertSame('in_progress', $log->status);
        $this->assertSame('123.45', $log->cost);
        $this->assertTrue($log->vehicle->is($vehicle));
        $this->assertTrue($log->user->is($this->user));
        $this->assertTrue($vehicle->maintenanceLogs->contains($log));
        $this->assertTrue($this->user->createdMaintenanceLogs->contains($log));
        $this->assertDatabaseHas('activity_log', ['log_name' => 'maintenance_log', 'event' => 'created', 'subject_id' => $log->id]);
        $this->assertSame('maintenance', $vehicle->fresh()->status);
    }

    public function test_forms_show_defaults_and_index_uses_powergrid(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 16)->setTime(12, 0));

        $this->actingAsUser()->get('/admin/maintenance-logs/create')
            ->assertSee('value="2026-11-16"', false)->assertSee('in_progress')->assertSee('Select a vehicle');
        $this->get('/admin/maintenance-logs')->assertSeeLivewire('admin.maintenance-log-table');
    }

    public function test_update_preserves_snapshot_and_creator_until_vehicle_changes(): void
    {
        $vehicle = Vehicle::factory()->create(['odometer_reading' => 200]);
        $log = MaintenanceLog::factory()->for($vehicle)->create(['odometer_reading' => 100, 'user_id' => $this->otherUser->id]);

        $this->actingAsUser()->get("/admin/maintenance-logs/{$log->id}/edit")->assertSee('Edit Maintenance Log');
        $this->put("/admin/maintenance-logs/{$log->id}", $this->payload($vehicle) + [
            'odometer_reading' => 999, 'user_id' => $this->user->id,
        ])->assertRedirect('/admin/maintenance-logs');
        $this->assertSame(100, $log->fresh()->odometer_reading);
        $this->assertSame($this->otherUser->id, $log->fresh()->user_id);

        $other = Vehicle::factory()->create(['odometer_reading' => 500]);
        $this->put("/admin/maintenance-logs/{$log->id}", $this->payload($other))->assertRedirect('/admin/maintenance-logs');
        $this->assertSame(500, $log->fresh()->odometer_reading);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'maintenance_log', 'event' => 'updated', 'subject_id' => $log->id]);
    }

    public function test_nullable_cost_and_unknown_odometer_remain_null(): void
    {
        $vehicle = Vehicle::factory()->create(['odometer_reading' => null]);
        $this->actingAsUser()->post('/admin/maintenance-logs', array_replace($this->payload($vehicle), ['cost' => null]))
            ->assertRedirect('/admin/maintenance-logs');

        $log = MaintenanceLog::query()->sole();
        $this->assertNull($log->cost);
        $this->assertNull($log->odometer_reading);
    }

    public function test_show_escapes_description_and_delete_archives_and_audits(): void
    {
        $log = MaintenanceLog::factory()->create(['service_description' => '<script>alert(1)</script>']);

        $this->actingAsUser()->get("/admin/maintenance-logs/{$log->id}")
            ->assertSee($log->service_description)->assertDontSee($log->service_description, false);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'maintenance_log', 'event' => 'view', 'subject_id' => $log->id]);
        $this->delete("/admin/maintenance-logs/{$log->id}/delete")->assertRedirect('/admin/maintenance-logs');

        $this->assertModelMissing($log);
        $this->assertDatabaseHas('deleted_models', ['model' => MaintenanceLog::class, 'key' => (string) $log->id]);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'maintenance_log', 'event' => 'deleted', 'subject_id' => $log->id]);
    }

    public function test_missing_required_values_do_not_create_a_log(): void
    {
        $this->actingAsUser()->post('/admin/maintenance-logs', [])->assertSessionHasErrors([
            'vehicle_id', 'service_description', 'expected_completion_date', 'vendor_name',
        ]);
        $this->assertDatabaseCount('maintenance_logs', 0);
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_values_are_rejected_without_success_logs(string $field, mixed $value): void
    {
        $this->actingAsUser()->post('/admin/maintenance-logs', array_replace($this->payload(Vehicle::factory()->create()), [$field => $value]))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('maintenance_logs', 0);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'maintenance_log', 'event' => 'created']);
    }

    public static function invalidValues(): array
    {
        return [
            'missing vehicle' => ['vehicle_id', 999999],
            'malformed vehicle' => ['vehicle_id', []],
            'empty description' => ['service_description', ''],
            'oversized description' => ['service_description', str_repeat('a', 10001)],
            'invalid delivery' => ['delivered_date', '2026-02-30'],
            'blank delivery' => ['delivered_date', ''],
            'invalid completion' => ['expected_completion_date', 'tomorrow'],
            'completion before delivery' => ['expected_completion_date', '2026-11-15'],
            'negative cost' => ['cost', -1],
            'imprecise cost' => ['cost', '1.234'],
            'oversized cost' => ['cost', '10000000000'],
            'invalid vendor' => ['vendor_name', 'vendor_5'],
            'malformed vendor' => ['vendor_name', []],
            'invalid status' => ['status', 'unknown'],
        ];
    }

    public function test_vehicle_lookup_returns_odometer_and_paginates_search_results(): void
    {
        Vehicle::factory()->count(21)->sequence(fn ($sequence): array => [
            'registration_number' => sprintf('SERVICE-%02d', $sequence->index), 'odometer_reading' => 456,
        ])->create();
        Vehicle::factory()->create(['registration_number' => 'UNRELATED']);

        $response = $this->actingAsUser()->getJson('/admin/maintenance-logs/vehicles?q=SERVICE-')
            ->assertJsonPath('pagination.more', true)->assertJsonCount(20, 'results')
            ->assertJsonPath('results.0.odometer_reading', 456);
        $this->assertStringNotContainsString('UNRELATED', $response->getContent());
        $this->getJson('/admin/maintenance-logs/vehicles?q=SERVICE-&page=2')
            ->assertJsonCount(1, 'results')->assertJsonPath('pagination.more', false);
        $this->getJson('/admin/maintenance-logs/vehicles?page=0')->assertUnprocessable();
    }

    public function test_guests_and_non_admins_cannot_access_maintenance_actions(): void
    {
        $log = MaintenanceLog::factory()->create();
        $this->get('/admin/maintenance-logs')->assertRedirect('/login');
        foreach (['staff', 'customer'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            foreach (['manage-maintenance-logs', 'viewAny', 'view', 'create', 'update', 'delete', 'export'] as $ability) {
                $this->assertFalse(Gate::forUser($user)->allows($ability, $log));
            }
            $this->actingAs($user)->withSession(['otp_verified' => true]);
            foreach (['', '/create', '/vehicles', "/{$log->id}", "/{$log->id}/edit"] as $suffix) {
                $this->get('/admin/maintenance-logs'.$suffix)->assertForbidden();
            }
            $this->post('/admin/maintenance-logs', [])->assertForbidden();
            $this->put("/admin/maintenance-logs/{$log->id}", [])->assertForbidden();
            $this->delete("/admin/maintenance-logs/{$log->id}/delete")->assertForbidden();
        }
        $this->assertModelExists($log);
    }

    public function test_referenced_vehicle_cannot_be_deleted(): void
    {
        $log = MaintenanceLog::factory()->create();
        $this->expectException(QueryException::class);

        DB::table('vehicles')->where('id', $log->vehicle_id)->delete();
    }

    private function payload(Vehicle $vehicle): array
    {
        return [
            'vehicle_id' => $vehicle->id, 'service_description' => 'Brake service',
            'delivered_date' => '2026-11-16', 'expected_completion_date' => '2026-11-20',
            'cost' => '123.45', 'vendor_name' => 'vendor_1', 'status' => 'in_progress',
        ];
    }
}
