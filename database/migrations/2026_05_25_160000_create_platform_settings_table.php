<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        if (Schema::hasTable('navigation_menu_items') && DB::table('navigation_menu_items')->exists()) {
            DB::table('navigation_menu_items')->updateOrInsert(
                ['type' => 'link', 'route' => 'admin.settings.edit'],
                [
                    'sort_order' => 520,
                    'label' => 'Configurações',
                    'icon' => 'bi bi-gear',
                    'required_role' => 'admin',
                    'is_active' => true,
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('navigation_menu_items')) {
            DB::table('navigation_menu_items')
                ->where('route', 'admin.settings.edit')
                ->delete();
        }

        Schema::dropIfExists('platform_settings');
    }
};
