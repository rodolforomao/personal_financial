<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('currency', 3)->default('BRL');
            $table->string('timezone')->default('America/Sao_Paulo');
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('workspace_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role')->default('member');
            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('phone')->nullable()->after('email');
            $table->boolean('two_factor_enabled')->default(false)->after('password');
            $table->text('two_factor_secret')->nullable()->after('two_factor_enabled');
            $table->json('preferences')->nullable()->after('two_factor_secret');
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key');
            $table->boolean('enabled')->default(false);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'key']);
        });

        Schema::create('financial_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('checking');
            $table->string('bank_name')->nullable();
            $table->string('account_number')->nullable();
            $table->decimal('opening_balance', 15, 2)->default(0);
            $table->decimal('current_balance', 15, 2)->default(0);
            $table->string('currency', 3)->default('BRL');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->string('type')->default('expense');
            $table->string('color')->nullable();
            $table->string('icon')->nullable();
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('categorization_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('match_type')->default('contains');
            $table->string('pattern');
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamps();
        });

        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('client');
            $table->string('document')->nullable();
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->string('status')->default('active');
            $table->decimal('expected_monthly_revenue', 15, 2)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('company_contracts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->decimal('amount', 15, 2);
            $table->string('recurrence')->default('monthly');
            $table->date('starts_at');
            $table->date('ends_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('status')->default('active');
            $table->decimal('budget', 15, 2)->nullable();
            $table->decimal('expected_return', 15, 2)->nullable();
            $table->decimal('total_cost', 15, 2)->default(0);
            $table->decimal('total_revenue', 15, 2)->default(0);
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('financial_account_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('status')->default('pending');
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3)->default('BRL');
            $table->string('description');
            $table->string('counterparty')->nullable();
            $table->date('transaction_date');
            $table->date('due_date')->nullable();
            $table->date('paid_at')->nullable();
            $table->boolean('is_recurring')->default(false);
            $table->string('source')->default('manual');
            $table->string('external_id')->nullable();
            $table->json('metadata')->nullable();
            $table->decimal('categorization_confidence', 5, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['workspace_id', 'transaction_date']);
            $table->index(['workspace_id', 'type', 'status']);
        });

        Schema::create('recurring_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type');
            $table->string('title');
            $table->decimal('amount', 15, 2);
            $table->string('frequency')->default('monthly');
            $table->unsignedTinyInteger('day_of_month')->nullable();
            $table->date('next_due_at');
            $table->date('last_occurrence_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('alert_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type')->default('investment');
            $table->decimal('current_value', 15, 2);
            $table->decimal('acquisition_value', 15, 2)->nullable();
            $table->date('acquired_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('cash_flow_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->decimal('total_income', 15, 2)->default(0);
            $table->decimal('total_expense', 15, 2)->default(0);
            $table->decimal('net_cash_flow', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);
            $table->json('breakdown')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'snapshot_date']);
        });

        Schema::create('financial_forecasts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->date('forecast_date');
            $table->unsignedSmallInteger('horizon_days')->default(90);
            $table->decimal('projected_income', 15, 2)->default(0);
            $table->decimal('projected_expense', 15, 2)->default(0);
            $table->decimal('projected_balance', 15, 2)->default(0);
            $table->string('risk_level')->default('low');
            $table->json('details')->nullable();
            $table->timestamps();
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name');
            $table->string('storage_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('document_type')->default('receipt');
            $table->string('status')->default('pending');
            $table->json('ocr_result')->nullable();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('ocr_jobs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('queued');
            $table->string('provider')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_insights', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('severity')->default('info');
            $table->string('title');
            $table->text('summary');
            $table->json('payload')->nullable();
            $table->json('suggested_actions')->nullable();
            $table->string('provider')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_resolved')->default(false);
            $table->timestamp('detected_at');
            $table->timestamps();
            $table->index(['workspace_id', 'type', 'severity']);
        });

        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('context')->default('assistant');
            $table->string('title')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->cascadeOnDelete();
            $table->string('role');
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('alerts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('type');
            $table->string('severity')->default('warning');
            $table->string('title');
            $table->text('message');
            $table->json('context')->nullable();
            $table->boolean('is_read')->default(false);
            $table->boolean('is_sent')->default(false);
            $table->timestamp('triggered_at');
            $table->timestamps();
            $table->index(['workspace_id', 'is_read', 'severity']);
        });

        Schema::create('alert_channels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel');
            $table->string('destination');
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('integration_connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('provider');
            $table->string('status')->default('inactive');
            $table->text('credentials')->nullable();
            $table->json('settings')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();
            $table->unique(['workspace_id', 'provider']);
        });

        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('event');
            $table->json('payload')->nullable();
            $table->string('status')->default('received');
            $table->timestamps();
        });

        Schema::create('system_health_metrics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete();
            $table->string('metric');
            $table->decimal('value', 15, 4);
            $table->json('tags')->nullable();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        $tables = [
            'system_health_metrics', 'webhook_logs', 'integration_connections',
            'alert_channels', 'alerts', 'ai_messages', 'ai_conversations',
            'ai_insights', 'ocr_jobs', 'documents', 'financial_forecasts',
            'cash_flow_snapshots', 'assets', 'recurring_items', 'transactions',
            'projects', 'company_contracts', 'companies', 'categorization_rules',
            'categories', 'financial_accounts', 'feature_flags', 'workspace_user',
            'workspaces',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['phone', 'two_factor_enabled', 'two_factor_secret', 'preferences']);
        });
    }
};
