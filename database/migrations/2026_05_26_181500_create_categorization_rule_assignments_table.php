<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorization_rule_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('categorization_rule_id')->constrained('categorization_rules')->cascadeOnDelete();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('hit_count')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'categorization_rule_id'], 'rule_assignment_workspace_unique');
            $table->index(['workspace_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('categorization_rule_assignments');
    }
};
