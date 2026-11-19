<?php

namespace App\Providers;

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

        \Illuminate\Support\Facades\Gate::before(function (\App\Models\User $user, string $ability) {
            if ($user->is_platform_admin) {
                return true;
            }

            if ($user->hasPermissionTo($ability)) {
                return true;
            }

            return null;
        });

        \Illuminate\Support\Facades\Gate::policy(\App\Models\Student::class, \App\Policies\StudentPolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\FeeInvoice::class, \App\Policies\FeeInvoicePolicy::class);
        \Illuminate\Support\Facades\Gate::policy(\App\Models\FileUpload::class, \App\Policies\FileUploadPolicy::class);
    }
}
