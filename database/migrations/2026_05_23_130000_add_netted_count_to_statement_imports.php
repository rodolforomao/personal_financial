<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statement_imports', function (Blueprint $table) {
            $table->unsignedInteger('netted_count')->default(0)->after('imported_count');
        });
    }

    public function down(): void
    {
        Schema::table('statement_imports', function (Blueprint $table) {
            $table->dropColumn('netted_count');
        });
    }
};
