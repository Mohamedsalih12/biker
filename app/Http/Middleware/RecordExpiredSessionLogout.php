<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class RecordExpiredSessionLogout
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $sessionCookieName = (string) config('session.cookie');
        $encryptedSessionId = $request->cookies->get($sessionCookieName);

        if (! is_string($encryptedSessionId) || $encryptedSessionId === '') {
            return $next($request);
        }

        try {
            $decryptedSessionId = app('encrypter')->decrypt($encryptedSessionId, false);
            $sessionId = CookieValuePrefix::validate(
                $sessionCookieName,
                $decryptedSessionId,
                app('encrypter')->getAllKeys()
            );
        } catch (DecryptException) {
            return $next($request);
        }

        if (! is_string($sessionId) || $sessionId === '') {
            return $next($request);
        }

        $session = DB::table('sessions')->where('id', $sessionId)->first();

        if ($session && $session->last_activity <= Date::now()->subMinutes((int) config('session.lifetime'))->timestamp) {
            DB::table('login_logs')
                ->where('session_id', $sessionId)
                ->whereNull('logout_time')
                ->update([
                    'logout_time' => Date::now(),
                    'updated_at' => Date::now(),
                ]);
        }

        return $next($request);
    }
}
