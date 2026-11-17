<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\VehicleTable;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Models\Vehicle;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class VehicleTableTest extends TestCase
{
    public function test_grid_totals_payments_for_each_vehicle_and_shows_zero_without_payments(): void
    {
        $vehicle = Vehicle::factory()->create(['registration_number' => 'EARNINGS-PAID']);
        $unpaid = Vehicle::factory()->create(['registration_number' => 'EARNINGS-UNPAID']);
        Payment::factory()->count(2)->sequence(
            ['amount_received' => '220.25'],
            ['amount_received' => '40.50'],
        )->create(['vehicle_id' => $vehicle->id]);

        Livewire::actingAs($this->user)->test(VehicleTable::class)
            ->set('search', 'EARNINGS-')
            ->call('sortBy', 'total_amount_earned', 'desc')
            ->assertSee('Total Amount Earned')
            ->assertSeeInOrder([$vehicle->registration_number, '261', $unpaid->registration_number, '0']);
    }

    public function test_select_all_selects_the_visible_page_and_can_clear_selection(): void
    {
        $vehicles = Vehicle::factory()->count(2)->create();

        Livewire::actingAs($this->user)->test(VehicleTable::class)
            ->set('checkboxAll', true)->call('selectCheckboxAll')
            ->assertSet('checkboxValues', array_map('strval', $vehicles->modelKeys()))
            ->set('checkboxAll', false)->call('selectCheckboxAll')
            ->assertSet('checkboxValues', []);
    }

    public function test_grid_searches_identifiers_and_combines_status_filter(): void
    {
        $vehicle = Vehicle::factory()->create(['registration_number' => 'MATCH-100', 'brand' => 'SearchBrand', 'model' => 'SearchModel', 'chassis_number' => 'CHASSIS-100', 'battery_serial_number' => 'BATTERY-100', 'insurance_policy_number' => 'POLICY-100', 'status' => 'maintenance']);
        Vehicle::factory()->create(['registration_number' => 'OTHER-100', 'brand' => 'SearchBrand', 'status' => 'rented']);
        $grid = Livewire::actingAs($this->user)->test(VehicleTable::class)->set('filters.select.status', 'maintenance');

        foreach (['MATCH-100', 'SearchBrand', 'SearchModel', 'CHASSIS-100', 'BATTERY-100', 'POLICY-100'] as $search) {
            $grid->set('search', $search)->assertSee($vehicle->registration_number)->assertDontSee('OTHER-100');
        }
    }

    public function test_grid_sorts_paginates_and_escapes_user_content(): void
    {
        $vehicle = Vehicle::factory()->create(['registration_number' => '<script>alert(1)</script>']);
        Vehicle::factory()->count(16)->sequence(fn ($sequence): array => ['registration_number' => sprintf('GRID-%02d', $sequence->index)])->create();

        Livewire::actingAs($this->user)->test(VehicleTable::class)
            ->assertSee($vehicle->registration_number)->assertDontSee($vehicle->registration_number, false)
            ->set('search', 'GRID-')->call('sortBy', 'registration_number', 'desc')
            ->assertSeeInOrder(['GRID-15', 'GRID-14'])->assertDontSee('GRID-00')
            ->call('gotoPage', 2)->assertSee('GRID-00')->assertDontSee('GRID-15');
    }

    public function test_guest_and_non_admin_cannot_mount_the_grid(): void
    {
        Livewire::test(VehicleTable::class)->assertForbidden();
        foreach (['staff', 'customer'] as $role) {
            Livewire::actingAs(User::factory()->create(['role' => $role]))->test(VehicleTable::class)->assertForbidden();
        }
    }

    #[DataProvider('protectedActions')]
    public function test_grid_rechecks_authorization_before_export_and_bulk_delete(string $action): void
    {
        $vehicle = Vehicle::factory()->create();
        $grid = Livewire::actingAs($this->user)->test(VehicleTable::class)->set('checkboxValues', [$vehicle->id]);
        $this->user->forceFill(['role' => 'staff'])->save();

        $grid->call($action)->assertForbidden();

        $this->assertModelExists($vehicle);
    }

    public static function protectedActions(): array
    {
        return ['export' => ['exportToXLS'], 'delete' => ['bulkDelete']];
    }

    public function test_grid_renders_date_only_values_without_midnight_timestamps(): void
    {
        Vehicle::factory()->create(['purchase_date' => '2026-02-15', 'insurance_expires_at' => '2027-02-15']);

        Livewire::actingAs($this->user)->test(VehicleTable::class)
            ->assertSee('2026-02-15')->assertSee('2027-02-15')
            ->assertDontSee('2026-02-15 00:00:00')->assertDontSee('2027-02-15 00:00:00');
    }

    public function test_bulk_delete_removes_only_selection_and_records_audit_and_deleted_models(): void
    {
        $vehicles = Vehicle::factory()->count(2)->create();
        $kept = Vehicle::factory()->create();

        Livewire::actingAs($this->user)->test(VehicleTable::class)->set('checkboxValues', $vehicles->modelKeys())
            ->dispatch('bulkDelete')->assertRedirect('/admin/vehicles');

        foreach ($vehicles as $vehicle) {
            $this->assertModelMissing($vehicle);
            $this->assertDatabaseHas('deleted_models', ['model' => Vehicle::class, 'key' => (string) $vehicle->id]);
        }
        $this->assertModelExists($kept);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'vehicle', 'event' => 'bulk_delete']);
    }

    #[DataProvider('invalidSelections')]
    public function test_invalid_bulk_selection_does_not_delete_anything(array $selection): void
    {
        $vehicle = Vehicle::factory()->create();

        Livewire::actingAs($this->user)->test(VehicleTable::class)->set('checkboxValues', $selection)
            ->call('bulkDelete')->assertHasErrors();

        $this->assertModelExists($vehicle);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'vehicle', 'event' => 'bulk_delete']);
    }

    public static function invalidSelections(): array
    {
        return ['empty' => [[]], 'missing' => [[999999]], 'malformed' => [['invalid']], 'duplicates' => [[1, 1]], 'too many' => [range(1, 101)]];
    }

    public function test_foreign_key_failure_rolls_back_the_whole_batch_and_its_logs(): void
    {
        $first = Vehicle::factory()->create();
        $linked = Vehicle::factory()->create();
        Booking::factory()->for($linked)->create();

        Livewire::actingAs($this->user)->test(VehicleTable::class)->set('checkboxValues', [$first->id, $linked->id])
            ->call('bulkDelete')->assertHasErrors('ids');

        $this->assertModelExists($first);
        $this->assertModelExists($linked);
        $this->assertDatabaseMissing('deleted_models', ['model' => Vehicle::class, 'key' => (string) $first->id]);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'vehicle', 'event' => 'deleted']);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'vehicle', 'event' => 'bulk_delete']);
    }

    public function test_cancelled_deletion_rolls_back_the_whole_batch_and_its_logs(): void
    {
        $first = Vehicle::factory()->create();
        $cancelled = Vehicle::factory()->create();
        Vehicle::deleting(fn (Vehicle $vehicle): ?bool => $vehicle->id === $cancelled->id ? false : null);

        Livewire::actingAs($this->user)->test(VehicleTable::class)->set('checkboxValues', [$first->id, $cancelled->id])
            ->call('bulkDelete')->assertHasErrors('ids');

        $this->assertModelExists($first);
        $this->assertModelExists($cancelled);
        $this->assertDatabaseMissing('deleted_models', ['model' => Vehicle::class, 'key' => (string) $first->id]);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'vehicle', 'event' => 'deleted']);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'vehicle', 'event' => 'bulk_delete']);
    }

    public function test_xlsx_exports_matching_rows_beyond_the_current_page(): void
    {
        Vehicle::factory()->count(16)->create(['brand' => 'Export all']);
        $grid = Livewire::actingAs($this->user)->test(VehicleTable::class)->set('search', 'Export all')
            ->call('exportToXLS')->assertFileDownloaded('vehicles.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'vehicles-xlsx-');

        try {
            file_put_contents($path, base64_decode($grid->effects['download']['content']));
            $workbook = IOFactory::load($path);
            $this->assertSame(17, $workbook->getActiveSheet()->getHighestDataRow());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
    }

    public function test_xlsx_respects_search_filter_and_selection_and_neutralizes_formulas(): void
    {
        $vehicle = Vehicle::factory()->create(['registration_number' => 'EXPORT-100', 'brand' => '=HYPERLINK("https://example.com")', 'chassis_number' => '001234567890123456789', 'status' => 'maintenance']);
        Vehicle::factory()->create(['registration_number' => 'EXPORT-200', 'status' => 'rented']);
        Vehicle::factory()->create(['registration_number' => 'OTHER-300', 'status' => 'maintenance']);
        $grid = Livewire::actingAs($this->user)->test(VehicleTable::class)
            ->set('search', 'EXPORT-')->set('filters.select.status', 'maintenance')
            ->call('exportToXLS')->assertFileDownloaded('vehicles.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'vehicles-xlsx-');

        try {
            file_put_contents($path, base64_decode($grid->effects['download']['content']));
            $workbook = IOFactory::load($path);
            $sheet = $workbook->getActiveSheet();
            $this->assertSame(2, $sheet->getHighestDataRow());
            $this->assertSame('Registration Number', $sheet->getCell('B1')->getFormattedValue());
            $this->assertSame('EXPORT-100', $sheet->getCell('B2')->getFormattedValue());
            $this->assertSame("'".$vehicle->brand, $sheet->getCell('C2')->getFormattedValue());
            $this->assertNotSame(DataType::TYPE_FORMULA, $sheet->getCell('C2')->getDataType());
            $this->assertSame('001234567890123456789', $sheet->getCell('E2')->getFormattedValue());
            $this->assertSame('O', $sheet->getHighestDataColumn());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }

        $grid->set('search', '')->set('filters.select.status', '')->set('checkboxValues', [$vehicle->id])
            ->call('exportToXLS', true)->assertFileDownloaded('vehicles.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'vehicles-selected-');
        try {
            file_put_contents($path, base64_decode($grid->effects['download']['content']));
            $workbook = IOFactory::load($path);
            $this->assertSame(2, $workbook->getActiveSheet()->getHighestDataRow());
            $this->assertSame('EXPORT-100', $workbook->getActiveSheet()->getCell('B2')->getFormattedValue());
            $workbook->disconnectWorksheets();
        } finally {
            unlink($path);
        }
        $this->assertDatabaseHas('activity_log', ['log_name' => 'vehicle', 'event' => 'export']);
    }

    public function test_empty_export_and_empty_export_selection_report_validation_errors(): void
    {
        $grid = Livewire::actingAs($this->user)->test(VehicleTable::class);

        $grid->call('exportToXLS')->assertHasErrors('export');
        $grid->call('exportToXLS', true)->assertHasErrors('ids');

        $this->assertDatabaseMissing('activity_log', ['log_name' => 'vehicle', 'event' => 'export']);
    }
}
