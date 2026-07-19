<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('deputados', function (Blueprint $table) {
            $table->id();
            $table->string('titulo_eleitoral')->unique()->index();
            $table->string('nome');
            $table->string('partido', 10)->nullable();
            $table->enum('cargo', ['deputado estadual', 'deputado federal'])->nullable();
            $table->boolean('ativo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('deputados');
    }
};
