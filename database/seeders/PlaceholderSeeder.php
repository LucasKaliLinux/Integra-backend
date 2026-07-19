<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlaceholderSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Cria órgão placeholder
        $orgaoId = DB::table('orgaos_governo')->insertGetId([
            'tipo_orgao_id' => 1, // Qualquer tipo (ex: Ministério)
            'nome' => 'A DEFINIR',
            'sigla' => 'A DEFINIR',
        ]);

        // 2. Cria categoria placeholder
        $categoriaId = DB::table('categorias_investimento')->insertGetId([
            'nome' => 'A DEFINIR',
            'slug' => 'a-definir',
        ]);

        // 3. Cria tipo de ação placeholder
        $tipoId = DB::table('tipos_acao')->insertGetId([
            'nome' => 'A DEFINIR',
            'slug' => 'a-definir',
        ]);

        $this->command->info('✅ Placeholders criados:');
        $this->command->info("   - Órgão ID: {$orgaoId}");
        $this->command->info("   - Categoria ID: {$categoriaId}");
        $this->command->info("   - Tipo Ação ID: {$tipoId}");
    }
}
