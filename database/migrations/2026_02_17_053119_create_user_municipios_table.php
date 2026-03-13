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
        Schema::create('user_municipios', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('user_id'); // users.id é bigint unsigned

            $table->integer('id_municipio'); // INT normal, igual à tabela municipios

            $table->unique(['user_id', 'id_municipio']);

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');

            $table->foreign('id_municipio')
                ->references('id_municipio')
                ->on('municipios')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_municipios');
    }
};
