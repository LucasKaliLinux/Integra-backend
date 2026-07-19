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
        Schema::create('campanha_politicos', function (Blueprint $table) {
            $table->id();

            $table->foreignId('deputado_id')
                ->constrained('deputados')
                ->cascadeOnDelete();

            $table->string('nome');
            $table->string('nome_urna');

            // String (não integer) para preservar zeros à esquerda.
            $table->string('numero_eleitoral');

            // Valores validados no FormRequest:
            // estadual|federal|senador|governador|presidente
            $table->string('cargo')->index();

            $table->string('partido');
            $table->string('federacao')->nullable();
            $table->string('slogan')->nullable();
            $table->string('cor_principal', 9)->nullable(); // hex: #RGB ou #RRGGBB
            $table->string('coordenador')->nullable();
            $table->string('telefone')->nullable();
            $table->string('foto_url')->nullable();

            $table->boolean('ativo')->default(true);

            $table->timestamps();

            $table->index(['deputado_id', 'cargo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campanha_politicos');
    }
};
