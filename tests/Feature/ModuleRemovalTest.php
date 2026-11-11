<?php

namespace Tests\Feature;

use App\Livewire\Xpress\WarehouseTable;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class ModuleRemovalTest extends TestCase
{
    public function test_deleted_importers_are_not_exposed_as_routes(): void
    {
        $this->assertFalse(Route::has('xpress.users.import'));
        $this->assertFalse(Route::has('xpress.warehouse.import-submit'));
        $this->assertFalse(Route::has('xpress.warehouse.bulk-delete'));
    }

    public function test_navigation_does_not_link_to_removed_modules(): void
    {
        $this->actingAsUser()->get(route('xpress.users.index'))
            ->assertOk()
            ->assertDontSee(url('xpress/pos'), false)
            ->assertDontSee(url('xpress/item'), false)
            ->assertDontSee(url('xpress/intransaction'), false)
            ->assertDontSee(url('xpress/outtransaction'), false)
            ->assertDontSee(url('xpress/balance-report'), false)
            ->assertDontSee(url('xpress/marn-integration'), false)
            ->assertDontSee(url('agreement/contract'), false)
            ->assertSee(route('xpress.warehouse.index'), false);
    }

    public function test_warehouse_create_page_does_not_require_deleted_lookup_models(): void
    {
        $this->actingAsUser()->get(route('xpress.warehouse.create'))
            ->assertOk()
            ->assertSee('Warehouse Name')
            ->assertDontSee('name="client_id"', false)
            ->assertDontSee('name="city_id"', false);
    }

    public function test_warehouse_table_can_query_without_deleted_lookup_tables(): void
    {
        $table = new WarehouseTable;
        $table->searchBox = 'test';

        $this->assertCount(0, $table->datasource()->get());
    }

    public function test_warehouse_can_be_created_without_removed_client_and_city_fields(): void
    {
        $this->actingAsUser()->post(route('xpress.warehouse.store'), [
            'user_id' => $this->user->id,
            'warehouse_code' => 'WH-001',
            'warehouse_name' => 'Main Warehouse',
            'active_status' => 'Active',
        ])->assertRedirect(route('xpress.warehouse.index'));

        $this->assertDatabaseHas('warehouses', [
            'warehouse_code' => 'WH-001',
            'warehouse_name' => 'Main Warehouse',
            'user_id' => $this->user->id,
            'client_id' => null,
            'city_id' => null,
        ]);
    }

    public function test_database_seeder_only_runs_remaining_seeders(): void
    {
        DB::table('users')->delete();

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'dr@gmb.com']);
    }
}
