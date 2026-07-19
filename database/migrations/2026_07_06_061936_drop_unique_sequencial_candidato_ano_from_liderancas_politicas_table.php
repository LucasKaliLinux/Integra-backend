<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Remove a constraint única global de (sequencial_candidato, ano) em
     * liderancas_politicas. Essa constraint impedia que deputados diferentes
     * cadastrassem o mesmo candidato como liderança política, já que a
     * unicidade não era escopada por deputado. O isolamento por deputado
     * agora é garantido via JOIN com `liderancas` (que possui deputado_id)
     * na aplicação, não por uma constraint de banco.
     */
    public function up(): void
    {
        Schema::table('liderancas_politicas', function (Blueprint $table) {
            $table->dropUnique('liderancas_politicas_sequencial_candidato_ano_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('liderancas_politicas', function (Blueprint $table) {
            $table->unique(['sequencial_candidato', 'ano']);
        });
    }
};
