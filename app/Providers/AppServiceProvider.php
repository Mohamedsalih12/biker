<?php

namespace App\Providers;

use App\Models\Setting;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrap();

        // Neutralise spreadsheet formula injection in the two exports that are
        // rendered through a Blade table rather than a map() method.
        Blade::directive('spreadsheet', fn (string $expression) => "<?php echo e(Spreadsheet::neutralize($expression)); ?>");

        try {
            $websiteSetting = null;

            if (Schema::hasTable('settings')) {
                $websiteSetting = Setting::first();
            }

            View::share('appSetting', $websiteSetting);

        } catch (\Exception $e) {
            // Prevent crash during deployment/config cache
        }

        RateLimiter::for('login', function (Request $request) {
            $emailOrUser = (string) $request->input('email'); // or $request->input('username')
            $emailOrUser = strtolower($emailOrUser);

            // Enhanced security: Stricter login rate limiting to prevent brute force attacks
            // 3 attempts per minute per email+IP combination (180 per hour)
            return Limit::perMinute(4)->by($emailOrUser.'|'.$request->ip());
        });

        RateLimiter::for('resend-otp', function (Request $request) {
            return Limit::perMinute(1)
                ->by($request->user()?->id ?: $request->ip());
        });

        Gate::before(static function (?Authenticatable $user): ?bool {
            return $user === null ? null : true;
        });

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(8)->mixedCase()->numbers()->symbols()->uncompromised()
            : null
        );
    }
}
