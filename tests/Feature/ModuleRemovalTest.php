<?php

namespace Tests\Feature;

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
            ->assertDontSee(url('xpress/warehouse'), false)
            ->assertDontSee(url('xpress/pos'), false)
            ->assertDontSee(url('xpress/item'), false)
            ->assertDontSee(url('xpress/intransaction'), false)
            ->assertDontSee(url('xpress/outtransaction'), false)
            ->assertDontSee(url('xpress/balance-report'), false)
            ->assertDontSee(url('xpress/marn-integration'), false)
            ->assertDontSee(url('agreement/contract'), false);
    }

    public function test_database_seeder_only_runs_remaining_seeders(): void
    {
        DB::table('users')->delete();

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseHas('users', ['email' => 'dr@gmb.com']);
    }
}
