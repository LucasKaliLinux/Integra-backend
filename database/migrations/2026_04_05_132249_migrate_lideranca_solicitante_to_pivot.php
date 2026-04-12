<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // 1️⃣ Migra ações que JÁ TEM liderança
        DB::statement("
            INSERT INTO acao_lideranca (acao_id, lideranca_id, created_at, updated_at)
            SELECT 
                id as acao_id,
                lideranca_solicitante_id as lideranca_id,
                created_at,
                updated_at
            FROM acoes
            WHERE lideranca_solicitante_id IS NOT NULL
        ");

        // 2️⃣ Remove coluna antiga (depois de migrar!)
        Schema::table('acoes', function (Blueprint $table) {
            $table->dropForeign(['lideranca_solicitante_id']);
            $table->dropColumn('lideranca_solicitante_id');
        });
    }

    public function down(): void
    {
        // Restaura coluna
        Schema::table('acoes', function (Blueprint $table) {
            $table->unsignedBigInteger('lideranca_solicitante_id')->nullable()->after('tipo_acao_id');
            $table->foreign('lideranca_solicitante_id')
                ->references('id')
                ->on('liderancas')
                ->nullOnDelete();
        });

        // Restaura primeiro relacionamento
        DB::statement("
            UPDATE acoes a
            INNER JOIN (
                SELECT acao_id, MIN(lideranca_id) as lideranca_id
                FROM acao_lideranca
                GROUP BY acao_id
            ) al ON al.acao_id = a.id
            SET a.lideranca_solicitante_id = al.lideranca_id
        ");
    }
};