<?php

use App\Http\Controllers\Web\AiController;
use App\Http\Controllers\Web\AiSettingsController;
use App\Http\Controllers\Web\AlertController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\CompanyController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\IntegrationSettingsController;
use App\Http\Controllers\Web\ObservabilityController;
use App\Http\Controllers\Web\PlatformUserController;
use App\Http\Controllers\Web\ProjectController;
use App\Http\Controllers\Web\TransactionController;
use App\Http\Middleware\SetWebWorkspace;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::middleware(['auth', SetWebWorkspace::class])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');

    Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
    Route::get('transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
    Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');

    Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
    Route::get('companies/create', [CompanyController::class, 'create'])->name('companies.create');
    Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');

    Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
    Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
    Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');

    Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
    Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');

    Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
    Route::patch('alerts/{alert}/read', [AlertController::class, 'markRead'])->name('alerts.read');

    Route::get('observability', [ObservabilityController::class, 'index'])->name('observability.index');

    Route::get('integrations/notifications', [IntegrationSettingsController::class, 'edit'])->name('integrations.settings');
    Route::post('integrations/notifications', [IntegrationSettingsController::class, 'update'])->name('integrations.settings.update');
    Route::post('integrations/notifications/test-telegram', [IntegrationSettingsController::class, 'testTelegram'])->name('integrations.test.telegram');
    Route::post('integrations/notifications/test-whatsapp', [IntegrationSettingsController::class, 'testWhatsApp'])->name('integrations.test.whatsapp');

    Route::get('ai/settings', [AiSettingsController::class, 'edit'])->name('ai.settings');
    Route::put('ai/settings', [AiSettingsController::class, 'update'])->name('ai.settings.update');
    Route::delete('ai/settings/key', [AiSettingsController::class, 'removeKey'])->name('ai.settings.remove-key');

    Route::get('ai/insights', [AiController::class, 'insights'])->name('ai.insights');
    Route::get('ai/assistant', [AiController::class, 'assistant'])->name('ai.assistant');
    Route::post('ai/ask', [AiController::class, 'ask'])->name('ai.ask');
    Route::post('ai/analyze', [AiController::class, 'analyze'])->name('ai.analyze');

    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('users', [PlatformUserController::class, 'index'])->name('users.index');
        Route::patch('users/{user}/internal', [PlatformUserController::class, 'toggleInternal'])->name('users.toggle-internal');
    });
});
