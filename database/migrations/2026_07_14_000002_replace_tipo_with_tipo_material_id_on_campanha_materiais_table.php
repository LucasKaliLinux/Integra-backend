<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Troca estrutural direta: o módulo de campanha nunca foi ativado em
 * produção (CAMPANHA_ATIVA=false desde sempre) e não há dado real a
 * preservar. Por isso não há backfill/seed — apenas remove a coluna
 * "tipo" (string livre) e adiciona "tipo_material_id" (FK), decisão
 * aprovada antes do primeiro deploy do módulo.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campanha_materiais', function (Blueprint $table) {
            $table->dropColumn('tipo');

            $table->foreignId('tipo_material_id')
                ->after('lideranca_id')
                ->constrained('campanha_material_tipos')
                ->restrictOnDelete();

            $table->index(['deputado_id', 'tipo_material_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campanha_materiais', function (Blueprint $table) {
            $table->dropForeign(['tipo_material_id']);
            $table->dropIndex(['deputado_id', 'tipo_material_id']);
            $table->dropColumn('tipo_material_id');

            $table->string('tipo')->after('lideranca_id');
        });
    }
};
