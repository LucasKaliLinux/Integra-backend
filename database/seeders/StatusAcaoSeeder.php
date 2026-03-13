<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\StatusAcao;

class StatusAcaoSeeder extends Seeder
{
    public function run(): void
    {
        $status = [
            ['nome' => 'Solicitado', 'slug' => 'solicitado', 'ordem' => 1],
            ['nome' => 'Em Articulação', 'slug' => 'em_articulacao', 'ordem' => 2],
            ['nome' => 'Aprovada', 'slug' => 'aprovada', 'ordem' => 3],
            ['nome' => 'Em Execução', 'slug' => 'em_execucao', 'ordem' => 4],
            ['nome' => 'Concluída', 'slug' => 'concluida', 'ordem' => 5],
        ];

        foreach ($status as $s) {
            StatusAcao::updateOrCreate(
                ['slug' => $s['slug']],
                $s
            );
        }
    }
}