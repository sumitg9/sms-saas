<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(\App\Services\Tenancy\TenantContext::class, function () {
            return new \App\Services\Tenancy\TenantContext();
        });

        $this->app->singleton(\App\Services\Tenancy\TenantResolver::class, function ($app) {
            return new \App\Services\Tenancy\TenantResolver($app->make(\App\Services\Tenancy\TenantContext::class));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Laravel\Sanctum\Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);

        // Security rate limiters
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        RateLimiter::for('auth', function (Request $request) {
            return Limit::perMinute(15)->by($request->ip());
        });

        RateLimiter::for('exports', function (Request $request) {
            return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
        });

        Gate::before(function (\App\Models\User $user, string $ability) {
            if ($user->is_platform_admin) {
                return true;
            }

            if ($user->hasPermissionTo($ability)) {
                return true;
            }

            return null;
        });

        Gate::policy(\App\Models\Student::class, \App\Policies\StudentPolicy::class);
        Gate::policy(\App\Models\FeeInvoice::class, \App\Policies\FeeInvoicePolicy::class);
        Gate::policy(\App\Models\FileUpload::class, \App\Policies\FileUploadPolicy::class);
    }
}
