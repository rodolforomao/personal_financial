<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            // Número total de sócios (incluindo o dono). Quando preenchido, a receita
            // recebida na conta pessoal é dividida por esse valor para refletir a
            // participação real do usuário.
            $table->unsignedTinyInteger('partners_count')->nullable()->after('description');

            // Valor total investido por todos os sócios desde o início da operação.
            // Usado para acompanhar o retorno acumulado no relatório.
            $table->decimal('total_invested', 12, 2)->nullable()->after('partners_count');
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropColumn(['partners_count', 'total_invested']);
        });
    }
};
