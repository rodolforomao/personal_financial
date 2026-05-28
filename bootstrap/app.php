<?php

use App\Core\Http\Middleware\EnsurePermission;
use App\Core\Http\Middleware\EnsureWorkspaceAccess;
use App\Http\Middleware\EnsureActivePlatformAccess;
use App\Http\Middleware\EnsureAdminRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'workspace' => EnsureWorkspaceAccess::class,
            'permission' => EnsurePermission::class,
            'admin' => EnsureAdminRole::class,
            'active.access' => EnsureActivePlatformAccess::class,
        ]);
        $middleware->api(prepend: [
            EnsureFrontendRequestsAreStateful::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
