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
        Schema::create('liderancas_politicas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('lideranca_id');
            $table->unsignedBigInteger('sequencial_candidato');
            $table->year('ano');
            $table->string('titulo_eleitoral', 20);
            $table->enum('resultado', ['eleito', 'nao_eleito']);
            $table->timestamps();

            $table->foreign('lideranca_id')
                ->references('id')
                ->on('liderancas')
                ->onDelete('cascade');

            $table->unique(['sequencial_candidato', 'ano']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('liderancas_politicas');
    }
};
