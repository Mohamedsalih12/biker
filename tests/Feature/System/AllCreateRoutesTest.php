<?php

namespace Tests\Feature\System;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class AllCreateRoutesTest extends TestCase
{
    /**
     * Test all GET routes require login
     */
    public function test_all_create_routes_require_authentication(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods()))
            ->filter(fn ($route) => str_contains($route->uri(), 'create'));

        foreach ($routes as $route) {
            $response = $this->get('/'.$route->uri());

            // Should redirect to login
            $this->assertTrue(
                $response->isRedirect('/login') ||
                $response->status() === 302 ||
                $response->status() === 403,                     // forbidden
                'Route failed (guest access allowed): '.$route->uri()
            );
        }
    }

    public function test_authenticated_user_can_access_all_create_pages(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods()))
            ->filter(fn ($route) => str_contains($route->uri(), 'create'))
            ->filter(fn ($route) => ! str_contains($route->uri(), 'warehouse'));

        foreach ($routes as $route) {
            $uri = '/'.$route->uri();

            $response = $this->actingAsUser()->get($uri);

            $this->assertTrue(
                $response->getStatusCode() < 500,
                '💥 Route crashed: '.$uri
            );
        }
    }
}
