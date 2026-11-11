<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordChangeTest extends TestCase
{
    public function test_user_password_change_logs_activity(): void
    {
        $response = $this->actingAs($this->user)->put(route('xpress.change-password'), [
            'current_password' => 'password',
            'new_password' => 'new-password',
            'new_password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('message', 'Password changed successfully.');
        $this->assertTrue(Hash::check('new-password', $this->user->fresh()->password));
        $this->assertDatabaseHas('activity_log', [
            'log_name' => 'password',
            'event' => 'password_changed',
            'description' => 'User Password Changed',
            'causer_id' => $this->user->id,
            'subject_id' => $this->user->id,
        ]);
    }
}
