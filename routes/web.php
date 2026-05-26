<?php

use App\Http\Controllers\Web\AiController;
use App\Http\Controllers\Web\AiSettingsController;
use App\Http\Controllers\Web\AlertController;
use App\Http\Controllers\Web\AssetController;
use App\Http\Controllers\Web\AuthController;
use App\Http\Controllers\Web\CategorizationRuleController;
use App\Http\Controllers\Web\CategoryController;
use App\Http\Controllers\Web\CltSalaryController;
use App\Http\Controllers\Web\CompanyController;
use App\Http\Controllers\Web\DashboardController;
use App\Http\Controllers\Web\DataHygieneController;
use App\Http\Controllers\Web\DocumentController;
use App\Http\Controllers\Web\IntegrationSettingsController;
use App\Http\Controllers\Web\ObservabilityController;
use App\Http\Controllers\Web\OperationController;
use App\Http\Controllers\Web\PlatformSettingsController;
use App\Http\Controllers\Web\PlatformUserController;
use App\Http\Controllers\Web\ProjectController;
use App\Http\Controllers\Web\ReceiptExtractController;
use App\Http\Controllers\Web\StatementImportController;
use App\Http\Controllers\Web\SubscriptionPaymentController;
use App\Http\Controllers\Web\TransactionController;
use App\Http\Controllers\Web\TransactionReceiptController;
use App\Http\Middleware\SetWebWorkspace;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return auth()->check()
        ? redirect()->route('dashboard')
        : view('welcome');
});

