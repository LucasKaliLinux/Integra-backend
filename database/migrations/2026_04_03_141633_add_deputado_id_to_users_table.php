<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('deputado_id')->nullable()->after('id')->constrained()->onDelete('cascade');
            $table->dropColumn('titulo_eleitoral'); // Move pra tabela deputados
            
            $table->index(['deputado_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['deputado_id']);
            $table->dropColumn('deputado_id');
            $table->string('titulo_eleitoral')->nullable()->index();
        });
    }
};