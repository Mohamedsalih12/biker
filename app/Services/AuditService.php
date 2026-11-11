<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class AuditService
{
    public function log(
        string $logName,
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        $causer = null
    ): void {
        $activity = activity($logName)
            ->event($event)
            ->withProperties($this->baseProperties($properties));

        $resolvedCauser = $causer ?: Auth::user();

        if ($resolvedCauser) {
            $activity->causedBy($resolvedCauser);
        }

        if ($subject) {
            $activity->performedOn($subject);
        }

        $activityLog = $activity->log($description);
        $country = $this->countryCode(request());

        if ($country !== null && $activityLog instanceof ActivityLog) {
            $activityLog->country = $country;
            $activityLog->save();
        }
    }

    public function success(
        string $logName,
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        $causer = null
    ): void {
        $this->log(
            logName: $logName,
            event: $event,
            description: $description,
            subject: $subject,
            properties: array_merge(['status' => 'success'], $properties),
            causer: $causer
        );
    }

    public function failure(
        string $logName,
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        $causer = null
    ): void {
        $this->log(
            logName: $logName,
            event: $event,
            description: $description,
            subject: $subject,
            properties: array_merge(['status' => 'failed'], $properties),
            causer: $causer
        );
    }

    public function security(
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        $causer = null
    ): void {
        $this->log(
            logName: 'security',
            event: $event,
            description: $description,
            subject: $subject,
            properties: $properties,
            causer: $causer
        );
    }

    public function auth(
        string $event,
        string $description,
        ?Model $subject = null,
        array $properties = [],
        $causer = null
    ): void {
        $this->log(
            logName: 'auth',
            event: $event,
            description: $description,
            subject: $subject,
            properties: $properties,
            causer: $causer
        );
    }

    protected function baseProperties(array $properties = []): array
    {
        /** @var Request $request */
        $request = request();

        return array_merge([
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'route_name' => optional($request->route())->getName(),
            'route_uri' => optional($request->route())->uri(),
            'session_id' => session()->getId(),
        ], $properties);
    }

    public function countryCode(Request $request): ?string
    {
        $country = $this->proxyHeader($request, 'CF-IPCountry')
            ?? $this->proxyHeader($request, 'X-Country-Code');

        if ($country !== null) {
            return $country;
        }

        return $this->countryCodeForIp($request->ip());
    }

    /**
     * Read a header that is only meaningful when a trusted proxy sent the
     * request. Honouring CF-IPCountry from an untrusted source would let any
     * caller write an arbitrary country into the audit log.
     */
    protected function proxyHeader(Request $request, string $header): ?string
    {
        if (! $request->isFromTrustedProxy()) {
            return null;
        }

        $value = $request->header($header);

        if (! is_string($value)) {
            return null;
        }

        $value = strtoupper(trim($value));

        return preg_match('/^[A-Z]{2}$/', $value) === 1 ? $value : null;
    }

    public function countryCodeForIp(?string $ip): ?string
    {
        if (! is_string($ip) || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return null;
        }

        return Cache::remember("country-code:{$ip}", now()->addDay(), function () use ($ip): ?string {
            $response = Http::acceptJson()
                ->timeout(2)
                ->get("https://ipwho.is/{$ip}");

            if (! $response->successful() || $response->json('success') !== true) {
                return null;
            }

            $country = strtoupper((string) $response->json('country_code'));

            return preg_match('/^[A-Z]{2}$/', $country) === 1 ? $country : null;
        });
    }
}
