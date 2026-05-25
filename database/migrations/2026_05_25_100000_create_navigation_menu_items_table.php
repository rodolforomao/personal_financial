<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('navigation_menu_items', function (Blueprint $table) {
            $table->id();
            $table->string('type', 16); // header | link
            $table->unsignedSmallInteger('sort_order')->default(100);
            $table->string('label');
            $table->string('route')->nullable();
            $table->string('icon')->nullable();
            $table->string('required_role')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
            $table->index('route');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('navigation_menu_items');
    }
};
