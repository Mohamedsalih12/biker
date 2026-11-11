<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Models\User;
use App\Services\AuditService;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    use AuthenticatesUsers;

    public function __construct(
        protected AuditService $audit
    ) {}

    public function loginIndex()
    {
        return view('auth.login');
    }

    public function login(LoginRequest $request): RedirectResponse
    {
        $throttleKey = strtolower((string) $request->input('email')).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, 3)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            $this->audit->failure(
                logName: 'auth',
                event: 'login_rate_limited',
                description: 'Login blocked due to too many attempts',
                properties: [
                    'email' => $request->input('email'),
                    'wait_seconds' => $seconds,
                ]
            );

            throw ValidationException::withMessages([
                $this->username() => ["Too many login attempts. Try again in {$seconds} seconds."],
            ])->status(429);
        }

        $validated = $request->validated();

        $credentials = [
            'email' => $validated['email'],
            'password' => $validated['password'],
        ];

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            $this->audit->failure(
                logName: 'auth',
                event: 'login_failed',
                description: 'Login failed due to invalid credentials',
                properties: [
                    'email' => $validated['email'],
                ]
            );

            throw ValidationException::withMessages([
                'email' => 'The provided credentials do not match our records.',
            ]);
        }

        /** @var User $user */
        $user = Auth::user();

        if (! $user->active_status) {
            $this->audit->failure(
                logName: 'auth',
                event: 'login_inactive',
                description: 'Login blocked for inactive user',
                properties: [
                    'email' => $user->email,
                ]
            );

            Auth::logout();
            RateLimiter::clear($throttleKey);

            throw ValidationException::withMessages([
                'email' => 'Your account is not active. Please contact the administrator.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->forget([
            config('google2fa.session_var', 'google2fa'),
            'google2fa_passed',
            '2fa_secret',
            '2fa_target_user_id',
            '2fa_setup_user_id',
        ]);
        $request->session()->regenerate();

        $this->audit->success(
            logName: 'auth',
            event: 'login_success',
            description: 'User logged in successfully',
            subject: $user,
            properties: [
                'email' => $user->email,
                'shift_name' => $validated['shift_name'] ?? null,
            ],
            causer: $user
        );

        if (empty($user->google2fa_secret)) {
            return redirect()->route('2fa.setup')->with('message', 'Please register your authenticator device to complete 2FA setup.');
        }

        $loginLogId = DB::table('login_logs')->insertGetId([
            'user_id' => $user->id,
            'session_id' => $request->session()->getId(),
            'pos_code' => 'others',
            'login_time' => now(),
            'ip_address' => $request->ip(),
            'country' => $this->audit->countryCode($request),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        session(['login_log_id' => $loginLogId]);

        return redirect('/xpress/warehouse')->with('message', 'Logged in Successfully');
    }

    public function logout(Request $request)
    {
        // ✅ Update logout time
        if (session()->has('login_log_id')) {
            DB::table('login_logs')
                ->where('id', session('login_log_id'))
                ->update([
                    'logout_time' => now(),
                    'updated_at' => now(),
                ]);
        }

        // Default Laravel logout
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login')->with('message', 'Logged out successfully');
    }
}
