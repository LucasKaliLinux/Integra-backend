<?php

namespace Database\Seeders;

use App\Models\TipoAcao;
use Illuminate\Database\Seeder;

class TipoAcaoSeeder extends Seeder
{
    public function run(): void
    {
        $tipos = [
            ['nome' => 'Obra', 'slug' => 'obra'],
            ['nome' => 'Convênio', 'slug' => 'convenio'],
            ['nome' => 'Equipamento', 'slug' => 'equipamento'],
            ['nome' => 'Emenda Parlamentar', 'slug' => 'emenda_parlamentar'],
            ['nome' => 'Programa', 'slug' => 'programa'],
            ['nome' => 'Transferência de Recurso', 'slug' => 'transferencia_recurso'],
            ['nome' => 'Projeto', 'slug' => 'projeto'],
            ['nome' => 'Estudo ou Planejamento', 'slug' => 'estudo_planejamento'],
        ];

        foreach ($tipos as $tipo) {
            TipoAcao::updateOrCreate(
                ['slug' => $tipo['slug']],
                $tipo
            );
        }
    }
}
