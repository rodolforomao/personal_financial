<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->string('content_hash', 64)->nullable()->after('storage_path');
            $table->index(['workspace_id', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::table('documents', function (Blueprint $table) {
            $table->dropIndex(['workspace_id', 'content_hash']);
            $table->dropColumn('content_hash');
        });
    }
};
