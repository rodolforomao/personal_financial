<?php

namespace App\Providers;

use App\Policies\CompanyPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TransactionPolicy;
use Modules\Companies\Infrastructure\Models\Company;
use Modules\Projects\Infrastructure\Models\Project;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Modules\Finance\Infrastructure\Models\Transaction;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Gate::policy(Transaction::class, TransactionPolicy::class);
        Gate::policy(Company::class, CompanyPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);

        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(120)->by($request->user()?->id ?: $request->ip());
        });
    }
}
