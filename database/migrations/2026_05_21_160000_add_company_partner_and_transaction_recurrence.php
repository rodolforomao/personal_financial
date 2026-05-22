<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->decimal('partnership_share', 5, 2)->nullable()->after('type');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreignId('recurring_item_id')->nullable()->after('is_recurring')
                ->constrained('recurring_items')->nullOnDelete();
            $table->string('recurrence_frequency')->nullable()->after('recurring_item_id');
        });

        DB::table('companies')->where('type', 'client')->update(['type' => 'payer']);
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recurring_item_id');
            $table->dropColumn('recurrence_frequency');
        });

        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('partnership_share');
        });
    }
};
