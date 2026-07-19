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
        Schema::create('campanha_material_tipos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('deputado_id')
                ->constrained('deputados')
                ->cascadeOnDelete();

            $table->string('nome');
            $table->boolean('ativo')->default(true);

            $table->timestamps();

            $table->unique(['deputado_id', 'nome']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campanha_material_tipos');
    }
};
