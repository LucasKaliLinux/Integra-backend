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
        Schema::create('acoes_historico', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('acao_id');
            $table->unsignedBigInteger('status_id'); // Status NOVO
            $table->unsignedBigInteger('user_id'); // Quem mudou
            $table->text('observacao')->nullable(); // Comentário da mudança
            $table->timestamp('created_at'); // Quando mudou

            $table->foreign('acao_id')
                ->references('id')
                ->on('acoes')
                ->onDelete('cascade');

            $table->foreign('status_id')
                ->references('id')
                ->on('status_acao');

            $table->foreign('user_id')
                ->references('id')
                ->on('users');

            $table->index(['acao_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('acoes_historico');
    }
};
