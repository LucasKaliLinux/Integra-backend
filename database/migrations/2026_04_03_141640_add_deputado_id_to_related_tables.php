<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. user_municipios
        Schema::table('user_municipios', function (Blueprint $table) {
            $table->foreignId('deputado_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->index(['deputado_id', 'user_id']);
        });

        // 2. liderancas
        Schema::table('liderancas', function (Blueprint $table) {
            $table->foreignId('deputado_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->index(['deputado_id', 'user_id']);
        });

        // 3. acoes
        Schema::table('acoes', function (Blueprint $table) {
            $table->foreignId('deputado_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->index(['deputado_id', 'user_id']);
        });

        // 4. acoes_historico (NÃO precisa, herda da ação)
        // Não adiciona deputado_id aqui

        // 5. imports
        Schema::table('imports', function (Blueprint $table) {
            $table->foreignId('deputado_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->index(['deputado_id', 'user_id']);
        });

        // 6. exports
        Schema::table('exports', function (Blueprint $table) {
            $table->foreignId('deputado_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->index(['deputado_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::table('user_municipios', function (Blueprint $table) {
            $table->dropForeign(['deputado_id']);
            $table->dropColumn('deputado_id');
        });

        Schema::table('liderancas', function (Blueprint $table) {
            $table->dropForeign(['deputado_id']);
            $table->dropColumn('deputado_id');
        });

        Schema::table('acoes', function (Blueprint $table) {
            $table->dropForeign(['deputado_id']);
            $table->dropColumn('deputado_id');
        });

        Schema::table('imports', function (Blueprint $table) {
            $table->dropForeign(['deputado_id']);
            $table->dropColumn('deputado_id');
        });

        Schema::table('exports', function (Blueprint $table) {
            $table->dropForeign(['deputado_id']);
            $table->dropColumn('deputado_id');
        });
    }
};
