<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscription_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->unsignedInteger('monthly_price_cents')->default(2000);
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('subscription_profile_id')
                ->nullable()
                ->after('is_platform_internal')
                ->constrained('subscription_profiles')
                ->nullOnDelete();
            $table->string('access_status')->default('active')->after('subscription_profile_id')->index();
            $table->timestamp('access_approved_at')->nullable()->after('access_status');
            $table->foreignId('access_approved_by')
                ->nullable()
                ->after('access_approved_at')
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('last_payment_at')->nullable()->after('access_approved_by');
            $table->timestamp('access_expires_at')->nullable()->after('last_payment_at');
        });

        Schema::create('user_access_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_profile_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('amount_cents');
            $table->string('currency', 3)->default('BRL');
            $table->string('status')->default('pending')->index();
            $table->string('provider')->nullable();
            $table->string('provider_payment_id')->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('billing_period_starts_at')->nullable();
            $table->timestamp('billing_period_ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_access_payments');

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['subscription_profile_id']);
            $table->dropForeign(['access_approved_by']);
            $table->dropColumn([
                'subscription_profile_id',
                'access_status',
                'access_approved_at',
                'access_approved_by',
                'last_payment_at',
                'access_expires_at',
            ]);
        });

        Schema::dropIfExists('subscription_profiles');
    }
};
