<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ========== ACOES ==========
        Schema::table('acoes', function (Blueprint $table) {
            // Verifica se FK existe antes de dropar
            $foreignKeys = $this->getForeignKeys('acoes');
            if (in_array('acoes_user_id_foreign', $foreignKeys)) {
                $table->dropForeign(['user_id']);
            }
        });

        Schema::table('acoes', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // ========== LIDERANCAS ==========
        Schema::table('liderancas', function (Blueprint $table) {
            $foreignKeys = $this->getForeignKeys('liderancas');
            if (in_array('liderancas_user_id_foreign', $foreignKeys)) {
                $table->dropForeign(['user_id']);
            }
        });

        Schema::table('liderancas', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // ========== ACOES_HISTORICO ==========
        Schema::table('acoes_historico', function (Blueprint $table) {
            $foreignKeys = $this->getForeignKeys('acoes_historico');
            if (in_array('acoes_historico_user_id_foreign', $foreignKeys)) {
                $table->dropForeign(['user_id']);
            }
        });

        Schema::table('acoes_historico', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // ========== IMPORTS ==========
        Schema::table('imports', function (Blueprint $table) {
            $foreignKeys = $this->getForeignKeys('imports');
            if (in_array('imports_user_id_foreign', $foreignKeys)) {
                $table->dropForeign(['user_id']);
            }
        });

        Schema::table('imports', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // ========== EXPORTS ==========
        Schema::table('exports', function (Blueprint $table) {
            $foreignKeys = $this->getForeignKeys('exports');
            if (in_array('exports_user_id_foreign', $foreignKeys)) {
                $table->dropForeign(['user_id']);
            }
        });

        Schema::table('exports', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });

        // ========== USER_MUNICIPIOS ==========
        // ⬇️ NOVO: Protege também (você estava certo!)
        Schema::table('user_municipios', function (Blueprint $table) {
            $foreignKeys = $this->getForeignKeys('user_municipios');
            if (in_array('user_municipios_user_id_foreign', $foreignKeys)) {
                $table->dropForeign(['user_id']);
            }
        });

        Schema::table('user_municipios', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->change();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete(); // ⬅️ Mantém CASCADE (pivot deve deletar junto)
        });
    }

    public function down(): void
    {
        // ========== ACOES ==========
        Schema::table('acoes', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('acoes', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        // ========== LIDERANCAS ==========
        Schema::table('liderancas', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('liderancas', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        // ========== ACOES_HISTORICO ==========
        Schema::table('acoes_historico', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('acoes_historico', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        // ========== IMPORTS ==========
        Schema::table('imports', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('imports', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        // ========== EXPORTS ==========
        Schema::table('exports', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('exports', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable(false)->change();
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });

        // ========== USER_MUNICIPIOS ==========
        Schema::table('user_municipios', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
        });
        Schema::table('user_municipios', function (Blueprint $table) {
            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
        });
    }

    /**
     * Helper: Pega FKs existentes de uma tabela
     */
    private function getForeignKeys(string $table): array
    {
        $foreignKeys = DB::select(
            "SELECT CONSTRAINT_NAME 
             FROM information_schema.TABLE_CONSTRAINTS 
             WHERE TABLE_SCHEMA = DATABASE() 
             AND TABLE_NAME = ? 
             AND CONSTRAINT_TYPE = 'FOREIGN KEY'",
            [$table]
        );

        return array_map(fn ($fk) => $fk->CONSTRAINT_NAME, $foreignKeys);
    }
};
