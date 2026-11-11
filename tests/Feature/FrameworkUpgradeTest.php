<?php

namespace Tests\Feature;

use App\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class FrameworkUpgradeTest extends TestCase
{
    public function test_form_posts_without_a_csrf_token_are_rejected(): void
    {
        $request = $this->formRequest();

        $this->expectException(TokenMismatchException::class);

        app(PreventRequestForgery::class)->handle($request, fn (): Response => response('accepted'));
    }

    public function test_form_posts_with_a_valid_csrf_token_are_accepted(): void
    {
        $request = $this->formRequest();
        $request->request->set('_token', $request->session()->token());

        $response = app(PreventRequestForgery::class)->handle($request, fn (): Response => response('accepted'));

        $this->assertSame('accepted', $response->getContent());
    }

    public function test_cross_site_posts_cannot_bypass_csrf_protection(): void
    {
        $request = $this->formRequest();
        $request->headers->set('Sec-Fetch-Site', 'cross-site');
        $request->request->set('_token', 'invalid-token');

        $this->expectException(TokenMismatchException::class);

        app(PreventRequestForgery::class)->handle($request, fn (): Response => response('accepted'));
    }

    public function test_database_cache_round_trips_scalar_payloads(): void
    {
        $store = Cache::build(['driver' => 'database', 'table' => 'cache']);
        $key = 'framework-upgrade:'.Str::uuid();

        try {
            $store->put($key, ['country' => 'SA', 'attempts' => 0], 60);

            $this->assertSame(['country' => 'SA', 'attempts' => 0], $store->get($key));
        } finally {
            $store->forget($key);
        }
    }

    public function test_sessions_keep_the_existing_php_serialization_format(): void
    {
        $session = app('session')->driver('array');
        $session->start();
        $session->put('upgrade-probe', ['otp_verified' => true]);

        $session->save();

        $stored = unserialize($session->getHandler()->read($session->getId()), ['allowed_classes' => false]);
        $this->assertSame(['otp_verified' => true], $stored['upgrade-probe']);
    }

    public function test_cache_does_not_restore_unlisted_php_classes(): void
    {
        $store = Cache::build(['driver' => 'file', 'path' => storage_path('framework/cache/data')]);
        $key = 'framework-upgrade:'.Str::uuid();

        try {
            $store->put($key, (object) ['country' => 'SA'], 60);

            $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $store->get($key));
        } finally {
            $store->forget($key);
        }
    }

    public function test_web_routes_reject_cross_site_posts_without_a_token(): void
    {
        Route::post('/framework-upgrade-probe', fn (): string => 'accepted')->middleware('web');
        $this->app['env'] = 'production';

        $this->withHeader('Sec-Fetch-Site', 'cross-site')
            ->post('/framework-upgrade-probe')
            ->assertStatus(419);
    }

    public function test_web_routes_accept_form_posts_with_a_valid_session_token(): void
    {
        Route::post('/framework-upgrade-probe', fn (): string => 'accepted')->middleware('web');
        $this->app['env'] = 'production';

        $this->withSession(['_token' => 'upgrade-session-token'])
            ->post('/framework-upgrade-probe', ['_token' => 'upgrade-session-token'])
            ->assertSeeText('accepted');
    }

    private function formRequest(): Request
    {
        $this->app['env'] = 'production';
        $session = app('session')->driver('array');
        $session->start();
        $request = Request::create('/login', 'POST');
        $request->setLaravelSession($session);

        return $request;
    }
}
