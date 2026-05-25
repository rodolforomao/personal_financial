<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statement_imports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('original_name');
            $table->string('format', 10);
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('lines_total')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
        });

        Schema::create('statement_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('statement_import_id')->constrained()->cascadeOnDelete();
            $table->foreignId('transaction_id')->nullable()->constrained()->nullOnDelete();
            $table->date('transaction_date');
            $table->decimal('amount', 15, 2);
            $table->string('type', 20);
            $table->string('description');
            $table->string('counterparty')->nullable();
            $table->string('external_ref')->nullable();
            $table->string('match_status', 20)->default('unmatched');
            $table->unsignedTinyInteger('match_score')->nullable();
            $table->timestamps();

            $table->index(['statement_import_id', 'match_status']);
            $table->index(['transaction_date', 'amount']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('statement_lines');
        Schema::dropIfExists('statement_imports');
    }
};
