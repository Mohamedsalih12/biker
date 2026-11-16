<?php

namespace Tests\Feature\System;

use Illuminate\Support\Facades\Route;
use MuhammadSadeeq\ActivitylogUi\Http\Controllers\AssetController;
use Tests\TestCase;

class AllGetRoutesTest extends TestCase
{
    /**
     * Test all GET routes require login
     */
    public function test_all_get_routes_require_authentication(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods()))
            ->filter(fn ($route) => ! str_contains($route->uri(), 'login'))
            ->filter(fn ($route) => ! str_contains($route->uri(), 'livewire'))
            ->filter(fn ($route) => ! str_contains($route->uri(), 'truss'))
            ->filter(fn ($route) => $route->getName() !== 'fleet.bookings.create')
            ->filter(fn ($route) => $route->getName() !== 'activitylog-ui.assets.css')
            ->filter(fn ($route) => $route->uri() !== 'up')
            ->filter(fn ($route) => $route->uri() !== '/');

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

    public function test_activity_log_stylesheet_is_public_without_exposing_audit_data(): void
    {
        $this->get(route('activitylog-ui.assets.css', ['version' => AssetController::version()]))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/css; charset=UTF-8');

        $this->get(route('activitylog-ui.dashboard'))->assertRedirect('/login');
    }

    public function test_authenticated_user_can_access_all_get_pages(): void
    {
        $routes = collect(Route::getRoutes())
            ->filter(fn ($route) => in_array('GET', $route->methods()))
            ->filter(fn ($route) => ! str_contains($route->uri(), '{'))
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
