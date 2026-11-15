<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    #[DataProvider('restrictedEndpoints')]
    public function test_non_admin_cannot_manage_users(string $role, string $method, string $routeName): void
    {
        config(['google2fa.enabled' => true, 'google2fa.lifetime' => 0]);
        $this->user->forceFill(['role' => $role, 'google2fa_secret' => 'JBSWY3DPEHPK3PXP']);
        $originalPassword = $this->otherUser->password;
        $this->actingAsUser()->withSession([
            'google2fa.auth_passed' => true,
            'google2fa.auth_time' => now()->toIso8601String(),
        ]);

        $response = $this->{$method}(route($routeName, $this->otherUser), [
            'name' => 'Unauthorized change',
            'email' => 'changed@example.com',
            'phone' => '+94112223344',
            'password' => 'NewPassword!123',
            'role' => 'admin',
        ]);

        $response->assertForbidden();
        $this->assertModelExists($this->otherUser);
        $this->assertSame($originalPassword, $this->otherUser->fresh()->password);
        $this->assertSame($this->otherUser->name, $this->otherUser->fresh()->name);
    }

    public static function restrictedEndpoints(): array
    {
        $endpoints = [
            ['get', 'xpress.users.index'],
            ['get', 'xpress.users.userdetails'],
            ['get', 'xpress.users.create'],
            ['get', 'xpress.users.edit'],
            ['post', 'xpress.users.store'],
            ['put', 'xpress.users.update'],
            ['delete', 'xpress.users.destroy'],
            ['post', 'users.reset-password.store'],
            ['post', 'users.reset-2fa.store'],
            ['get', 'xpress.adminprofile.edit'],
            ['put', 'xpress.adminprofile.update'],
        ];
        $cases = [];

        foreach (['staff', 'customer'] as $role) {
            foreach ($endpoints as [$method, $routeName]) {
                $cases[$role.' '.$routeName] = [$role, $method, $routeName];
            }
        }

        return $cases;
    }

    public function test_user_management_gate_restricts_all_user_abilities_to_admins(): void
    {
        foreach (['admin' => true, 'staff' => false, 'customer' => false] as $role => $allowed) {
            $actor = User::factory()->make(['role' => $role]);
            $gate = Gate::forUser($actor);

            $this->assertSame($allowed, $gate->allows('manage-users'));
            $this->assertSame($allowed, $gate->allows('isAdmin'));
            $this->assertSame($allowed, $gate->allows('viewAny', User::class));
            $this->assertSame($allowed, $gate->allows('create', User::class));
            $this->assertSame($allowed, $gate->allows('update', $this->otherUser));
            $this->assertSame($allowed, $gate->allows('delete', $this->otherUser));
        }
    }

    public function test_unhandled_abilities_are_not_automatically_granted(): void
    {
        Gate::define('staff-only-test-ability', fn (User $user): bool => $user->role === 'staff');

        foreach (['admin', 'staff', 'customer'] as $role) {
            $gate = Gate::forUser(User::factory()->make(['role' => $role]));

            $this->assertFalse($gate->allows('undefined-test-ability'));
            $this->assertSame($role === 'staff', $gate->allows('staff-only-test-ability'));
        }
    }

    public function test_admin_can_create_a_customer_without_optional_credentials(): void
    {
        $this->user->forceFill(['role' => 'admin']);

        $response = $this->actingAsUser()->post(route('xpress.users.store'), [
            'name' => 'New customer',
            'phone' => '+94112223344',
            'address' => '12 Main Street',
        ]);

        $response->assertSessionHasNoErrors();
        $user = User::where('name', 'New customer')->firstOrFail();
        $response->assertRedirect(route('xpress.users.edit', $user));
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'phone' => '+94112223344',
            'address' => '12 Main Street',
            'role' => 'customer',
            'email' => null,
            'password' => null,
        ]);
    }

    public function test_admin_can_create_a_staff_account_with_hashed_password(): void
    {
        $this->user->forceFill(['role' => 'admin']);

        $response = $this->actingAsUser()->post(route('xpress.users.store'), [
            'name' => 'New staff',
            'email' => 'staff@example.com',
            'phone' => '+94112223344',
            'password' => 'StaffPassword!123',
            'role' => 'staff',
        ]);

        $response->assertSessionHasNoErrors();
        $user = User::where('email', 'staff@example.com')->firstOrFail();
        $this->assertSame('staff', $user->role);
        $this->assertTrue(Hash::check('StaffPassword!123', $user->password));
    }

    public function test_admin_can_update_user_details_without_clearing_the_password(): void
    {
        $this->user->forceFill(['role' => 'admin']);
        $password = $this->otherUser->password;

        $response = $this->actingAsUser()->put(route('xpress.users.update', $this->otherUser), [
            'name' => 'Updated customer',
            'email' => null,
            'phone' => '+94112223344',
            'role' => 'staff',
            'address' => 'Updated address',
            'password' => '',
            'active_status' => true,
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('xpress.users.index'));
        $this->assertDatabaseHas('users', [
            'id' => $this->otherUser->id,
            'name' => 'Updated customer',
            'email' => null,
            'phone' => '+94112223344',
            'role' => 'staff',
            'address' => 'Updated address',
            'password' => $password,
        ]);
    }

    public function test_user_creation_requires_name_and_phone(): void
    {
        $this->user->forceFill(['role' => 'admin']);

        $response = $this->actingAsUser()->post(route('xpress.users.store'), []);

        $response->assertSessionHasErrors(['name', 'phone']);
        $response->assertSessionDoesntHaveErrors(['email', 'password']);
    }

    #[DataProvider('invalidDetails')]
    public function test_invalid_user_details_are_rejected(string $field, mixed $value): void
    {
        $this->user->forceFill(['role' => 'admin']);

        $response = $this->actingAsUser()->post(route('xpress.users.store'), array_replace([
            'name' => 'Invalid customer',
            'phone' => '+94112223344',
        ], [$field => $value]));

        $response->assertSessionHasErrors([$field]);
        $this->assertDatabaseMissing('users', ['name' => 'Invalid customer']);
    }

    public static function invalidDetails(): array
    {
        return [
            'unknown role' => ['role', 'superadmin'],
            'malformed email' => ['email', 'not-an-email'],
            'long phone' => ['phone', str_repeat('1', 31)],
            'non-text address' => ['address', ['invalid']],
        ];
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $this->user->forceFill(['role' => 'admin']);

        $response = $this->actingAsUser()->post(route('xpress.users.store'), [
            'name' => 'Duplicate email',
            'phone' => '+94112223344',
            'email' => $this->otherUser->email,
        ]);

        $response->assertSessionHasErrors(['email']);
        $this->assertDatabaseMissing('users', ['name' => 'Duplicate email']);
    }

    #[DataProvider('crossUserTwoFactorEndpoints')]
    public function test_non_admin_cannot_manage_another_users_authenticator(string $role, string $method, string $routeName): void
    {
        config(['google2fa.enabled' => true, 'google2fa.lifetime' => 0]);
        $this->user->forceFill(['role' => $role, 'google2fa_secret' => 'JBSWY3DPEHPK3PXP']);
        $this->actingAsUser()->withSession([
            'google2fa.auth_passed' => true,
            'google2fa.auth_time' => now()->toIso8601String(),
            '2fa_setup_user_id' => $this->user->id,
            '2fa_target_user_id' => $this->otherUser->id,
            '2fa_secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        $response = $this->{$method}(route($routeName, $this->otherUser), [
            'user_id' => $this->otherUser->id,
            'otp' => '000000',
        ]);

        $response->assertForbidden();
        $this->assertNull($this->otherUser->fresh()->google2fa_secret);
    }

    public static function crossUserTwoFactorEndpoints(): array
    {
        $cases = [];

        foreach (['staff', 'customer'] as $role) {
            foreach ([['get', '2fa.setup'], ['get', '2fa.disable.form'], ['post', '2fa.enable'], ['post', '2fa.disable']] as [$method, $routeName]) {
                $cases[$role.' '.$routeName] = [$role, $method, $routeName];
            }
        }

        return $cases;
    }

    public function test_admin_forms_and_list_display_contact_details_and_roles(): void
    {
        $this->otherUser->forceFill(['phone' => '+94112223344', 'address' => '12 Main Street', 'role' => 'staff'])->save();

        $this->actingAsUser()->get(route('xpress.users.create'))
            ->assertSee('name="phone"', false)
            ->assertSee('name="role"', false)
            ->assertSee('name="address"', false);
        $this->get(route('xpress.users.edit', $this->otherUser))
            ->assertSee('+94112223344')
            ->assertSee('12 Main Street')
            ->assertSee('value="staff" selected', false);
        $this->get(route('xpress.users.index'))
            ->assertSee('+94112223344')
            ->assertSee('Staff');
    }

    public function test_customer_profile_cannot_escalate_role_or_display_user_management_links(): void
    {
        $this->user->forceFill(['role' => 'customer'])->save();

        $this->actingAsUser()->put(route('xpress.profile.update'), [
            'name' => 'Customer profile',
            'role' => 'admin',
        ])->assertSessionHasNoErrors();

        $this->assertSame('customer', $this->user->fresh()->role);
        $this->get(route('xpress.profile.edit'))
            ->assertDontSee('href="'.route('xpress.users.index').'"', false)
            ->assertDontSee('href="'.route('xpress.users.create').'"', false);
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_can_change_their_own_password(string $role): void
    {
        $this->user->forceFill(['role' => $role])->save();

        $response = $this->actingAsUser()->put(route('xpress.change-password'), [
            'current_password' => 'password',
            'new_password' => 'ChangedPassword!123',
            'new_password_confirmation' => 'ChangedPassword!123',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('message', 'Password changed successfully.');
        $this->assertTrue(Hash::check('ChangedPassword!123', $this->user->fresh()->password));
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_can_disable_their_own_authenticator_with_verified_mfa_and_otp(string $role): void
    {
        config(['google2fa.enabled' => true, 'google2fa.lifetime' => 0]);
        $secret = 'JBSWY3DPEHPK3PXP';
        $this->user->forceFill(['role' => $role, 'google2fa_secret' => $secret])->save();
        $otp = app('pragmarx.google2fa')->getCurrentOtp($secret);

        $response = $this->actingAsUser()->withSession([
            'google2fa.auth_passed' => true,
            'google2fa.auth_time' => now()->toIso8601String(),
        ])->post(route('2fa.disable'), ['otp' => $otp]);

        $response->assertRedirect(route('home'));
        $this->assertNull($this->user->fresh()->google2fa_secret);
    }

    public static function nonAdminRoles(): array
    {
        return ['staff' => ['staff'], 'customer' => ['customer']];
    }
}
