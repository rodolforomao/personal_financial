<?php

use App\Http\Controllers\Api\AuditController as ApiAuditController;
use App\Http\Controllers\Api\AuthController as ApiAuthController;
use App\Http\Controllers\Api\PermissionController as ApiPermissionController;
use App\Http\Controllers\Api\RoleController as ApiRoleController;
use App\Http\Controllers\Api\SessionController as ApiSessionController;
use App\Http\Controllers\Api\TeamController as ApiTeamController;
use App\Http\Controllers\Api\TokenController as ApiTokenController;
use App\Http\Controllers\Api\UserController as ApiUserController;
use Illuminate\Support\Facades\Route;
use Modules\Alerts\Presentation\Http\Controllers\AlertController;
use Modules\Categorization\Presentation\Http\Controllers\CategoryController;
use Modules\Companies\Presentation\Http\Controllers\CompanyController;
use Modules\Finance\Presentation\Http\Controllers\DashboardController;
use Modules\Finance\Presentation\Http\Controllers\FinancialAccountController;
use Modules\Finance\Presentation\Http\Controllers\RecurringItemController;
use Modules\Finance\Presentation\Http\Controllers\StatementImportController;
use Modules\Finance\Presentation\Http\Controllers\TransactionController;
use Modules\Intelligence\Presentation\Http\Controllers\AiAssistantController;
use Modules\Intelligence\Presentation\Http\Controllers\AiInsightController;
use Modules\OCR\Presentation\Http\Controllers\DocumentController;
use Modules\Projects\Presentation\Http\Controllers\ProjectController;

Route::post('/auth/register', [ApiAuthController::class, 'register']);
Route::post('/auth/login', [ApiAuthController::class, 'login'])->middleware('throttle:login');
Route::post('/team/invite/accept', [ApiTeamController::class, 'acceptInvite']);

Route::middleware(['auth:sanctum', 'workspace'])->group(function () {
    Route::get('/auth/me', [ApiAuthController::class, 'me']);
    Route::post('/auth/logout', [ApiAuthController::class, 'logout']);
    Route::post('/auth/switch-workspace', [ApiAuthController::class, 'switchWorkspace']);

    Route::middleware('permission:users.view')->group(function () {
        Route::get('/users', [ApiUserController::class, 'index']);
        Route::get('/users/{user}', [ApiUserController::class, 'show']);
        Route::get('/team', [ApiTeamController::class, 'index']);
    });
    Route::post('/users', [ApiUserController::class, 'store'])->middleware('permission:users.create');
    Route::patch('/users/{user}', [ApiUserController::class, 'update'])->middleware('permission:users.update');
    Route::put('/users/{user}', [ApiUserController::class, 'update'])->middleware('permission:users.update');
    Route::delete('/users/{user}', [ApiUserController::class, 'destroy'])->middleware('permission:users.delete');

    Route::middleware('permission:users.invite')->group(function () {
        Route::get('/team/invites', [ApiTeamController::class, 'invites']);
        Route::post('/team/invite', [ApiTeamController::class, 'invite']);
        Route::delete('/team/invites/{invite}', [ApiTeamController::class, 'revokeInvite']);
    });
    Route::patch('/team/{user}', [ApiTeamController::class, 'update'])->middleware('permission:users.update');
    Route::delete('/team/{user}', [ApiTeamController::class, 'destroy'])->middleware('permission:users.delete');

    Route::middleware('permission:sessions.view')->group(function () {
        Route::get('/team/sessions', [ApiSessionController::class, 'index']);
        Route::get('/sessions', [ApiSessionController::class, 'index']);
    });
    Route::delete('/team/sessions/{session}', [ApiSessionController::class, 'destroy'])
        ->middleware('permission:sessions.revoke,sessions.view');
    Route::delete('/sessions/{session}', [ApiSessionController::class, 'destroy'])
        ->middleware('permission:sessions.revoke,sessions.view');

    Route::middleware('permission:tokens.manage')->group(function () {
        Route::get('/team/tokens', [ApiTokenController::class, 'index']);
        Route::post('/team/tokens', [ApiTokenController::class, 'store']);
        Route::delete('/team/tokens/{token}', [ApiTokenController::class, 'destroy']);
        Route::get('/tokens', [ApiTokenController::class, 'index']);
        Route::post('/tokens', [ApiTokenController::class, 'store']);
        Route::delete('/tokens/{token}', [ApiTokenController::class, 'destroy']);
    });

    Route::get('/team/audit', [ApiAuditController::class, 'index'])
        ->middleware('permission:audit.view');
    Route::get('/audit', [ApiAuditController::class, 'index'])
        ->middleware('permission:audit.view');

    Route::middleware('permission:roles.view')->group(function () {
        Route::get('/roles', [ApiRoleController::class, 'index']);
        Route::get('/roles/{role}', [ApiRoleController::class, 'show']);
    });
    Route::post('/roles', [ApiRoleController::class, 'store'])->middleware('permission:roles.create');
    Route::patch('/roles/{role}', [ApiRoleController::class, 'update'])->middleware('permission:roles.update');
    Route::put('/roles/{role}', [ApiRoleController::class, 'update'])->middleware('permission:roles.update');
    Route::delete('/roles/{role}', [ApiRoleController::class, 'destroy'])->middleware('permission:roles.delete');

    Route::get('/permissions', [ApiPermissionController::class, 'index'])
        ->middleware('permission:permissions.view');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('/categories', [CategoryController::class, 'store'])->name('categories.store');
    Route::get('/accounts', [FinancialAccountController::class, 'index'])->name('accounts.index');
    Route::post('/accounts', [FinancialAccountController::class, 'store'])->name('accounts.store');
    Route::get('/recurring-items', [RecurringItemController::class, 'index'])->name('recurring-items.index');
    Route::post('/imports/ofx', [StatementImportController::class, 'importOfx'])->name('imports.ofx');
    Route::post('/imports/csv', [StatementImportController::class, 'importCsv'])->name('imports.csv');
    Route::apiResource('transactions', TransactionController::class);
    Route::apiResource('companies', CompanyController::class);
    Route::apiResource('projects', ProjectController::class);
    Route::apiResource('documents', DocumentController::class)->only(['index', 'store', 'show']);
    Route::get('/alerts', [AlertController::class, 'index']);
    Route::patch('/alerts/{alert}/read', [AlertController::class, 'markRead']);
    Route::get('/ai/insights', [AiInsightController::class, 'index']);
    Route::post('/ai/assistant', [AiAssistantController::class, 'ask']);
    Route::post('/ai/analyze', [AiInsightController::class, 'triggerAnalysis']);
});
