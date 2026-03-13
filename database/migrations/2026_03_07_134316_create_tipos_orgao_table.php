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
        Schema::create('tipos_orgao', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('esfera_governo_id');
            $table->string('nome'); // Ministério, Autarquia, Agência, Secretaria, etc
            $table->string('slug');
            $table->timestamps();

            $table->foreign('esfera_governo_id')
                ->references('id')
                ->on('esferas_governo')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tipos_orgao');
    }
};
