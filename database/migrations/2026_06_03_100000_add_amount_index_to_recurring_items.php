<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recurring_items', function (Blueprint $table) {
            // Ex: 'salario_minimo', 'usdt'
            $table->string('amount_index', 32)->nullable()->after('amount');
            // Multiplicador puro: 0.0175 = 1,75%; 100 = 100 USDT
            $table->decimal('amount_factor', 12, 6)->nullable()->after('amount_index');
        });
    }

    public function down(): void
    {
        Schema::table('recurring_items', function (Blueprint $table) {
            $table->dropColumn(['amount_index', 'amount_factor']);
        });
    }
};
