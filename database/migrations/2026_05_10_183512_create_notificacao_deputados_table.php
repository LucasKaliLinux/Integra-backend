<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificacao_deputados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('notificacao_id')->constrained('notificacoes')->onDelete('cascade');
            $table->foreignId('deputado_id')->constrained()->onDelete('cascade');
            $table->timestamps();

            $table->unique(['notificacao_id', 'deputado_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificacao_deputados');
    }
};