Route::middleware('guest')->group(function () {
    Route::get('login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('register', [AuthController::class, 'register'])->name('register.store');
});

Route::middleware(['auth', SetWebWorkspace::class])->group(function () {
    Route::post('logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('subscription/pending', [AuthController::class, 'pendingSubscription'])->name('subscription.pending');
    Route::post('subscription/payment', [SubscriptionPaymentController::class, 'store'])->name('subscription.payment.store');
    Route::post('subscription/payment/check', [SubscriptionPaymentController::class, 'check'])->name('subscription.payment.check');
    Route::get('subscription/payments/{payment}/qr-code.svg', [SubscriptionPaymentController::class, 'qrCode'])->name('subscription.payment.qr-code');

    Route::middleware('active.access')->group(function () {
        Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
        Route::post('dashboard/filter', [DashboardController::class, 'updateFilter'])->name('dashboard.filter');

        Route::get('data-hygiene', [DataHygieneController::class, 'index'])->name('data-hygiene.index');
        Route::post('data-hygiene/fix', [DataHygieneController::class, 'applyFix'])->name('data-hygiene.fix');
        Route::post('data-hygiene/bulk-assign', [DataHygieneController::class, 'bulkAssign'])->name('data-hygiene.bulk-assign');

        Route::get('categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::post('categories', [CategoryController::class, 'store'])->name('categories.store');

        Route::get('categorization-rules', [CategorizationRuleController::class, 'index'])->name('categorization-rules.index');
        Route::post('categorization-rules', [CategorizationRuleController::class, 'store'])->name('categorization-rules.store');
        Route::post('categorization-rules/shared/{categorizationRule}/accept', [CategorizationRuleController::class, 'acceptShared'])->name('categorization-rules.shared.accept');
        Route::put('categorization-rules/{categorizationRule}', [CategorizationRuleController::class, 'update'])->name('categorization-rules.update');
        Route::patch('categorization-rules/{categorizationRule}/toggle', [CategorizationRuleController::class, 'toggle'])->name('categorization-rules.toggle');
        Route::delete('categorization-rules/{categorizationRule}', [CategorizationRuleController::class, 'destroy'])->name('categorization-rules.destroy');

        Route::get('transactions', [TransactionController::class, 'index'])->name('transactions.index');
        Route::post('transactions/bulk-categorize', [TransactionController::class, 'bulkCategorize'])->name('transactions.bulk-categorize');
        Route::post('transactions/bulk-update', [TransactionController::class, 'bulkUpdate'])->name('transactions.bulk-update');
        Route::post('transactions/bulk-destroy', [TransactionController::class, 'bulkDestroy'])->name('transactions.bulk-destroy');
        Route::post('transactions/remove-estorno-pairs', [TransactionController::class, 'removeEstornoPairs'])->name('transactions.remove-estorno-pairs');
        Route::get('transactions/create', [TransactionController::class, 'create'])->name('transactions.create');
        Route::post('transactions/receipt-extract/preview', [ReceiptExtractController::class, 'preview'])->name('transactions.receipt-extract.preview');
        Route::post('transactions/suggest-category', [TransactionController::class, 'suggestCategory'])->name('transactions.suggest-category');
        Route::post('transactions', [TransactionController::class, 'store'])->name('transactions.store');
        Route::get('transactions/{transaction}/edit', [TransactionController::class, 'edit'])->name('transactions.edit');
        Route::put('transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
        Route::delete('transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');
        Route::post('transactions/{transaction}/receipts/extract', [ReceiptExtractController::class, 'storeAndPrefill'])->name('transactions.receipts.extract');
        Route::post('transactions/{transaction}/receipts', [TransactionReceiptController::class, 'store'])->name('transactions.receipts.store');
        Route::post('transactions/{transaction}/receipts/link', [TransactionReceiptController::class, 'link'])->name('transactions.receipts.link');
        Route::get('transactions/{transaction}/receipts/{document}', [TransactionReceiptController::class, 'show'])->name('transactions.receipts.show');
        Route::delete('transactions/{transaction}/receipts/{document}', [TransactionReceiptController::class, 'destroy'])->name('transactions.receipts.destroy');

        Route::get('companies', [CompanyController::class, 'index'])->name('companies.index');
        Route::get('companies/create', [CompanyController::class, 'create'])->name('companies.create');
        Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::get('companies/{company}/edit', [CompanyController::class, 'edit'])->name('companies.edit');
        Route::put('companies/{company}', [CompanyController::class, 'update'])->name('companies.update');
        Route::delete('companies/{company}', [CompanyController::class, 'destroy'])->name('companies.destroy');

        Route::get('clt-salaries', [CltSalaryController::class, 'index'])->name('clt-salaries.index');
        Route::post('clt-salaries', [CltSalaryController::class, 'store'])->name('clt-salaries.store');
        Route::put('clt-salaries/{cltSalary}', [CltSalaryController::class, 'update'])->name('clt-salaries.update');
        Route::post('clt-salaries/periods/{period}/confirm', [CltSalaryController::class, 'confirm'])->name('clt-salaries.periods.confirm');
        Route::post('clt-salaries/periods/{period}/skip', [CltSalaryController::class, 'skip'])->name('clt-salaries.periods.skip');

        Route::get('operations', [OperationController::class, 'index'])->name('operations.index');
        Route::get('operations/create', [OperationController::class, 'create'])->name('operations.create');
        Route::post('operations', [OperationController::class, 'store'])->name('operations.store');
        Route::get('operations/{operation}', [OperationController::class, 'show'])->name('operations.show');
        Route::get('operations/{operation}/edit', [OperationController::class, 'edit'])->name('operations.edit');
        Route::put('operations/{operation}', [OperationController::class, 'update'])->name('operations.update');
        Route::post('operations/{operation}/units', [OperationController::class, 'storeUnit'])->name('operations.units.store');
        Route::delete('operations/{operation}/units/{unit}', [OperationController::class, 'destroyUnit'])->name('operations.units.destroy');

        Route::get('projects', [ProjectController::class, 'index'])->name('projects.index');
        Route::get('projects/create', [ProjectController::class, 'create'])->name('projects.create');
        Route::post('projects', [ProjectController::class, 'store'])->name('projects.store');

        Route::get('statements', [StatementImportController::class, 'index'])->name('statements.index');
        Route::post('statements', [StatementImportController::class, 'store'])->name('statements.store');
        Route::get('statements/import/csv-map', [StatementImportController::class, 'csvMap'])->name('statements.import.csv-map');
        Route::post('statements/import/csv-map', [StatementImportController::class, 'csvImport'])->name('statements.import.csv-submit');
        Route::get('statements/{statementImport}/reconcile', [StatementImportController::class, 'reconcile'])->name('statements.reconcile');
        Route::post('statements/{statementImport}/confirm-all', [StatementImportController::class, 'confirmAllSuggested'])->name('statements.confirm-all');
        Route::post('statements/{statementImport}/import-unmatched', [StatementImportController::class, 'importAllUnmatched'])->name('statements.import-unmatched');
        Route::post('statements/{statementImport}/lines/{line}/match', [StatementImportController::class, 'confirmMatch'])->name('statements.lines.match');
        Route::post('statements/{statementImport}/lines/{line}/import', [StatementImportController::class, 'importLine'])->name('statements.lines.import');
        Route::post('statements/{statementImport}/lines/{line}/skip', [StatementImportController::class, 'skipLine'])->name('statements.lines.skip');

        Route::get('assets', [AssetController::class, 'index'])->name('assets.index');
        Route::post('assets', [AssetController::class, 'store'])->name('assets.store');
        Route::put('assets/{asset}', [AssetController::class, 'update'])->name('assets.update');
        Route::delete('assets/{asset}', [AssetController::class, 'destroy'])->name('assets.destroy');

        Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('documents', [DocumentController::class, 'store'])->name('documents.store');
        Route::get('documents/{document}', [DocumentController::class, 'show'])->name('documents.show');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
        Route::post('documents/{document}/extract', [ReceiptExtractController::class, 'previewDocument'])->name('documents.extract');

        Route::get('alerts', [AlertController::class, 'index'])->name('alerts.index');
        Route::patch('alerts/{alert}/read', [AlertController::class, 'markRead'])->name('alerts.read');

        Route::get('observability', [ObservabilityController::class, 'index'])->name('observability.index');

        Route::get('integrations/notifications', [IntegrationSettingsController::class, 'edit'])->name('integrations.settings');
        Route::post('integrations/notifications', [IntegrationSettingsController::class, 'update'])->name('integrations.settings.update');
        Route::get('integrations/notifications/test-telegram', fn () => redirect()
            ->route('integrations.settings')
            ->with('info', 'Use o botão "Testar Telegram" dentro da tela de integrações.'))->name('integrations.test.telegram.get');
        Route::post('integrations/notifications/test-telegram', [IntegrationSettingsController::class, 'testTelegram'])->name('integrations.test.telegram');
        Route::post('integrations/notifications/test-whatsapp', [IntegrationSettingsController::class, 'testWhatsApp'])->name('integrations.test.whatsapp');
        Route::get('integrations/gmail/connect', [IntegrationSettingsController::class, 'connectGmail'])->name('integrations.gmail.connect');
        Route::get('integrations/gmail/callback', [IntegrationSettingsController::class, 'gmailCallback'])->name('integrations.gmail.callback');
        Route::post('integrations/gmail/sync', [IntegrationSettingsController::class, 'syncGmail'])->name('integrations.gmail.sync');
        Route::delete('integrations/gmail', [IntegrationSettingsController::class, 'disconnectGmail'])->name('integrations.gmail.disconnect');
        Route::post('integrations/proxy-check', [IntegrationSettingsController::class, 'proxyCheck'])->name('integrations.proxy.check');

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
            Route::patch('users/{user}/access', [PlatformUserController::class, 'updateAccess'])->name('users.update-access');
            Route::post('users/{user}/payments', [PlatformUserController::class, 'confirmPayment'])->name('users.confirm-payment');
            Route::post('subscription-profiles', [PlatformUserController::class, 'storeProfile'])->name('subscription-profiles.store');
            Route::put('subscription-profiles/{subscriptionProfile}', [PlatformUserController::class, 'updateProfile'])->name('subscription-profiles.update');
            Route::get('settings', [PlatformSettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [PlatformSettingsController::class, 'update'])->name('settings.update');
        });
    });
});
