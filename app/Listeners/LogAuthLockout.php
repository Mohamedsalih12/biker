<?php

namespace App\Listeners;

use App\Support\Audit;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\RateLimiter;

class LogAuthLockout
{
    public function handle(Lockout $event): void
    {
        // Get the seconds remaining from the RateLimiter directly
        $seconds = RateLimiter::availableIn($event->request->fingerprint());

        Audit::log('auth.lockout', [
            'seconds' => $seconds,
        ], null, null, $event->request);
    }
}
