<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorization_rules', function (Blueprint $table) {
            $table->foreignId('operation_id')->nullable()->after('category_id')->constrained()->nullOnDelete();
            $table->foreignId('company_id')->nullable()->after('operation_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categorization_rules', function (Blueprint $table) {
            $table->dropForeign(['operation_id']);
            $table->dropForeign(['company_id']);
            $table->dropColumn(['operation_id', 'company_id']);
        });
    }
};
