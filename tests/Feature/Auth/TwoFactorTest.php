<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Mockery;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    public function test_first_time_login_redirects_to_two_factor_setup(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('2fa.setup'));
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->fresh()->google2fa_secret);
    }

    public function test_enrolled_user_login_redirects_to_user_index(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
            'google2fa_secret' => 'TEST-SECRET',
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $response->assertRedirect(route('xpress.users.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_unauthenticated_user_is_redirected_from_two_factor_setup(): void
    {
        $response = $this->get(route('2fa.setup'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_can_open_setup_without_saving_unverified_secret(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('2fa.setup'));

        $response->assertOk();
        $this->assertNull($user->fresh()->google2fa_secret);
        $response->assertSessionHas('2fa_target_user_id', $user->id);
        $response->assertSessionHas('2fa_secret');
    }

    public function test_user_who_abandons_setup_is_sent_back_to_setup_at_next_login(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect(route('2fa.setup'));

        $this->get(route('2fa.setup'))->assertOk();
        $this->assertNull($user->fresh()->google2fa_secret);

        auth()->logout();
        $this->app['session']->invalidate();
        $this->app['session']->regenerateToken();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ])->assertRedirect(route('2fa.setup'));
    }

    public function test_user_without_verified_mfa_cannot_manage_another_users_two_factor_setup(): void
    {
        $user = User::factory()->create();
        $targetUser = User::factory()->create();

        $response = $this->actingAs($user)->get(route('2fa.setup', $targetUser));

        $response->assertForbidden();
    }

    public function test_verified_user_can_open_two_factor_setup_for_another_user(): void
    {
        $targetUser = User::factory()->create();

        $response = $this->actingAsMfaVerifiedUser()->get(route('2fa.setup', $targetUser));

        $response->assertOk();
        $this->assertNull($targetUser->fresh()->google2fa_secret);
        $response->assertSessionHas('2fa_target_user_id', $targetUser->id);
    }

    public function test_setup_never_discloses_an_existing_secret(): void
    {
        $user = User::factory()->create(['google2fa_secret' => 'EXISTING-SECRET']);

        $response = $this->actingAs($user)->withSession(['2fa_secret' => 'SESSION-SECRET'])
            ->get(route('2fa.setup'));

        $response->assertForbidden();
        $response->assertDontSee('EXISTING-SECRET');
        $response->assertSessionMissing('2fa_secret');
        $this->assertSame('EXISTING-SECRET', $user->fresh()->google2fa_secret);
    }

    public function test_enable_requires_a_six_digit_otp(): void
    {
        $response = $this->actingAsUser()->post(route('2fa.enable'), ['otp' => '123']);

        $response->assertSessionHasErrors(['otp']);
    }

    public function test_enable_redirects_when_setup_session_has_expired(): void
    {
        $response = $this->actingAsUser()->post(route('2fa.enable'), ['otp' => '123456']);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Your 2FA setup session expired. Please try again.');
    }

    public function test_enable_redirects_when_setup_target_user_no_longer_exists(): void
    {
        $response = $this->actingAsUser()->withSession([
            '2fa_secret' => 'TARGET-SECRET',
            '2fa_target_user_id' => 999999,
        ])->post(route('2fa.enable'), ['otp' => '123456']);

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Your 2FA setup session expired. Please try again.');
    }

    public function test_user_without_verified_mfa_cannot_enable_two_factor_for_another_user(): void
    {
        $targetUser = User::factory()->create();

        $response = $this->actingAs($this->otherUser)->withSession([
            '2fa_secret' => 'TARGET-SECRET',
            '2fa_target_user_id' => $targetUser->id,
        ])->post(route('2fa.enable'), ['otp' => '123456']);

        $response->assertForbidden();
        $this->assertNull($targetUser->fresh()->google2fa_secret);
    }

    public function test_enable_rejects_an_invalid_otp(): void
    {
        $this->actingAsUser()->get(route('2fa.setup'));
        $google2fa = Mockery::mock();
        $google2fa->shouldReceive('verifyKey')->once()->with(Mockery::type('string'), '000000')->andReturnFalse();
        $this->app->instance('pragmarx.google2fa', $google2fa);

        $response = $this->actingAsUser()->post(route('2fa.enable'), ['otp' => '000000']);

        $response->assertSessionHas('error', 'Invalid OTP, please try again.');
        $this->assertNull($this->user->fresh()->google2fa_secret);
    }

    public function test_enable_accepts_a_valid_otp_and_clears_setup_session(): void
    {
        $user = $this->user;
        $this->actingAs($user)->get(route('2fa.setup'));
        $secret = session('2fa_secret');
        $google2fa = Mockery::mock();
        $google2fa->shouldReceive('verifyKey')->once()->with($secret, '123456')->andReturnTrue();
        $this->app->instance('pragmarx.google2fa', $google2fa);

        $response = $this->actingAs($user)->post(route('2fa.enable'), ['otp' => '123456']);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('success', '2FA enabled successfully for '.$user->email.'.');
        $response->assertSessionMissing('2fa_secret');
        $response->assertSessionMissing('2fa_target_user_id');
        $this->assertSame($secret, $user->fresh()->google2fa_secret);
    }

    public function test_verified_user_can_enable_two_factor_for_another_user_with_a_valid_otp(): void
    {
        $targetUser = User::factory()->create();
        $this->actingAsMfaVerifiedUser()->get(route('2fa.setup', $targetUser));
        $secret = session('2fa_secret');
        $google2fa = Mockery::mock();
        $google2fa->shouldReceive('verifyKey')->once()->with($secret, '123456')->andReturnTrue();
        $this->app->instance('pragmarx.google2fa', $google2fa);

        $response = $this->actingAsUser()->post(route('2fa.enable'), ['otp' => '123456']);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('success', '2FA enabled successfully for '.$targetUser->email.'.');
        $this->assertSame($secret, $targetUser->fresh()->google2fa_secret);
    }

    public function test_verified_user_can_disable_two_factor_for_another_user_without_an_otp(): void
    {
        $targetUser = User::factory()->create(['google2fa_secret' => 'TARGET-SECRET']);

        $response = $this->actingAsMfaVerifiedUser()->post(route('2fa.disable'), [
            'user_id' => $targetUser->id,
        ]);

        $response->assertRedirect(route('home'));
        $this->assertNull($targetUser->fresh()->google2fa_secret);
    }

    public function test_disable_redirects_when_target_user_has_no_two_factor_enabled(): void
    {
        $targetUser = User::factory()->create();

        $response = $this->actingAsMfaVerifiedUser()->post(route('2fa.disable'), [
            'user_id' => $targetUser->id,
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('info', '2FA is not enabled for this user.');
        $this->assertNull($targetUser->fresh()->google2fa_secret);
    }

    public function test_unauthenticated_user_is_redirected_from_two_factor_disable(): void
    {
        $response = $this->post(route('2fa.disable'));

        $response->assertRedirect(route('login'));
    }

    public function test_user_without_verified_mfa_cannot_open_two_factor_disable_form(): void
    {
        $response = $this->actingAs($this->otherUser)->get(route('2fa.disable.form'));

        $response->assertForbidden();
    }

    public function test_verified_user_can_open_two_factor_disable_form_for_another_user(): void
    {
        $targetUser = User::factory()->create(['google2fa_secret' => 'TARGET-SECRET']);

        $response = $this->actingAsMfaVerifiedUser()->get(route('2fa.disable.form', $targetUser));

        $response->assertOk();
        $response->assertViewHas('targetUser', $targetUser);
    }

    public function test_disabling_own_two_factor_requires_a_six_digit_otp(): void
    {
        $this->user->google2fa_secret = 'ADMIN-SECRET';
        $this->user->save();

        $response = $this->actingAsUser()->post(route('2fa.disable'), ['otp' => '123']);

        $response->assertSessionHasErrors(['otp']);
        $this->assertSame('ADMIN-SECRET', $this->user->fresh()->google2fa_secret);
    }

    public function test_user_cannot_disable_their_own_two_factor_with_an_invalid_otp(): void
    {
        $this->user->google2fa_secret = 'ADMIN-SECRET';
        $this->user->save();
        $google2fa = Mockery::mock();
        $google2fa->shouldReceive('verifyKey')->once()->with('ADMIN-SECRET', '000000')->andReturnFalse();
        $this->app->instance('pragmarx.google2fa', $google2fa);

        $response = $this->actingAsMfaVerifiedUser()->post(route('2fa.disable'), ['otp' => '000000']);

        $response->assertSessionHas('error', 'Invalid OTP. Please try again.');
        $this->assertSame('ADMIN-SECRET', $this->user->fresh()->google2fa_secret);
    }

    public function test_user_can_disable_their_own_two_factor_with_a_valid_otp(): void
    {
        $this->user->google2fa_secret = 'ADMIN-SECRET';
        $this->user->save();
        $google2fa = Mockery::mock();
        $google2fa->shouldReceive('verifyKey')->once()->with('ADMIN-SECRET', '123456')->andReturnTrue();
        $this->app->instance('pragmarx.google2fa', $google2fa);

        $response = $this->actingAsMfaVerifiedUser()->withSession([
            'google2fa_passed' => true,
            '2fa_secret' => 'SETUP-SECRET',
            '2fa_target_user_id' => $this->user->id,
        ])->post(route('2fa.disable'), ['otp' => '123456']);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('success', '2FA has been disabled for '.$this->user->email.'.');
        $response->assertSessionMissing('google2fa_passed');
        $response->assertSessionMissing('2fa_secret');
        $response->assertSessionMissing('2fa_target_user_id');
        $this->assertNull($this->user->fresh()->google2fa_secret);
    }

    public function test_user_without_verified_mfa_cannot_disable_two_factor(): void
    {
        $user = User::factory()->create([
            'google2fa_secret' => 'USER-SECRET',
        ]);
        $response = $this->actingAs($user)->post(route('2fa.disable'), ['otp' => '123456']);

        $response->assertForbidden();
    }

    public function test_disable_form_redirects_when_two_factor_is_not_enabled(): void
    {
        $this->actingAsMfaVerifiedUser();
        $targetUser = User::factory()->create();

        $response = $this->get(route('2fa.disable.form', $targetUser));

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('info', '2FA is not enabled for this user.');
    }

    public function test_user_without_verified_mfa_cannot_enroll_another_user(): void
    {
        $targetUser = User::factory()->create();

        $this->actingAsUser()->get(route('2fa.setup', $targetUser))->assertForbidden();
    }

    public function test_user_without_verified_mfa_cannot_disable_another_users_factor(): void
    {
        $this->user->forceFill(['google2fa_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        $targetUser = User::factory()->create(['google2fa_secret' => 'TARGET-SECRET']);

        $response = $this->actingAsUser()->post(route('2fa.disable'), ['user_id' => $targetUser->id]);

        $response->assertForbidden();
        $this->assertSame('TARGET-SECRET', $targetUser->fresh()->google2fa_secret);
    }

    public function test_user_without_verified_mfa_cannot_open_another_users_disable_form(): void
    {
        $targetUser = User::factory()->create(['google2fa_secret' => 'TARGET-SECRET']);

        $this->actingAsUser()->get(route('2fa.disable.form', $targetUser))->assertForbidden();
    }

    public function test_enable_cannot_replace_an_already_enrolled_factor(): void
    {
        $user = User::factory()->create(['google2fa_secret' => 'EXISTING-SECRET']);
        $pendingSecret = 'JBSWY3DPEHPK3PXP';
        $otp = app('pragmarx.google2fa')->getCurrentOtp($pendingSecret);

        $response = $this->actingAs($user)->withSession([
            '2fa_secret' => $pendingSecret,
            '2fa_target_user_id' => $user->id,
        ])->post(route('2fa.enable'), ['otp' => $otp]);

        $response->assertForbidden();
        $response->assertSessionMissing('2fa_secret');
        $this->assertSame('EXISTING-SECRET', $user->fresh()->google2fa_secret);
    }

    public function test_setup_does_not_reuse_a_different_users_pending_secret(): void
    {
        $this->actingAsMfaVerifiedUser();
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();
        $this->get(route('2fa.setup', $firstUser));
        $firstSecret = session('2fa_secret');

        $response = $this->get(route('2fa.setup', $secondUser));

        $response->assertOk();
        $response->assertSessionHas('2fa_target_user_id', $secondUser->id);
        $this->assertNotSame($firstSecret, session('2fa_secret'));
    }

    public function test_enable_rejects_a_pending_secret_without_a_target_binding(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $otp = app('pragmarx.google2fa')->getCurrentOtp($secret);

        $response = $this->actingAsUser()->withSession(['2fa_secret' => $secret])
            ->post(route('2fa.enable'), ['otp' => $otp]);

        $response->assertRedirect(route('login'));
        $this->assertNull($this->user->fresh()->google2fa_secret);
    }

    public function test_expired_mfa_cannot_authorize_factor_management(): void
    {
        $this->actingAsMfaVerifiedUser();
        config(['google2fa.lifetime' => 5]);
        $targetUser = User::factory()->create(['google2fa_secret' => 'TARGET-SECRET']);

        $response = $this->withSession(['google2fa.auth_time' => now()->subMinutes(10)->toIso8601String()])
            ->post(route('2fa.disable'), ['user_id' => $targetUser->id]);

        $response->assertForbidden();
        $this->assertSame('TARGET-SECRET', $targetUser->fresh()->google2fa_secret);
    }

    public function test_verified_user_cannot_view_another_users_enrolled_secret(): void
    {
        $targetUser = User::factory()->create(['google2fa_secret' => 'TARGET-SECRET']);

        $response = $this->actingAsMfaVerifiedUser()->get(route('2fa.setup', $targetUser));

        $response->assertForbidden();
        $response->assertDontSee('TARGET-SECRET');
        $response->assertSessionMissing('2fa_secret');
    }

    public function test_verified_user_cannot_redisplay_their_enrolled_secret(): void
    {
        $this->actingAsMfaVerifiedUser();

        $response = $this->get(route('2fa.setup'));

        $response->assertForbidden();
        $response->assertDontSee('JBSWY3DPEHPK3PXP');
    }

    public function test_pending_enrollment_cannot_be_completed_after_mfa_is_lost(): void
    {
        $targetUser = User::factory()->create();
        $this->actingAsMfaVerifiedUser()->get(route('2fa.setup', $targetUser));
        $otp = app('pragmarx.google2fa')->getCurrentOtp(session('2fa_secret'));

        $response = $this->withSession(['google2fa.auth_passed' => false])
            ->post(route('2fa.enable'), ['otp' => $otp]);

        $response->assertForbidden();
        $this->assertNull($targetUser->fresh()->google2fa_secret);
    }

    public function test_pending_enrollment_is_rejected_when_it_belongs_to_another_actor(): void
    {
        $this->actingAsUser()->get(route('2fa.setup'));
        $otp = app('pragmarx.google2fa')->getCurrentOtp(session('2fa_secret'));

        $response = $this->withSession(['2fa_setup_user_id' => $this->otherUser->id])
            ->post(route('2fa.enable'), ['otp' => $otp]);

        $response->assertRedirect(route('login'));
        $response->assertSessionMissing('2fa_secret');
        $this->assertNull($this->user->fresh()->google2fa_secret);
    }

    public function test_same_user_can_resume_pending_enrollment(): void
    {
        $this->actingAsUser()->get(route('2fa.setup'));
        $secret = session('2fa_secret');

        $response = $this->get(route('2fa.setup'));

        $response->assertViewHas('secret', $secret);
        $response->assertSessionHas('2fa_secret', $secret);
    }

    public function test_disabled_otp_configuration_does_not_authorize_factor_management(): void
    {
        $this->actingAsMfaVerifiedUser();
        config(['google2fa.enabled' => false]);
        $targetUser = User::factory()->create(['google2fa_secret' => 'TARGET-SECRET']);

        $response = $this->post(route('2fa.disable'), ['user_id' => $targetUser->id]);

        $response->assertForbidden();
        $this->assertSame('TARGET-SECRET', $targetUser->fresh()->google2fa_secret);
    }

    public function test_first_time_enrollment_accepts_a_real_authenticator_code(): void
    {
        $this->actingAsUser()->get(route('2fa.setup'));
        $secret = session('2fa_secret');
        $otp = app('pragmarx.google2fa')->getCurrentOtp($secret);

        $response = $this->post(route('2fa.enable'), ['otp' => $otp]);

        $response->assertRedirect(route('home'));
        $response->assertSessionMissing('2fa_setup_user_id');
        $this->assertSame($secret, $this->user->fresh()->google2fa_secret);
    }

    public function test_real_otp_verification_authorizes_subsequent_factor_management(): void
    {
        config(['google2fa.enabled' => true, 'google2fa.lifetime' => 0]);
        $secret = 'JBSWY3DPEHPK3PXP';
        $this->user->forceFill(['google2fa_secret' => $secret])->save();
        $targetUser = User::factory()->create(['google2fa_secret' => 'TARGET-SECRET']);
        $otp = app('pragmarx.google2fa')->getCurrentOtp($secret);
        $this->actingAsUser()->get(route('2fa.disable.form', $targetUser))->assertForbidden();

        $verification = $this->post(route('2fa'), ['one_time_password' => $otp]);
        $verification->assertRedirect(route('home'));
        $verification->assertSessionHas('google2fa.auth_passed', true);
        $response = $this->post(route('2fa.disable'), ['user_id' => $targetUser->id]);

        $response->assertRedirect(route('home'));
        $this->assertNull($targetUser->fresh()->google2fa_secret);
    }

    private function actingAsMfaVerifiedUser(): static
    {
        config(['google2fa.enabled' => true, 'google2fa.lifetime' => 0]);
        if (! $this->user->google2fa_secret) {
            $this->user->forceFill(['google2fa_secret' => 'JBSWY3DPEHPK3PXP'])->save();
        }

        return $this->actingAsUser()->withSession([
            'google2fa.auth_passed' => true,
            'google2fa.auth_time' => now()->toIso8601String(),
        ]);
    }
}
