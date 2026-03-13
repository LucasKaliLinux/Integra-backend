<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\EsferaGoverno;
use App\Models\TipoOrgao;

class TipoOrgaoSeeder extends Seeder
{
    public function run(): void
    {
        $federal = EsferaGoverno::where('slug', 'federal')->first();
        $estadual = EsferaGoverno::where('slug', 'estadual')->first();

        $tipos = [
            // Federal
            ['esfera_governo_id' => $federal->id, 'nome' => 'Presidência da República', 'slug' => 'presidencia'],
            ['esfera_governo_id' => $federal->id, 'nome' => 'Ministério', 'slug' => 'ministerio'],
            ['esfera_governo_id' => $federal->id, 'nome' => 'Autarquia', 'slug' => 'autarquia'],
            ['esfera_governo_id' => $federal->id, 'nome' => 'Agência Reguladora', 'slug' => 'agencia'],
            ['esfera_governo_id' => $federal->id, 'nome' => 'Empresa Pública', 'slug' => 'empresa_publica'],
            ['esfera_governo_id' => $federal->id, 'nome' => 'Fundação Pública', 'slug' => 'fundacao_publica'],

            // Estadual (Bahia)
            ['esfera_governo_id' => $estadual->id, 'nome' => 'Governadoria', 'slug' => 'governadoria'],
            ['esfera_governo_id' => $estadual->id, 'nome' => 'Secretaria', 'slug' => 'secretaria'],
            ['esfera_governo_id' => $estadual->id, 'nome' => 'Autarquia', 'slug' => 'autarquia_estadual'],
            ['esfera_governo_id' => $estadual->id, 'nome' => 'Fundação', 'slug' => 'fundacao'],
            ['esfera_governo_id' => $estadual->id, 'nome' => 'Instituto', 'slug' => 'instituto'],
            ['esfera_governo_id' => $estadual->id, 'nome' => 'Empresa Estadual', 'slug' => 'empresa_estadual'],
            ['esfera_governo_id' => $estadual->id, 'nome' => 'Superintendência', 'slug' => 'superintendencia'],
        ];

        foreach ($tipos as $tipo) {
            TipoOrgao::updateOrCreate(
                ['slug' => $tipo['slug']],
                $tipo
            );
        }
    }
}