<?php

use Illuminate\Support\Facades\Route;
use Modules\Alerts\Presentation\Http\Controllers\AlertController;
use Modules\Companies\Presentation\Http\Controllers\CompanyController;
use Modules\Categorization\Presentation\Http\Controllers\CategoryController;
use Modules\Finance\Presentation\Http\Controllers\DashboardController;
use Modules\Finance\Presentation\Http\Controllers\FinancialAccountController;
use Modules\Finance\Presentation\Http\Controllers\RecurringItemController;
use Modules\Finance\Presentation\Http\Controllers\StatementImportController;
use Modules\Finance\Presentation\Http\Controllers\TransactionController;
use Modules\Intelligence\Presentation\Http\Controllers\AiAssistantController;
use Modules\Intelligence\Presentation\Http\Controllers\AiInsightController;
use Modules\OCR\Presentation\Http\Controllers\DocumentController;
use Modules\Projects\Presentation\Http\Controllers\ProjectController;

Route::middleware(['auth:sanctum', 'workspace'])->group(function () {
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
