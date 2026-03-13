<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('liderancas', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id');
            $table->integer('id_municipio');
            $table->unsignedBigInteger('classificacao_id');
            $table->unsignedBigInteger('funcao_id');

            $table->string('nome');
            $table->string('telefone')->nullable();
            $table->date('data_nascimento')->nullable();

            $table->enum('alinhamento', ['aliado', 'oposicao']);

            $table->text('observacao')->nullable();

            $table->timestamps();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('id_municipio')
                ->references('id_municipio')
                ->on('municipios')
                ->onDelete('cascade');

            $table->foreign('classificacao_id')
                ->references('id')
                ->on('classificacoes_lideranca')
                ->onDelete('cascade');

            $table->foreign('funcao_id')
                ->references('id')
                ->on('cargos_lideranca')
                ->onDelete('cascade');

            $table->index(['user_id', 'id_municipio']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('liderancas');
    }
};
