<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Expansão do "Tipo de Material" do módulo Campanha.
 *
 * Migration aditiva (o módulo nunca foi ativado — CAMPANHA_ATIVA=false —
 * e não há dado real a preservar, por isso sem backfill/seed):
 *  - campanha_material_tipos ganha "categoria" (enum FIXO validado no
 *    FormRequest, não em tabela), "unidade" (texto livre) e "variantes"
 *    (array JSON de strings).
 *  - campanha_materiais ganha "variante" (snapshot string da variante
 *    escolhida no momento do cadastro; sem FK).
 *
 * Observação: o MySQL não aceita DEFAULT literal em coluna JSON, então
 * "variantes" fica nullable e o array vazio ([]) é garantido pelo model
 * (atributo default + cast 'array') e pelo Resource.
 */
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('campanha_material_tipos', function (Blueprint $table) {
            $table->string('categoria')->default('outro')->after('nome');
            $table->string('unidade', 50)->default('un')->after('categoria');
            $table->json('variantes')->nullable()->after('unidade');
        });

        Schema::table('campanha_materiais', function (Blueprint $table) {
            $table->string('variante')->nullable()->after('tipo_material_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('campanha_materiais', function (Blueprint $table) {
            $table->dropColumn('variante');
        });

        Schema::table('campanha_material_tipos', function (Blueprint $table) {
            $table->dropColumn(['categoria', 'unidade', 'variantes']);
        });
    }
};
