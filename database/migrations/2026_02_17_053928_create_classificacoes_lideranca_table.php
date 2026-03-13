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
        Schema::create('classificacoes_lideranca', function (Blueprint $table) {
            $table->id();
            $table->string('nome')->unique();
            $table->string('slug')->unique();
            $table->timestamps();
        });

        Schema::create('cargos_lideranca', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('classificacao_id');
            $table->string('nome');
            $table->string('slug');
            $table->timestamps();

            $table->foreign('classificacao_id')
                ->references('id')
                ->on('classificacoes_lideranca')
                ->onDelete('cascade');

            $table->unique(['classificacao_id', 'nome']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cargos_lideranca');
        Schema::dropIfExists('classificacoes_lideranca');
    }
};
