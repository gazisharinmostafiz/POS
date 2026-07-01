<?php

namespace App\Providers;

use App\Support\Roles;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        // 'App\Models\Model' => 'App\Policies\ModelPolicy',
    ];

    /**
     * Register any authentication / authorization services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerPolicies();

        Gate::define('access-platform-area', fn ($user) => $user->hasRole(Roles::PLATFORM_OWNER));
        Gate::define('access-tenant-admin-area', fn ($user) => $user->hasRole([Roles::ADMIN, Roles::SUPER_ADMIN]));
        Gate::define('access-waiter-pos', fn ($user) => $user->hasRole(Roles::WAITER));
        Gate::define('access-counter-screen', fn ($user) => $user->hasRole([Roles::COUNTER, Roles::ADMIN, Roles::SUPER_ADMIN]));
        Gate::define('access-kitchen-screen', fn ($user) => $user->hasRole(Roles::KITCHEN));
    }
}
