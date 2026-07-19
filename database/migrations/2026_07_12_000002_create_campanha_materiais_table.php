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
        Schema::create('campanha_materiais', function (Blueprint $table) {
            $table->id();

            $table->foreignId('deputado_id')
                ->constrained('deputados')
                ->cascadeOnDelete();

            // Mesma convenção da migration de acoes: PK de municipios é id_municipio (integer).
            $table->integer('id_municipio');

            $table->foreignId('lideranca_id')
                ->nullable()
                ->constrained('liderancas')
                ->nullOnDelete();

            $table->string('tipo'); // tipo de material (Santinhos, Adesivos, ...)
            $table->unsignedInteger('quantidade');

            // Valores validados no FormRequest: solicitado|produzido|entregue|cancelado
            $table->string('status')->default('solicitado')->index();

            // Composição ("casadinha"): slots de parceiros por cargo.
            // O deputado dono NÃO entra na composição — o material é implicitamente dele.
            $table->foreignId('politico_estadual_id')
                ->nullable()
                ->constrained('campanha_politicos')
                ->restrictOnDelete();

            $table->foreignId('politico_federal_id')
                ->nullable()
                ->constrained('campanha_politicos')
                ->restrictOnDelete();

            $table->foreignId('politico_senador_id')
                ->nullable()
                ->constrained('campanha_politicos')
                ->restrictOnDelete();

            $table->foreignId('politico_governador_id')
                ->nullable()
                ->constrained('campanha_politicos')
                ->restrictOnDelete();

            $table->foreignId('politico_presidente_id')
                ->nullable()
                ->constrained('campanha_politicos')
                ->restrictOnDelete();

            $table->string('recebedor')->nullable();
            $table->date('data'); // data de entrega (pode ser futura)
            $table->string('arte_url')->nullable();
            $table->text('observacao')->nullable();

            // Quem criou (auditoria, como em acoes).
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamps();

            $table->foreign('id_municipio')
                ->references('id_municipio')
                ->on('municipios')
                ->restrictOnDelete();

            $table->index(['deputado_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campanha_materiais');
    }
};
