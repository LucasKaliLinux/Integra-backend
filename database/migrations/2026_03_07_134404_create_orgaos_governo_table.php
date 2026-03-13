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
        Schema::create('orgaos_governo', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tipo_orgao_id');
            $table->string('nome'); // Casa Civil, IBAMA, ANATEL, IPAC, etc
            $table->string('sigla')->nullable(); // IBAMA, ANATEL, IPAC
            $table->timestamps();

            $table->foreign('tipo_orgao_id')
                ->references('id')
                ->on('tipos_orgao')
                ->onDelete('cascade');

            $table->index('tipo_orgao_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orgaos_governo');
    }
};
