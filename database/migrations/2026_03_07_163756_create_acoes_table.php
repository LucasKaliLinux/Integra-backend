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
        Schema::create('acoes', function (Blueprint $table) {
            $table->id();

            // Relacionamentos
            $table->unsignedBigInteger('user_id');
            $table->integer('id_municipio');
            $table->unsignedBigInteger('orgao_governo_id');
            $table->unsignedBigInteger('categoria_investimento_id');
            $table->unsignedBigInteger('tipo_acao_id');
            $table->unsignedBigInteger('lideranca_solicitante_id')->nullable();

            // Detalhes da Ação
            $table->string('titulo');
            $table->string('numero_sei')->nullable(); // N° SEI
            $table->string('instrumento_path')->nullable(); // Caminho do arquivo
            $table->decimal('valor', 15, 2)->default(0);
            $table->year('ano');
            $table->unsignedBigInteger('status_id');

            $table->text('observacao')->nullable(); // Campo extra pra anotações

            $table->timestamps();

            // Foreign Keys
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('id_municipio')
                ->references('id_municipio')
                ->on('municipios')
                ->onDelete('cascade');

            $table->foreign('orgao_governo_id')
                ->references('id')
                ->on('orgaos_governo')
                ->onDelete('restrict'); // Não deixa deletar órgão se tiver ação

            $table->foreign('categoria_investimento_id')
                ->references('id')
                ->on('categorias_investimento')
                ->onDelete('restrict');

            $table->foreign('tipo_acao_id')
                ->references('id')
                ->on('tipos_acao')
                ->onDelete('restrict');

            $table->foreign('lideranca_solicitante_id')
                ->references('id')
                ->on('liderancas')
                ->nullOnDelete();

            $table->foreign('status_id')
                ->references('id')
                ->on('status_acao');

            // Índices
            $table->index(['user_id', 'id_municipio']);
            $table->index('ano');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acoes');
    }
};
