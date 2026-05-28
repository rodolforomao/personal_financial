<?php

namespace App\Providers;

use App\Application\Services\NavigationMenuService;
use App\Services\RbacBootstrap;
use App\Core\Support\HttpClientOptions;
use App\Models\User;
use App\Policies\CompanyPolicy;
use App\Policies\OperationPolicy;
use App\Policies\PermissionPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\RolePolicy;
use App\Policies\TransactionPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Finance\Infrastructure\Models\Transaction;
use Modules\Operations\Infrastructure\Models\Operation;
use Modules\Projects\Infrastructure\Models\Project;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            DB::prohibitDestructiveCommands(
                app()->runningUnitTests() === false
            );
        }

        Paginator::useBootstrapFive();

        if (! $this->app->runningUnitTests() && ! RbacBootstrap::isProvisioned()) {
            try {
                RbacBootstrap::sync();
            } catch (\Throwable) {
                // Banco indisponível no boot — setup-local / rbac:sync corrige depois.
            }
        }

        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Operation::class, OperationPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Permission::class, PermissionPolicy::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
        RateLimiter::for('login', function (Request $request) {
            $email = (string) $request->input('email', '');

            return Limit::perMinute(10)->by(strtolower($email).'|'.$request->ip());
        });

        Http::macro('external', function () {
            return Http::withOptions(HttpClientOptions::verify());
        });

        View::composer('partials.sidebar', function ($view) {
            $view->with('sidebarMenu', app(NavigationMenuService::class)->forUser(auth()->user()));
        });
    }
}
