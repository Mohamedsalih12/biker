<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserPasswordResetTest extends TestCase
{
    public function test_authenticated_user_can_reset_another_users_password(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
        ]);

        $response = $this->actingAsUser()->post(route('users.reset-password.store', $user));

        $response->assertRedirect(route('xpress.users.index'));
        $response->assertSessionHas('reset_password_once');
        $resetData = session('reset_password_once');
        $this->assertSame($user->email, $resetData['user_email']);
        $this->assertNotSame('old-password', $resetData['temporary_password']);
        $this->assertTrue(Hash::check($resetData['temporary_password'], $user->fresh()->password));
        $this->assertTrue($user->fresh()->must_change_password);
        $this->assertSame($this->user->id, $user->fresh()->password_reset_by);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'user',
            'event' => 'password_reset',
        ]);
    }

    public function test_authenticated_user_with_verified_mfa_can_reset_another_users_two_factor_secret(): void
    {
        config(['google2fa.enabled' => true, 'google2fa.lifetime' => 0]);
        $this->user->forceFill(['google2fa_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $user = User::factory()->create(['google2fa_secret' => 'SECRET']);

        $response = $this->actingAsUser()->withSession([
            'google2fa.auth_passed' => true,
            'google2fa.auth_time' => now()->toIso8601String(),
        ])->post(route('users.reset-2fa.store', $user));

        $response->assertRedirect(route('xpress.users.index'));
        $response->assertSessionHas('reset2fa.user_email', $user->email);
        $this->assertNull($user->fresh()->google2fa_secret);
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'user',
            'event' => 'reset2fa',
        ]);
    }

    public function test_guest_cannot_reset_another_users_password(): void
    {
        $user = User::factory()->create();

        $response = $this->post(route('users.reset-password.store', $user));

        $response->assertRedirect(route('login'));
        $this->assertFalse($user->fresh()->must_change_password);
    }

    public function test_authenticated_user_cannot_reset_their_own_password_from_user_management(): void
    {
        $response = $this->actingAsUser()->post(route('users.reset-password.store', $this->user));

        $response->assertForbidden();
    }

    public function test_guest_cannot_reset_another_users_two_factor_secret(): void
    {
        $user = User::factory()->create(['google2fa_secret' => 'SECRET']);

        $response = $this->post(route('users.reset-2fa.store', $user));

        $response->assertRedirect(route('login'));
        $this->assertSame('SECRET', $user->fresh()->google2fa_secret);
    }

    public function test_user_without_verified_mfa_cannot_reset_another_users_two_factor_secret(): void
    {
        $user = User::factory()->create(['google2fa_secret' => 'SECRET']);

        $response = $this->actingAsUser()->post(route('users.reset-2fa.store', $user));

        $response->assertForbidden();
        $this->assertSame('SECRET', $user->fresh()->google2fa_secret);
        $this->assertDatabaseMissing('activity_log', ['event' => 'reset2fa']);
    }
}
