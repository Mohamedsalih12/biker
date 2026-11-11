<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ForcedPasswordChangeTest extends TestCase
{
    public function test_forced_password_change_page_is_available_to_a_user_marked_for_change(): void
    {
        $user = $this->user;
        $user->update(['must_change_password' => true]);

        $response = $this->actingAs($user)->get(route('password.force.edit'));

        $response->assertOk();
        $response->assertViewIs('auth.force-change-password');
    }

    public function test_forced_password_change_rejects_an_incorrect_current_password(): void
    {
        $user = $this->user;
        $user->password = Hash::make('temporary-password');
        $user->must_change_password = true;
        $user->save();

        $response = $this->actingAs($user)->put(route('password.force.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertSessionHasErrors(['current_password']);
        $this->assertTrue((bool) $user->fresh()->must_change_password);
    }

    public function test_forced_password_change_updates_the_password_and_clears_the_flag(): void
    {
        $user = $this->user;
        $user->password = Hash::make('temporary-password');
        $user->must_change_password = true;
        $user->save();

        $response = $this->actingAs($user)->put(route('password.force.update'), [
            'current_password' => 'temporary-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect(route('home'));
        $response->assertSessionHas('message', 'Password changed successfully.');
        $this->assertFalse($user->fresh()->must_change_password);
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'user',
            'event' => 'forced_password_changed',
        ]);
    }
}
