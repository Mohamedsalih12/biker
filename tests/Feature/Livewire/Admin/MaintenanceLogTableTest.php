<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\MaintenanceLogTable;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MaintenanceLogTableTest extends TestCase
{
    public function test_grid_combines_search_vendor_status_and_date_filters(): void
    {
        $vehicle = Vehicle::factory()->create(['registration_number' => 'SERVICE-001']);
        MaintenanceLog::factory()->for($vehicle)->create(['service_description' => 'Brake inspection', 'vendor_name' => 'vendor_2', 'status' => 'completed', 'delivered_date' => '2026-11-16']);
        MaintenanceLog::factory()->create(['service_description' => 'Brake OTHER', 'vendor_name' => 'vendor_1', 'status' => 'completed', 'delivered_date' => '2026-11-16']);

        Livewire::actingAs($this->user)->test(MaintenanceLogTable::class)
            ->set('filters.select.maintenance_logs.status', 'completed')
            ->set('filters.select.maintenance_logs.vendor_name', 'vendor_2')
            ->set('filters.input_text.maintenance_logs.delivered_date', '2026-11-16')
            ->set('filters.input_text_options.maintenance_logs.delivered_date', 'is')
            ->set('search', 'Brake')->assertSee('SERVICE-001')->assertDontSee('Brake OTHER')
            ->set('search', 'SERVICE-001')->assertSee('Brake inspection');
    }

    public function test_grid_sorts_paginates_and_escapes_descriptions(): void
    {
        MaintenanceLog::factory()->create(['service_description' => '<script>alert(1)</script>']);
        MaintenanceLog::factory()->count(16)->sequence(fn ($sequence): array => ['service_description' => sprintf('GRID-%02d', $sequence->index)])->create();

        Livewire::actingAs($this->user)->test(MaintenanceLogTable::class)
            ->assertSee('<script>alert(1)</script>')->assertDontSee('<script>alert(1)</script>', false)
            ->set('search', 'GRID-')->call('sortBy', 'service_description', 'desc')
            ->assertSeeInOrder(['GRID-15', 'GRID-14'])->assertDontSee('GRID-00')
            ->call('gotoPage', 2)->assertSee('GRID-00')->assertDontSee('GRID-15');
    }

    public function test_guest_and_non_admin_cannot_mount_grid(): void
    {
        Livewire::test(MaintenanceLogTable::class)->assertForbidden();
        foreach (['staff', 'customer'] as $role) {
            Livewire::actingAs(User::factory()->create(['role' => $role]))->test(MaintenanceLogTable::class)->assertForbidden();
        }
    }

    #[DataProvider('protectedActions')]
    public function test_actions_recheck_authorization_after_role_changes(string $action): void
    {
        $log = MaintenanceLog::factory()->create();
        $grid = Livewire::actingAs($this->user)->test(MaintenanceLogTable::class)->set('checkboxValues', [$log->id]);
        $this->user->forceFill(['role' => 'staff'])->save();

        $grid->call($action)->assertForbidden();
        $this->assertModelExists($log);
    }

    public static function protectedActions(): array
    {
        return ['export' => ['exportToXLS'], 'delete' => ['bulkDelete']];
    }

    public function test_bulk_delete_archives_only_selected_rows_and_logs_count(): void
    {
        $logs = MaintenanceLog::factory()->count(2)->create();
        $kept = MaintenanceLog::factory()->create();

        Livewire::actingAs($this->user)->test(MaintenanceLogTable::class)->set('checkboxValues', $logs->modelKeys())
            ->dispatch('bulkDelete')->assertRedirect('/admin/maintenance-logs');

        foreach ($logs as $log) {
            $this->assertModelMissing($log);
            $this->assertDatabaseHas('deleted_models', ['model' => MaintenanceLog::class, 'key' => (string) $log->id]);
        }
        $this->assertModelExists($kept);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'maintenance_log', 'event' => 'bulk_delete']);
    }

    #[DataProvider('invalidSelections')]
    public function test_invalid_selection_does_not_delete_rows(array $selection): void
    {
        $log = MaintenanceLog::factory()->create();

        Livewire::actingAs($this->user)->test(MaintenanceLogTable::class)->set('checkboxValues', $selection)
            ->call('bulkDelete')->assertHasErrors();

        $this->assertModelExists($log);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'maintenance_log', 'event' => 'bulk_delete']);
    }

    public static function invalidSelections(): array
    {
        return ['empty' => [[]], 'missing' => [[999999]], 'malformed' => [['invalid']], 'duplicate' => [[1, 1]], 'oversized' => [range(1, 101)]];
    }

    public function test_foreign_key_failure_rolls_back_bulk_delete_and_logs(): void
    {
        $this->createForeignKeyFixture();
        $first = MaintenanceLog::factory()->create();
        $linked = MaintenanceLog::factory()->create();
        DB::table('maintenance_delete_links')->insert(['maintenance_log_id' => $linked->id]);

        Livewire::actingAs($this->user)->test(MaintenanceLogTable::class)->set('checkboxValues', [$first->id, $linked->id])
            ->call('bulkDelete')->assertHasErrors('ids');

        $this->assertModelExists($first);
        $this->assertModelExists($linked);
        $this->assertDatabaseMissing('deleted_models', ['model' => MaintenanceLog::class]);
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'maintenance_log', 'event' => 'deleted']);
    }

    /**
     * Create the fixture table that restricts maintenance log deletions.
     *
     * No production table references maintenance logs, so the test provides
     * its own table. MySQL DDL implicitly commits any open transaction, which
     * would destroy the transaction RefreshDatabase keeps open on the default
     * connection and break the nested savepoint rollback under test. The
     * fixture is therefore built through a dedicated connection to the same
     * database, leaving the default connection's transaction intact. SQLite
     * supports transactional DDL and uses the default connection directly.
     */
    private function createForeignKeyFixture(): void
    {
        $define = static function (Blueprint $table): void {
            $table->id();
            $table->foreignId('maintenance_log_id')->constrained('maintenance_logs')->restrictOnDelete();
        };

        if (DB::connection()->getDriverName() === 'mysql') {
            $default = config('database.default');
            config(['database.connections.fixture_ddl' => config("database.connections.{$default}")]);
            Schema::connection('fixture_ddl')->dropIfExists('maintenance_delete_links');
            Schema::connection('fixture_ddl')->create('maintenance_delete_links', $define);
            DB::connection('fixture_ddl')->disconnect();

            return;
        }

        Schema::dropIfExists('maintenance_delete_links');
        Schema::create('maintenance_delete_links', $define);
    }

    public function test_export_respects_filters_neutralizes_formulas_and_logs_success(): void
    {
        $vehicle = Vehicle::factory()->create(['registration_number' => '=VEHICLE()']);
        MaintenanceLog::factory()->for($vehicle)->create(['service_description' => '=HYPERLINK("bad")', 'status' => 'completed', 'cost' => '123.45']);
        MaintenanceLog::factory()->create(['service_description' => 'EXCLUDED', 'status' => 'scheduled']);
        $grid = Livewire::actingAs($this->user)->test(MaintenanceLogTable::class)->set('filters.select.maintenance_logs.status', 'completed');

        $response = $grid->instance()->exportToXLS();
        $sheet = IOFactory::load($response->getFile()->getPathname())->getActiveSheet();
        try {
            $rows = $sheet->toArray();
            $this->assertSame(2, count($rows));
            $this->assertContains('Service Description', $rows[0]);
            $this->assertContains("'=HYPERLINK(\"bad\")", $rows[1]);
            $this->assertContains("'=VEHICLE()", $rows[1]);
            $this->assertDatabaseHas('activity_log', ['log_name' => 'maintenance_log', 'event' => 'export']);
        } finally {
            @unlink($response->getFile()->getPathname());
        }
    }

    public function test_selected_export_contains_only_selected_rows(): void
    {
        $selected = MaintenanceLog::factory()->create(['service_description' => 'SELECTED']);
        MaintenanceLog::factory()->create(['service_description' => 'UNSELECTED']);
        $grid = Livewire::actingAs($this->user)->test(MaintenanceLogTable::class)->set('checkboxValues', [$selected->id]);

        $rows = $grid->instance()->prepareToExport(true);

        $this->assertSame([$selected->id], $rows->pluck('id')->all());
    }

    public function test_empty_export_reports_error_without_success_log(): void
    {
        Livewire::actingAs($this->user)->test(MaintenanceLogTable::class)->call('exportToXLS')->assertHasErrors('export');
        $this->assertDatabaseMissing('activity_log', ['log_name' => 'maintenance_log', 'event' => 'export']);
    }
}
