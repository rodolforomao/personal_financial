<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clt_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('gross_amount', 15, 2)->nullable();
            $table->decimal('net_amount', 15, 2);
            $table->unsignedTinyInteger('payment_day')->default(5);
            $table->string('description_template')->default('Salário CLT');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'company_id']);
        });

        Schema::create('clt_salary_periods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('clt_salary_id')->constrained()->cascadeOnDelete();
            $table->date('reference_month');
            $table->decimal('gross_amount', 15, 2)->nullable();
            $table->decimal('net_amount', 15, 2);
            $table->string('status', 20)->default('pending');
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['clt_salary_id', 'reference_month']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clt_salary_periods');
        Schema::dropIfExists('clt_salaries');
    }
};
