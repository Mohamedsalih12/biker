<?php

namespace Tests\Feature;

use App\Livewire\Admin\VehicleTable;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VehicleMaintenanceStatusTest extends TestCase
{
    public function test_vehicle_stays_under_maintenance_until_the_last_in_progress_log_is_closed(): void
    {
        $vehicle = Vehicle::factory()->create();
        $first = MaintenanceLog::factory()->for($vehicle)->create();
        $second = MaintenanceLog::factory()->for($vehicle)->create();

        $this->assertSame('maintenance', $vehicle->fresh()->status);
        $first->update(['status' => 'completed']);
        $this->assertSame('maintenance', $vehicle->fresh()->status);
        $second->update(['status' => 'scheduled']);
        $this->assertSame('available', $vehicle->fresh()->status);
        $first->update(['status' => 'in_progress']);
        $this->assertSame('maintenance', $vehicle->fresh()->status);
        $first->delete();
        $this->assertSame('available', $vehicle->fresh()->status);
        $second->delete();
        $this->assertSame('available', $vehicle->fresh()->status);
    }

    public function test_reassigning_a_log_synchronizes_both_vehicles(): void
    {
        $original = Vehicle::factory()->create();
        $replacement = Vehicle::factory()->create();
        $log = MaintenanceLog::factory()->for($original)->create();

        $log->update(['vehicle_id' => $replacement->id]);

        $this->assertSame('available', $original->fresh()->status);
        $this->assertSame('maintenance', $replacement->fresh()->status);
    }

    public function test_cancelling_a_log_is_valid_and_releases_the_vehicle(): void
    {
        $log = MaintenanceLog::factory()->create();

        $this->actingAsUser()->put(route('xpress.maintenance-logs.update', $log), [
            'vehicle_id' => $log->vehicle_id, 'service_description' => 'Cancelled service',
            'delivered_date' => '2026-11-16', 'expected_completion_date' => '2026-11-20',
            'vendor_name' => 'vendor_1', 'status' => 'cancelled',
        ])->assertSessionHasNoErrors()->assertRedirect(route('xpress.maintenance-logs.index'));

        $this->assertDatabaseHas('maintenance_logs', ['id' => $log->id, 'status' => 'cancelled']);
        $this->assertSame('available', $log->vehicle->status);
    }

    #[DataProvider('alertDates')]
    public function test_alerts_follow_calendar_date_boundaries(string $status, string $delivered, string $completion, ?string $severity): void
    {
        $this->travelTo('2026-11-17 23:59:59');
        $log = MaintenanceLog::factory()->create([
            'status' => $status, 'delivered_date' => $delivered, 'expected_completion_date' => $completion,
        ]);

        $alert = $log->vehicle->maintenanceAlert();

        $this->assertSame($severity, $alert['severity'] ?? null);
    }

    public static function alertDates(): array
    {
        return [
            'scheduled yesterday' => ['scheduled', '2026-11-16', '2026-11-21', 'danger'],
            'scheduled today' => ['scheduled', '2026-11-17', '2026-11-21', 'warning'],
            'scheduled tomorrow' => ['scheduled', '2026-11-18', '2026-11-21', 'warning'],
            'scheduled in three days' => ['scheduled', '2026-11-20', '2026-11-21', 'warning'],
            'scheduled in four days' => ['scheduled', '2026-11-21', '2026-11-22', null],
            'in progress overdue' => ['in_progress', '2026-11-14', '2026-11-16', 'danger'],
            'completion today is not overdue' => ['in_progress', '2026-11-14', '2026-11-17', null],
            'completion tomorrow' => ['in_progress', '2026-11-14', '2026-11-18', null],
            'completed dates ignored' => ['completed', '2026-11-14', '2026-11-16', null],
            'cancelled dates ignored' => ['cancelled', '2026-11-14', '2026-11-16', null],
        ];
    }

    public function test_danger_overrides_warning_across_logs_and_updates_when_the_date_changes(): void
    {
        $this->travelTo('2026-11-17 12:00:00');
        $vehicle = Vehicle::factory()->create();
        MaintenanceLog::factory()->for($vehicle)->create(['status' => 'scheduled', 'delivered_date' => '2026-11-18']);
        $overdue = MaintenanceLog::factory()->for($vehicle)->create(['status' => 'scheduled', 'delivered_date' => '2026-11-16']);

        $alert = $vehicle->maintenanceAlert();

        $this->assertSame('danger', $alert['severity']);
        $this->assertSame($overdue->id, $alert['log_id']);
        $overdue->update(['status' => 'completed']);
        $vehicle->unsetRelation('maintenanceLogs');
        $this->assertSame('warning', $vehicle->maintenanceAlert()['severity']);
        $this->travelTo('2026-11-19 00:00:00');
        $this->assertSame('danger', $vehicle->maintenanceAlert()['severity']);
    }

    public function test_grid_shows_highlight_rules_and_alert_details_without_changing_identifiers(): void
    {
        $this->travelTo('2026-11-17');
        $vehicle = Vehicle::factory()->create(['registration_number' => 'SERVICE-ALERT']);
        MaintenanceLog::factory()->for($vehicle)->create(['status' => 'scheduled', 'delivered_date' => '2026-11-19']);

        Livewire::actingAs($this->user)->test(VehicleTable::class)->set('search', 'SERVICE-ALERT')
            ->assertSee('SERVICE-ALERT')->assertSee('Warning')->assertSee('Maintenance due in 2 days')
            ->assertSee('vehicle-maintenance-warning', false)->assertSee('Available');
    }

    public function test_grid_danger_includes_a_link_and_no_warning_when_both_conditions_apply(): void
    {
        $this->travelTo('2026-11-17');
        $vehicle = Vehicle::factory()->create(['registration_number' => '<unsafe>']);
        MaintenanceLog::factory()->for($vehicle)->create(['status' => 'scheduled', 'delivered_date' => '2026-11-19']);
        $overdue = MaintenanceLog::factory()->for($vehicle)->create(['expected_completion_date' => '2026-11-16']);

        Livewire::actingAs($this->user)->test(VehicleTable::class)
            ->assertSee('Danger')->assertDontSee('Warning')->assertSee('Completion overdue by 1 day')
            ->assertSee('Under Maintenance')->assertSee('vehicle-maintenance-danger', false)
            ->assertSee(route('xpress.maintenance-logs.show', $overdue), false)
            ->assertSee('&lt;unsafe&gt;', false)->assertDontSee('<unsafe>', false);
    }

    public function test_grid_loads_alerts_in_a_bounded_number_of_queries_and_leaves_clear_vehicles_unmarked(): void
    {
        $clear = Vehicle::factory()->create();
        MaintenanceLog::factory()->count(3)->create();
        $this->actingAsUser();
        DB::enableQueryLog();
        DB::flushQueryLog();

        $vehicles = app(VehicleTable::class)->datasource()->get();
        $alerts = $vehicles->map(fn (Vehicle $vehicle): ?array => $vehicle->maintenanceAlert());

        $this->assertCount(2, DB::getQueryLog());
        $this->assertNull($alerts[$vehicles->search(fn (Vehicle $vehicle): bool => $vehicle->id === $clear->id)]);
        DB::disableQueryLog();
    }

    public function test_new_admin_booking_is_rejected_during_maintenance(): void
    {
        $vehicle = Vehicle::factory()->create();
        MaintenanceLog::factory()->for($vehicle)->create();
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser()->post(route('xpress.bookings.store'), [
            'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'rate_per_unit' => 2500,
            'start_date' => '2026-11-17', 'end_date' => '2026-12-17', 'start_odometer' => 100,
        ])->assertSessionHasErrors(['vehicle_id' => 'This vehicle is under maintenance and cannot be booked.']);

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_vehicle_edit_cannot_mark_an_in_progress_vehicle_available(): void
    {
        $vehicle = Vehicle::factory()->create();
        MaintenanceLog::factory()->for($vehicle)->create();

        $this->actingAsUser()->put(route('xpress.vehicles.update', $vehicle), [
            'registration_number' => $vehicle->registration_number, 'status' => 'available',
        ])->assertSessionHasNoErrors();

        $this->assertSame('maintenance', $vehicle->fresh()->status);
    }

    public function test_warning_does_not_block_a_new_booking(): void
    {
        $this->travelTo('2026-11-17');
        config(['services.telegram.bot_token' => null]);
        $vehicle = Vehicle::factory()->create();
        MaintenanceLog::factory()->for($vehicle)->create(['status' => 'scheduled', 'delivered_date' => '2026-11-18']);
        $customer = User::factory()->create(['role' => 'customer']);

        $this->actingAsUser()->post(route('xpress.bookings.store'), [
            'customer_id' => $customer->id, 'vehicle_id' => $vehicle->id, 'rate_per_unit' => 2500,
            'start_date' => '2026-11-17', 'end_date' => '2026-12-17', 'start_odometer' => 100,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('bookings', ['vehicle_id' => $vehicle->id]);
    }

    public function test_public_booking_rechecks_maintenance_after_the_form_opens(): void
    {
        $this->travelTo('2026-11-17');
        $vehicle = Vehicle::factory()->create(['rate_per_unit' => 6000]);
        $this->get("/bikes/{$vehicle->id}/book")->assertSee('Confirm reservation');
        MaintenanceLog::factory()->for($vehicle)->create();
        $users = User::query()->count();

        $this->post("/bikes/{$vehicle->id}/book", [
            'name' => 'Customer', 'phone' => '9876543210', 'aadhaar' => '123456789012',
            'start_date' => '2026-11-17', 'end_date' => '2026-12-17',
        ])->assertSessionHasErrors('booking');

        $this->assertDatabaseCount('bookings', 0);
        $this->assertDatabaseCount('users', $users);
        $this->get("/bikes/{$vehicle->id}/book")->assertDontSee('Confirm reservation');
        $this->get('/')->assertSee('Not Available');
    }
}
