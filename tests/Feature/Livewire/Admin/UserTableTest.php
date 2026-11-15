<?php

namespace Tests\Feature\Livewire\Admin;

use App\Livewire\Admin\UserTable;
use App\Models\Payment;
use App\Models\User;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserTableTest extends TestCase
{
    public function test_grid_totals_payments_from_each_customer_not_payments_they_collected(): void
    {
        $customer = User::factory()->create(['name' => 'Earnings customer']);
        $unpaid = User::factory()->create(['name' => 'Earnings unpaid']);
        Payment::factory()->count(2)->sequence(
            ['amount_received' => '120.25'],
            ['amount_received' => '30.50'],
        )->create(['customer_id' => $customer->id, 'user_id' => $unpaid->id]);

        Livewire::actingAs($this->user)->test(UserTable::class)
            ->set('search', 'Earnings')
            ->call('sortBy', 'total_amount_earned', 'desc')
            ->assertSee('Total Amount Earned')
            ->assertSeeInOrder([$customer->name, '151', $unpaid->name, '0']);
    }

    public function test_user_index_renders_powergrid_and_keeps_temporary_password_notice(): void
    {
        $this->actingAsUser()->withSession(['reset_password_once' => [
            'user_name' => 'Reset user',
            'user_email' => 'reset@example.com',
            'temporary_password' => 'Temporary!123',
        ]])->get(route('xpress.users.index'))
            ->assertSeeLivewire('admin.user-table')
            ->assertSee('Temporary!123');
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_cannot_mount_user_grid(string $role): void
    {
        $actor = User::factory()->create(['role' => $role]);

        Livewire::actingAs($actor)->test(UserTable::class)->assertForbidden();
    }

    public static function nonAdminRoles(): array
    {
        return ['staff' => ['staff'], 'customer' => ['customer']];
    }

    public function test_guest_cannot_mount_user_grid(): void
    {
        Livewire::test(UserTable::class)->assertForbidden();
    }

    public function test_grid_rechecks_authorization_on_subsequent_requests(): void
    {
        $grid = Livewire::actingAs($this->user)->test(UserTable::class);
        $this->user->forceFill(['role' => 'customer'])->save();

        $grid->set('search', 'anything')->assertForbidden();
    }

    public function test_grid_searches_name_email_and_phone(): void
    {
        $target = User::factory()->create([
            'name' => 'Searchable customer',
            'email' => 'target@example.com',
            'phone' => '+94112345678',
        ]);

        $grid = Livewire::actingAs($this->user)->test(UserTable::class);

        foreach (['Searchable', 'target@example.com', '94112345678'] as $search) {
            $grid->set('search', $search)
                ->assertSee($target->name)
                ->assertDontSee($this->otherUser->name);
        }
    }

    public function test_grid_combines_role_status_and_search_filters(): void
    {
        User::factory()->create(['name' => 'Matching staff', 'role' => 'staff', 'active_status' => true]);
        User::factory()->create(['name' => 'Inactive staff', 'role' => 'staff', 'active_status' => false]);
        User::factory()->create(['name' => 'Customer account', 'role' => 'customer', 'active_status' => true]);

        Livewire::actingAs($this->user)->test(UserTable::class)
            ->set('filters.select.role', 'staff')
            ->set('filters.select.active_status', '1')
            ->assertSee('Matching staff')
            ->assertDontSee('Inactive staff')
            ->assertDontSee('Customer account')
            ->set('search', 'Customer account')
            ->assertDontSee('Customer account')
            ->assertDontSee('Matching staff');
    }

    public function test_grid_can_filter_inactive_users(): void
    {
        User::factory()->create(['name' => 'Inactive customer', 'active_status' => false]);

        Livewire::actingAs($this->user)->test(UserTable::class)
            ->set('filters.select.active_status', '0')
            ->assertSee('Inactive customer')
            ->assertDontSee($this->otherUser->name);
    }

    public function test_grid_sorts_and_paginates_users(): void
    {
        User::factory()->count(16)->sequence(fn ($sequence): array => [
            'name' => sprintf('Grid customer %02d', $sequence->index),
        ])->create();

        Livewire::actingAs($this->user)->test(UserTable::class)
            ->set('search', 'Grid customer')
            ->call('sortBy', 'name', 'desc')
            ->assertSeeInOrder(['Grid customer 15', 'Grid customer 14'])
            ->assertDontSee('Grid customer 00')
            ->call('gotoPage', 2)
            ->assertSee('Grid customer 00')
            ->assertDontSee('Grid customer 15');
    }

    public function test_grid_preserves_server_protected_row_actions(): void
    {
        Livewire::actingAs($this->user)->test(UserTable::class)
            ->assertSee(route('xpress.users.edit', $this->otherUser), false)
            ->assertSee(route('xpress.users.destroy', $this->otherUser), false)
            ->assertSee(route('users.reset-password.store', $this->otherUser), false)
            ->assertSee(route('users.reset-2fa.store', $this->otherUser), false)
            ->assertDontSee('action="'.route('users.reset-password.store', $this->user).'"', false);
    }

    public function test_grid_escapes_user_content_and_does_not_fetch_secrets(): void
    {
        $target = User::factory()->create([
            'name' => '<script>alert(1)</script>',
            'phone' => '<img src=x onerror=alert(1)>',
            'google2fa_secret' => 'PRIVATE-MFA-SECRET',
        ]);

        Livewire::actingAs($this->user)->test(UserTable::class)
            ->assertSee($target->name)
            ->assertDontSee($target->name, false)
            ->assertDontSee($target->phone, false)
            ->assertDontSee('PRIVATE-MFA-SECRET')
            ->assertDontSee($target->password, false);

        $row = (new UserTable)->datasource()->findOrFail($target->id);
        $this->assertArrayNotHasKey('password', $row->getAttributes());
        $this->assertArrayNotHasKey('google2fa_secret', $row->getAttributes());
    }
}
