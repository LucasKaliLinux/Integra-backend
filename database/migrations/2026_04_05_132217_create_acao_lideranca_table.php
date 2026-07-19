<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acao_lideranca', function (Blueprint $table) {
            $table->id();
            $table->foreignId('acao_id')->constrained('acoes')->onDelete('cascade');
            $table->foreignId('lideranca_id')->constrained('liderancas')->onDelete('cascade');
            $table->timestamps();

            // Previne duplicatas
            $table->unique(['acao_id', 'lideranca_id']);

            // Índices de performance
            $table->index('acao_id');
            $table->index('lideranca_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('acao_lideranca');
    }
};
