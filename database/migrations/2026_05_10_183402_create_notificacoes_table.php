<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacoes', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('mensagem');
            $table->timestamp('data_envio')->nullable(); // NULL = enviar agora
            $table->enum('audiencia', ['todos', 'especifico'])->default('todos');
            $table->boolean('enviada')->default(false);
            $table->timestamps();

            $table->index(['enviada', 'data_envio']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacoes');
    }
};
