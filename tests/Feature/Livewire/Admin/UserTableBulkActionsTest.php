<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\UserTable;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserTableBulkActionsTest extends TestCase
{
    public function test_grid_displays_selection_bulk_delete_and_xlsx_controls(): void
    {
        $this->actingAsUser()->get(route('xpress.users.index'))->assertSee('Bulk delete');

        Livewire::actingAs($this->user)->test(UserTable::class)
            ->assertSet('checkbox', true)
            ->assertSee('XLSX')
            ->assertSee('wire:click.prevent="exportToXLS(true)"', false);
    }

    public function test_bulk_delete_removes_selected_users_and_logs_the_committed_batch(): void
    {
        $second = User::factory()->create();

        Livewire::actingAs($this->user)->test(UserTable::class)
            ->set('checkboxValues', [(string) $this->otherUser->id, (string) $second->id])
            ->dispatch('bulkDelete')
            ->assertRedirect(route('xpress.users.index'));

        $this->assertModelMissing($this->otherUser);
        $this->assertModelMissing($second);
        $this->assertModelExists($this->user);
        $this->assertDatabaseHas('activity_log', ['log_name' => 'user', 'event' => 'bulk_delete']);
        $this->assertDatabaseHas('deleted_models', ['model' => User::class, 'key' => $second->id]);
    }

    #[DataProvider('invalidSelections')]
    public function test_bulk_delete_rejects_invalid_selections(array $ids): void
    {
        Livewire::actingAs($this->user)->test(UserTable::class)
            ->set('checkboxValues', $ids)
            ->call('bulkDelete')
            ->assertHasErrors();

        $this->assertModelExists($this->otherUser);
        $this->assertDatabaseMissing('activity_log', ['event' => 'bulk_delete']);
    }

    public static function invalidSelections(): array
    {
        return [
            'empty' => [[]],
            'non-integer' => [['invalid']],
            'missing user' => [[999999]],
            'duplicate' => [[2, 2]],
            'too many' => [range(1, 101)],
        ];
    }

    public function test_bulk_delete_rejects_entire_batch_containing_the_current_user(): void
    {
        Livewire::actingAs($this->user)->test(UserTable::class)
            ->set('checkboxValues', [$this->user->id, $this->otherUser->id])
            ->call('bulkDelete')
            ->assertHasErrors(['ids'])
            ->assertSee('You cannot delete your own account.');

        $this->assertModelExists($this->user);
        $this->assertModelExists($this->otherUser);
        $this->assertDatabaseMissing('activity_log', ['event' => 'bulk_delete']);
    }

    public function test_bulk_delete_preserves_the_last_active_admin(): void
    {
        $this->user->forceFill(['active_status' => false])->save();
        $lastAdmin = User::factory()->admin()->create();

        Livewire::actingAs($this->user)->test(UserTable::class)
            ->set('checkboxValues', [$lastAdmin->id, $this->otherUser->id])
            ->call('bulkDelete')
            ->assertHasErrors(['ids']);

        $this->assertModelExists($lastAdmin);
        $this->assertModelExists($this->otherUser);
    }

    public function test_bulk_delete_can_remove_an_admin_when_an_active_admin_remains(): void
    {
        $target = User::factory()->admin()->create();

        Livewire::actingAs($this->user)->test(UserTable::class)
            ->set('checkboxValues', [$target->id])
            ->call('bulkDelete')
            ->assertRedirect(route('xpress.users.index'));

        $this->assertModelMissing($target);
        $this->assertModelExists($this->user);
    }

    public function test_foreign_key_restriction_rolls_back_the_whole_batch(): void
    {
        $linked = User::factory()->create();
        Booking::factory()->for($linked, 'customer')->create();

        Livewire::actingAs($this->user)->test(UserTable::class)
            ->set('checkboxValues', [$this->otherUser->id, $linked->id])
            ->call('bulkDelete')
            ->assertHasErrors(['ids']);

        $this->assertModelExists($this->otherUser);
        $this->assertModelExists($linked);
        $this->assertDatabaseMissing('activity_log', ['event' => 'bulk_delete']);
        $this->assertDatabaseMissing('deleted_models', ['model' => User::class, 'key' => $this->otherUser->id]);
    }

    #[DataProvider('protectedActions')]
    public function test_privilege_loss_blocks_bulk_delete_and_export(string $role, string $action): void
    {
        $grid = Livewire::actingAs($this->user)->test(UserTable::class)
            ->set('checkboxValues', [$this->otherUser->id]);
        $this->user->forceFill(['role' => $role])->save();

        $grid->call($action)->assertForbidden();

        $this->assertModelExists($this->otherUser);
        $this->assertDatabaseMissing('activity_log', ['event' => 'bulk_delete']);
        $this->assertDatabaseMissing('activity_log', ['event' => 'export']);
    }

    public static function protectedActions(): array
    {
        return [
            'staff delete' => ['staff', 'bulkDelete'],
            'customer delete' => ['customer', 'bulkDelete'],
            'staff export' => ['staff', 'exportToXLS'],
            'customer export' => ['customer', 'exportToXLS'],
        ];
    }

    public function test_xlsx_export_includes_all_matching_rows_not_just_the_current_page(): void
    {
        $matches = User::factory()->count(16)->create(['name' => 'Matching staff', 'role' => 'staff']);
        User::factory()->create(['name' => 'Matching customer', 'role' => 'customer']);
        User::factory()->create(['name' => 'Inactive staff', 'role' => 'staff', 'active_status' => false]);

        $grid = Livewire::actingAs($this->user)->test(UserTable::class)
            ->set('search', 'Matching')
            ->set('filters.select.role', 'staff')
            ->set('filters.select.active_status', '1')
            ->call('exportToXLS')
            ->assertFileDownloaded('users.xlsx');

        $rows = $this->downloadedRows($grid->effects['download']['content']);
        $this->assertCount(17, $rows);
        $this->assertSame(array_map('strval', $matches->modelKeys()), array_column(array_slice($rows, 1), 0));
        $log = DB::table('activity_log')->where('event', 'export')->first();
        $this->assertSame(16, json_decode($log->properties, true)['count']);
    }

    public function test_selected_export_intersects_selected_ids_with_current_filters(): void
    {
        $target = User::factory()->create(['role' => 'staff']);
        User::factory()->create(['role' => 'staff']);

        $grid = Livewire::actingAs($this->user)->test(UserTable::class)
            ->set('filters.select.role', 'staff')
            ->set('checkboxValues', [$target->id, $this->otherUser->id])
            ->call('exportToXLS', true)
            ->assertFileDownloaded('users.xlsx');

        $rows = $this->downloadedRows($grid->effects['download']['content']);
        $this->assertCount(2, $rows);
        $this->assertSame((string) $target->id, $rows[1][0]);
        $log = DB::table('activity_log')->where('event', 'export')->first();
        $this->assertSame(1, json_decode($log->properties, true)['count']);
    }

    public function test_selected_export_requires_a_nonempty_valid_selection(): void
    {
        Livewire::actingAs($this->user)->test(UserTable::class)
            ->call('exportToXLS', true)
            ->assertHasErrors(['ids']);

        $this->assertDatabaseMissing('activity_log', ['event' => 'export']);
    }

    public function test_export_with_no_matching_rows_shows_an_error_instead_of_downloading_a_blank_file(): void
    {
        Livewire::actingAs($this->user)->test(UserTable::class)
            ->set('search', 'no-matching-users-123456789')
            ->call('exportToXLS')
            ->assertHasErrors(['export'])
            ->assertSee('No users match the current search or selection.')
            ->assertNoFileDownloaded();

        $this->assertDatabaseMissing('activity_log', ['event' => 'export']);
    }

    private function downloadedRows(string $content): array
    {
        $path = tempnam(sys_get_temp_dir(), 'users-xlsx-');

        try {
            file_put_contents($path, base64_decode($content));
            $workbook = IOFactory::load($path);
            $rows = $workbook->getActiveSheet()->toArray();
            $workbook->disconnectWorksheets();

            return $rows;
        } finally {
            unlink($path);
        }
    }
}
