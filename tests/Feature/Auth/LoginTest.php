<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoginTest extends TestCase
{
    public function test_password_login_clears_previous_mfa_and_enrollment_state(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);

        $response = $this->withSession([
            'google2fa' => ['auth_passed' => true, 'auth_time' => now()->toIso8601String()],
            'google2fa_passed' => true,
            '2fa_secret' => 'OLD-SECRET',
            '2fa_target_user_id' => $this->user->id,
        ])->post('/login', ['email' => $user->email, 'password' => 'password123']);

        $response->assertRedirect(route('2fa.setup'));
        $response->assertSessionMissing('google2fa');
        $response->assertSessionMissing('google2fa_passed');
        $response->assertSessionMissing('2fa_secret');
        $response->assertSessionMissing('2fa_target_user_id');
    }

    public function test_login_page_loads(): void
    {
        $response = $this->get('/login');
        $response->assertStatus(200);
    }

    public function test_user_can_login(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
            'pos_code' => '182016',
            'shift_name' => '8 AM to 12 PM',
        ]);

        // Login now requires OTP verification: user should be redirected to OTP form
        $this->assertAuthenticatedAs($user);
        // $response->assertRedirect(route('otp.form'));
        // $this->assertEquals(session('otp_user_id'), $user->id);
        // $this->assertTrue(session('password_confirmed_for_otp'));
        $this->assertDatabaseHas('activity_log', ['log_name' => 'auth', 'event' => 'login_success']);
    }

    public function test_user_cannot_login_wrong_password(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('correct123'),
        ]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong',
            'pos_code' => '182016',
            'shift_name' => '8 AM to 12 PM',
        ]);

        $this->assertGuest();
    }

    public function test_first_time_login_user_has_no_google2fa_secret_before_enrollment(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);
        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
            'pos_code' => '182016',
            'shift_name' => '8 AM to 12 PM',
        ]);

        $user->refresh();

        $this->assertNull($user->google2fa_secret);
    }

    public function test_authenticated_user_can_register_their_own_2fa_device(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);
        $response = $this->actingAs($user)->get(route('2fa.setup'));

        $response->assertOk();
        $response->assertSessionHas('2fa_secret');
        $response->assertSessionHas('2fa_target_user_id', $user->id);
        $this->assertNull($user->fresh()->google2fa_secret);
    }

    public function test_first_time_login_redirects_to_2fa_setup(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('password123'),
        ]);
        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
            'pos_code' => '182016',
            'shift_name' => '8 AM to 12 PM',
        ]);

        $response->assertRedirect(route('2fa.setup'));
    }

    public function test_authenticated_user_can_open_home_page(): void
    {
        $response = $this->actingAsUser()->get(route('home'));

        $response->assertOk();
        $response->assertSee('Dashboard');
        $response->assertSee('No bookings recorded yet.');
    }

    public function test_user_can_logout(): void
    {
        $user = User::factory()->create(['password' => bcrypt('password123')]);
        // Simulate an authenticated session and a pre-existing login_logs entry
        $this->actingAs($user);
        $loginLogId = DB::table('login_logs')->insertGetId([
            'user_id' => $user->id,
            'pos_code' => '182016',
            'login_time' => now()->toDateTimeString(),
            'logout_time' => null,
            'ip_address' => '127.0.0.1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        session(['login_log_id' => $loginLogId]);

        $response = $this->post(route('logout'));
        $response->assertStatus(302);
        $response->assertRedirect('/login');
        $this->assertGuest();
        $logoutTime = DB::table('login_logs')->where('id', $loginLogId)->value('logout_time');
        $this->assertNotNull($logoutTime);
    }
}
