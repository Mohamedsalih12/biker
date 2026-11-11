<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserControllerTest extends TestCase
{
    public function test_password_update_revokes_database_sessions_and_remember_token(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
            'remember_token' => 'remember-token-before',
        ]);
        DB::table('sessions')->insert([
            'id' => 'target-session',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $response = $this->actingAsUser()->put(route('xpress.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'active_status' => true,
            'password' => 'NewPassword!123',
        ]);

        $response->assertRedirect('/admin/users');
        $this->assertTrue(Hash::check('NewPassword!123', $user->fresh()->password));
        $this->assertNotSame('remember-token-before', $user->fresh()->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'target-session']);
    }

    public function test_profile_update_without_password_keeps_existing_sessions_and_remember_token(): void
    {
        config(['session.driver' => 'database']);

        $user = User::factory()->create([
            'password' => Hash::make('old-password'),
            'remember_token' => 'remember-token-before',
        ]);
        $originalPassword = $user->password;
        DB::table('sessions')->insert([
            'id' => 'target-session',
            'user_id' => $user->id,
            'payload' => '',
            'last_activity' => now()->timestamp,
        ]);

        $response = $this->actingAsUser()->put(route('xpress.users.update', $user), [
            'name' => $user->name,
            'email' => $user->email,
            'active_status' => true,
        ]);

        $response->assertRedirect('/admin/users');
        $this->assertSame('remember-token-before', $user->fresh()->remember_token);
        $this->assertSame($originalPassword, $user->fresh()->password);
        $this->assertDatabaseHas('sessions', ['id' => 'target-session']);
    }
}
