<?php

namespace Tests\Feature\Auth;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SmokeTest extends TestCase
{
    public function test_application_redirects_guest_to_login(): void
    {
        $response = $this->get(route('home'));

        $response->assertRedirect(route('login'));
    }

    public function test_login_page_loads(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_database_connection_is_working(): void
    {
        $this->assertNotNull(DB::connection()->getPdo());
    }
}
