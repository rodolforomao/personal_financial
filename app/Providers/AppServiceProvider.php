<?php

namespace App\Providers;

use App\Core\Support\HttpClientOptions;
use App\Policies\CompanyPolicy;
use App\Policies\OperationPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TransactionPolicy;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Operations\Infrastructure\Models\Operation;
use Modules\Projects\Infrastructure\Models\Project;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use App\Application\Services\NavigationMenuService;
use Modules\Finance\Infrastructure\Models\Transaction;

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

        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Operation::class, OperationPolicy::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });

        Http::macro('external', function () {
            return Http::withOptions(HttpClientOptions::verify());
        });

        View::composer('partials.sidebar', function ($view) {
            $view->with('sidebarMenu', app(NavigationMenuService::class)->forUser(auth()->user()));
        });
    }
}
