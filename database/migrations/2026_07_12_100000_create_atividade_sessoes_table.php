<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sessões de uso do gabinete (inteligência de uso, NÃO auditoria de
     * segurança). Uma sessão é aberta no login e fechada no logout ou por
     * inferência (timeout de inatividade). Guarda apenas FKs e timestamps —
     * nenhum dado sensível (sem IP, sem user-agent bruto).
     */
    public function up(): void
    {
        Schema::create('atividade_sessoes', function (Blueprint $table) {
            $table->id();

            $table->foreignId('deputado_id')
                ->constrained('deputados')
                ->cascadeOnDelete();

            // User usa SoftDeletes: preserva a sessão histórica ao excluir o user.
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            // Nullable no schema (evita default inválido de timestamp sob strict
            // mode); sempre preenchidos pela aplicação na criação da sessão.
            $table->timestamp('iniciada_em')->nullable();
            $table->timestamp('ultima_atividade_em')->nullable();
            $table->timestamp('encerrada_em')->nullable();
            $table->integer('duracao_segundos')->nullable();

            // login: sessão aberta por login explícito.
            // inferida: encerramento inferido por timeout (sem logout explícito).
            $table->string('origem', 20)->default('login');

            $table->index(['deputado_id', 'user_id', 'iniciada_em']);
            // Suporta a varredura do comando que fecha sessões inativas.
            $table->index(['encerrada_em', 'ultima_atividade_em']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atividade_sessoes');
    }
};
