<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;

    protected User $user;

    protected User $otherUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->otherUser = User::factory()->create();

        // Ensure tests that rely on authenticated, OTP-verified sessions have the flag by default
        $this->withSession(['otp_verified' => true]);
    }

    protected function actingAsUser(): static
    {
        return $this->actingAs($this->user)->withSession(['otp_verified' => true]);
    }
}
