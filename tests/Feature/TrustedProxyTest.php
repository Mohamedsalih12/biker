<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TrustedProxyTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A range listed in TRUSTED_PROXIES in phpunit.xml.
     */
    private const CLOUDFLARE_IP = '173.245.48.90';

    private const CLIENT_IP = '203.0.113.45';

    private const LOOKUP_COUNTRY = 'XX';

    protected function setUp(): void
    {
        parent::setUp();

        // The country fallback calls ipwho.is. Stub it so the suite stays
        // offline and the assertions below can prove which branch was taken.
        Http::fake([
            'ipwho.is/*' => Http::response([
                'success' => true,
                'country_code' => self::LOOKUP_COUNTRY,
            ]),
        ]);
    }

    public function test_client_ip_comes_from_x_forwarded_for_behind_a_trusted_proxy(): void
    {
        $this->assertSame(
            self::CLIENT_IP,
            $this->requestFrom(self::CLOUDFLARE_IP, forwardedFor: self::CLIENT_IP)->ip()
        );
    }

    public function test_x_forwarded_for_is_ignored_from_an_untrusted_source(): void
    {
        // Anyone able to reach the origin directly must not be able to forge
        // their IP and walk past the login or import rate limiters.
        $this->assertSame(
            '198.51.100.7',
            $this->requestFrom('198.51.100.7', forwardedFor: self::CLIENT_IP)->ip()
        );
    }

    public function test_a_trusted_proxy_chain_keeps_the_original_client(): void
    {
        $this->assertSame(
            self::CLIENT_IP,
            $this->requestFrom(self::CLOUDFLARE_IP, forwardedFor: self::CLIENT_IP.', '.self::CLOUDFLARE_IP)->ip()
        );
    }

    public function test_missing_x_forwarded_for_falls_back_to_the_proxy_address(): void
    {
        $this->assertSame(
            self::CLOUDFLARE_IP,
            $this->requestFrom(self::CLOUDFLARE_IP)->ip()
        );
    }

    public function test_a_trusted_proxy_is_trusted_for_x_forwarded_proto(): void
    {
        $request = $this->requestFrom(self::CLOUDFLARE_IP, forwardedFor: self::CLIENT_IP);
        $request->headers->set('X-Forwarded-Proto', 'https');

        $this->assertTrue($request->isSecure());
    }

    public function test_country_header_is_honoured_from_a_trusted_proxy(): void
    {
        $audit = app(AuditService::class);

        $country = $audit->countryCode($this->requestFrom(self::CLOUDFLARE_IP, country: 'sa'));

        $this->assertSame('SA', $country);
        Http::assertNothingSent();
    }

    public function test_country_header_is_ignored_from_an_untrusted_proxy(): void
    {
        $audit = app(AuditService::class);

        $country = $audit->countryCode($this->requestFrom('198.51.100.7', country: 'sa'));

        // Falls through to the IP lookup rather than believing the header.
        $this->assertSame(self::LOOKUP_COUNTRY, $country);
    }

    public function test_a_malformed_country_header_is_ignored(): void
    {
        $audit = app(AuditService::class);

        $country = $audit->countryCode($this->requestFrom(self::CLOUDFLARE_IP, country: 'not-a-country'));

        $this->assertSame(self::LOOKUP_COUNTRY, $country);
    }

    public function test_the_audit_log_records_the_real_client_ip_and_country(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $this->withServerVariables([
            'REMOTE_ADDR' => self::CLOUDFLARE_IP,
            'HTTP_X_FORWARDED_FOR' => self::CLIENT_IP,
            'HTTP_CF_IPCOUNTRY' => 'sa',
        ])->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $log = ActivityLog::where('log_name', 'auth')
            ->where('event', 'login_success')
            ->firstOrFail();

        // The point of trusting proxies: the audit trail shows the person, not
        // the Cloudflare edge server.
        $this->assertSame(self::CLIENT_IP, data_get($log->properties, 'ip'));
        $this->assertSame('SA', $log->country);
    }

    public function test_a_forged_country_header_from_an_untrusted_client_is_not_recorded(): void
    {
        $user = User::factory()->create(['password' => Hash::make('password123')]);

        $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.7',
            'HTTP_X_FORWARDED_FOR' => self::CLIENT_IP,
            'HTTP_CF_IPCOUNTRY' => 'sa',
        ])->post('/login', [
            'email' => $user->email,
            'password' => 'password123',
        ]);

        $log = ActivityLog::where('log_name', 'auth')
            ->where('event', 'login_success')
            ->firstOrFail();

        $this->assertSame('198.51.100.7', data_get($log->properties, 'ip'));
        $this->assertNotSame('SA', $log->country);
    }

    /**
     * Build a request, run it through the real middleware stack so the
     * framework resolves the client IP, and hand it back.
     */
    private function requestFrom(
        string $remoteAddress,
        ?string $forwardedFor = null,
        ?string $country = null
    ): Request {
        $server = [
            'REMOTE_ADDR' => $remoteAddress,
            'HTTP_HOST' => 'fieldt360.test',
        ];

        if ($forwardedFor !== null) {
            $server['HTTP_X_FORWARDED_FOR'] = $forwardedFor;
        }

        if ($country !== null) {
            $server['HTTP_CF_IPCOUNTRY'] = $country;
        }

        $request = Request::create('/up', 'GET', [], [], [], $server);

        app(Kernel::class)->handle($request);

        return $request;
    }
}
