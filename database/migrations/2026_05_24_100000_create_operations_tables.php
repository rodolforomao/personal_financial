<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            /** Lançamentos desta operação não entram no dashboard principal */
            $table->boolean('exclude_from_main_dashboard')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['workspace_id', 'slug']);
        });

        Schema::create('operation_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('code', 32)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('operation_id')->nullable()->after('project_id')->constrained()->nullOnDelete();
            $table->foreignId('operation_unit_id')->nullable()->after('operation_id')->constrained()->nullOnDelete();
            $table->index(['workspace_id', 'operation_id']);
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['operation_id']);
            $table->dropForeign(['operation_unit_id']);
            $table->dropColumn(['operation_id', 'operation_unit_id']);
        });

        Schema::dropIfExists('operation_units');
        Schema::dropIfExists('operations');
    }
};
