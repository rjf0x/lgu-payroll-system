<?php

namespace App\Providers;

use App\Listeners\AuthenticationEventSubscriber;
use App\Models\Permission;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Blockchain\BesuBlockchainDriver;
use App\Services\Blockchain\BlockchainDriver;
use App\Services\Blockchain\SimulatedBlockchainDriver;
use App\Services\Blockchain\TestnetBlockchainDriver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(BlockchainDriver::class, function (): BlockchainDriver {
            return match (config('blockchain.driver')) {
                'besu' => new BesuBlockchainDriver,
                'testnet' => new TestnetBlockchainDriver,
                'simulated' => new SimulatedBlockchainDriver,
                default => throw new \InvalidArgumentException('Unknown blockchain driver.'),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        TrustProxies::at(config('deployment.trusted_proxies', []));
        $this->registerPermissionGates();
        $this->registerAuthenticationAuditing();
        $this->registerRateLimiters();
        View::composer(['layouts.app', 'layouts.print', 'payroll-runs.register', 'reports.payroll-summary'], function (\Illuminate\View\View $view): void {
            $view->with('systemSettings', SystemSetting::currentValues());
        });
    }

    /**
     * Expose every known permission name as a Gate ability so it can be used
     * with `Gate::allows()`, `@can`, and the `can:` route middleware.
     */
    private function registerPermissionGates(): void
    {
        foreach (Permission::names() as $permission) {
            Gate::define($permission, fn (User $user): bool => $user->hasPermissionTo($permission));
        }
    }

    private function registerAuthenticationAuditing(): void
    {
        Event::subscribe(AuthenticationEventSubscriber::class);
    }

    private function registerRateLimiters(): void
    {
        RateLimiter::for('login', function (Request $request): Limit {
            $key = Str::transliterate(
                Str::lower((string) $request->input('username')).'|'.$request->ip(),
            );

            return Limit::perMinute(5)->by($key);
        });
    }
}
