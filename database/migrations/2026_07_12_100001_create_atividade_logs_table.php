<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Log de atividades — inteligência de uso por gabinete (NÃO auditoria de
     * segurança / LGPD-safe). Tabela append-only e imutável: sem updated_at,
     * imutabilidade garantida na aplicação (AtividadeLog é append-only).
     *
     * Privacidade: nunca guarda IP em claro, user-agent bruto, e-mail digitado,
     * corpo/headers de request, tokens, ou PII. Só FKs + buckets + rota (padrão
     * de rota do Laravel, nunca URL completa/query string) + contexto whitelisted.
     */
    public function up(): void
    {
        Schema::create('atividade_logs', function (Blueprint $table) {
            $table->id();

            // Nullable: eventos de auth podem não ter tenant resolvido.
            $table->foreignId('deputado_id')
                ->nullable()
                ->constrained('deputados')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->foreignId('sessao_id')
                ->nullable()
                ->constrained('atividade_sessoes')
                ->nullOnDelete();

            // Enums controlados na config/atividades.php (categoria=domínio, acao=verbo).
            $table->string('categoria', 40);
            $table->string('acao', 40);

            $table->string('recurso_tipo', 40)->nullable();
            $table->unsignedBigInteger('recurso_id')->nullable();

            $table->string('metodo_http', 8)->nullable();
            // Padrão de rota do Laravel (ex.: acoes/{aco}), NUNCA a URL completa.
            $table->string('rota')->nullable();

            // Buckets estreitos derivados do user-agent (string bruta descartada).
            $table->string('plataforma', 16)->nullable();
            $table->string('navegador', 16)->nullable();

            // JSON mínimo/whitelisted (deny-by-default no model).
            $table->json('contexto')->nullable();

            // Append-only: só created_at (sem updated_at).
            $table->timestamp('created_at')->nullable();

            $table->index(['deputado_id', 'created_at']);
            $table->index(['deputado_id', 'categoria', 'created_at']);
            $table->index(['deputado_id', 'user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('atividade_logs');
    }
};
