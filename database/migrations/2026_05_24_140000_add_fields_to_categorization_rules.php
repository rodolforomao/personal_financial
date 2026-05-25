<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categorization_rules', function (Blueprint $table) {
            $table->string('name')->nullable()->after('category_id');
            $table->string('transaction_type')->nullable()->after('pattern');
        });
    }

    public function down(): void
    {
        Schema::table('categorization_rules', function (Blueprint $table) {
            $table->dropColumn(['name', 'transaction_type']);
        });
    }
};
