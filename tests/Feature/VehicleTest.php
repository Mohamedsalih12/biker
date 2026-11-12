<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Support\Facades\Gate;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    public function test_admin_can_create_a_vehicle_with_defaults_and_only_validated_fields(): void
    {
        $this->actingAsUser()->post('/admin/vehicles', ['registration_number' => 'EV-100', 'id' => 9999])
            ->assertRedirect();

        $vehicle = Vehicle::query()->sole();
        $this->assertSame('EV-100', $vehicle->registration_number);
        $this->assertSame('available', $vehicle->status);
        $this->assertNull($vehicle->brand);
        $this->assertNotSame(9999, $vehicle->id);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'vehicle', 'event' => 'created', 'subject_id' => $vehicle->id]);
    }

    public function test_admin_can_view_forms_update_show_and_delete_a_vehicle(): void
    {
        $vehicle = Vehicle::factory()->create(['registration_number' => 'EV-200']);
        $this->actingAsUser()->get('/admin/vehicles')->assertSeeLivewire('admin.vehicle-table');
        $this->get('/admin/vehicles/create')->assertSee('Add Vehicle');
        $this->get("/admin/vehicles/{$vehicle->id}/edit")->assertSee('EV-200');
        $this->put("/admin/vehicles/{$vehicle->id}", [
            'registration_number' => 'EV-200', 'brand' => 'Updated brand', 'status' => 'maintenance',
            'purchase_cost' => '1234.50', 'odometer_reading' => 321, 'purchase_date' => '2026-01-15',
            'insurance_expires_at' => '2027-01-15',
        ])->assertRedirect('/admin/vehicles');

        $vehicle->refresh();
        $this->assertSame('Updated brand', $vehicle->brand);
        $this->assertSame('1234.50', $vehicle->purchase_cost);
        $this->assertSame(321, $vehicle->odometer_reading);
        $this->assertSame('2026-01-15', $vehicle->purchase_date->toDateString());
        $this->get("/admin/vehicles/{$vehicle->id}")->assertSee('Updated brand')->assertSee('2027-01-15');
        $this->delete("/admin/vehicles/{$vehicle->id}/delete")->assertRedirect('/admin/vehicles');

        $this->assertModelMissing($vehicle);
        $this->assertDatabaseHas('deleted_models', ['model' => Vehicle::class, 'key' => (string) $vehicle->id]);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'vehicle', 'event' => 'updated', 'subject_id' => $vehicle->id]);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'vehicle', 'event' => 'deleted', 'subject_id' => $vehicle->id]);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'vehicle', 'event' => 'view']);
    }

    #[DataProvider('invalidValues')]
    public function test_invalid_vehicle_input_is_rejected(string $field, mixed $value): void
    {
        $this->actingAsUser()->post('/admin/vehicles', array_replace(['registration_number' => 'EV-300'], [$field => $value]))
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('vehicles', 0);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'vehicle', 'event' => 'created']);
    }

    public static function invalidValues(): array
    {
        return [
            'registration required' => ['registration_number', ''],
            'registration length' => ['registration_number', str_repeat('A', 256)],
            'brand length' => ['brand', str_repeat('A', 256)],
            'model type' => ['model', ['invalid']],
            'chassis length' => ['chassis_number', str_repeat('A', 256)],
            'battery length' => ['battery_serial_number', str_repeat('A', 256)],
            'policy length' => ['insurance_policy_number', str_repeat('A', 256)],
            'status value' => ['status', 'unknown'],
            'status null' => ['status', null],
            'odometer negative' => ['odometer_reading', -1],
            'odometer fractional' => ['odometer_reading', 1.5],
            'cost negative' => ['purchase_cost', -0.01],
            'cost overflow' => ['purchase_cost', '10000000000.00'],
            'cost precision' => ['purchase_cost', '1.001'],
            'purchase date' => ['purchase_date', 'not-a-date'],
            'insurance date' => ['insurance_expires_at', '2026-02-30'],
        ];
    }

    public function test_registration_must_be_unique_on_create_and_update(): void
    {
        $existing = Vehicle::factory()->create(['registration_number' => 'EV-400']);
        $other = Vehicle::factory()->create();

        $this->actingAsUser()->post('/admin/vehicles', ['registration_number' => $existing->registration_number])
            ->assertSessionHasErrors('registration_number');
        $this->put("/admin/vehicles/{$other->id}", ['registration_number' => $existing->registration_number])
            ->assertSessionHasErrors('registration_number');

        $this->assertSame($other->registration_number, $other->fresh()->registration_number);
    }

    #[DataProvider('roles')]
    public function test_vehicle_abilities_are_admin_only(string $role, bool $allowed): void
    {
        $actor = User::factory()->create(['role' => $role]);
        $vehicle = Vehicle::factory()->create();

        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export'] as $ability) {
            $this->assertSame($allowed, Gate::forUser($actor)->allows($ability, $vehicle), $ability);
        }
        $this->assertSame($allowed, Gate::forUser($actor)->allows('manage-vehicles'));
    }

    public static function roles(): array
    {
        return ['admin' => ['admin', true], 'staff' => ['staff', false], 'customer' => ['customer', false]];
    }

    public function test_non_admin_cannot_access_any_vehicle_endpoint_or_change_records(): void
    {
        $vehicle = Vehicle::factory()->create();
        $this->actingAs($this->otherUser);

        foreach (['/admin/vehicles', '/admin/vehicles/create', "/admin/vehicles/{$vehicle->id}", "/admin/vehicles/{$vehicle->id}/edit"] as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post('/admin/vehicles', ['registration_number' => 'DENIED'])->assertForbidden();
        $this->put("/admin/vehicles/{$vehicle->id}", ['registration_number' => 'DENIED'])->assertForbidden();
        $this->delete("/admin/vehicles/{$vehicle->id}/delete")->assertForbidden();

        $this->assertModelExists($vehicle);
        $this->assertDatabaseMissing('vehicles', ['registration_number' => 'DENIED']);
    }

    public function test_guest_is_redirected_and_missing_vehicle_returns_not_found(): void
    {
        $this->get('/admin/vehicles')->assertRedirect('/login');
        $this->actingAsUser()->get('/admin/vehicles/999999')->assertNotFound();
    }

    public function test_cancelled_deletion_does_not_report_success(): void
    {
        $vehicle = Vehicle::factory()->create();
        Vehicle::deleting(fn (Vehicle $record): bool => false);

        $this->actingAsUser()->delete("/admin/vehicles/{$vehicle->id}/delete")->assertSessionHasErrors('vehicle');

        $this->assertModelExists($vehicle);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'vehicle', 'event' => 'deleted']);
    }
}
