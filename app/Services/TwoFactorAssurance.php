<?php

namespace App\Services;

use Illuminate\Http\Request;
use PragmaRX\Google2FALaravel\Support\Authenticator;
use PragmaRX\Google2FALaravel\Support\Constants;

class TwoFactorAssurance
{
    public function ensureVerified(Request $request): void
    {
        $authenticator = new Authenticator($request);

        abort_unless(
            config('google2fa.enabled')
            && filled($request->user()?->google2fa_secret)
            && $request->session()->get(config('google2fa.session_var').'.'.Constants::SESSION_AUTH_PASSED, false)
            && $authenticator->isAuthenticated(),
            403,
            'Complete two-factor authentication before managing authenticator devices.'
        );
    }
}
