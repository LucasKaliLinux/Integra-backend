<?php

namespace Database\Seeders;

use App\Models\ClassificacaoLideranca;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ClassificacaoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $classificacoes = [
            ['nome' => 'Política', 'slug' => 'politica'],
            ['nome' => 'Lideranças Comunitárias e Sociais', 'slug' => 'liderancas_comunitarias_e_sociais'],
            ['nome' => 'Religioso', 'slug' => 'religioso'],
            ['nome' => 'Imprensa e Comunicação', 'slug' => 'imprensa_e_comunicacao'],
            ['nome' => 'Pessoal', 'slug' => 'pessoal'],
        ];

        foreach ($classificacoes as $classificacao) {
            ClassificacaoLideranca::updateOrCreate(
                ['slug' => $classificacao['slug']],
                $classificacao
            );
        }
    }
}
