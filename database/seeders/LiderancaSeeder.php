<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class LiderancaSeeder extends Seeder
{
    public function run(): void
    {
        $classificacoes = [

            'Política' => [
                'Prefeito',
                'Vice-prefeito',
                'Ex-prefeito',
                'Vereador',
                'Ex-vereador'
            ],

            'Lideranças Comunitárias e Sociais' => [
                'Liderança Comunitária',
                'Presidente de Associação',
                'Representante de Bairro',
                'Representante de Comunidade',
                'Líder Rural',
                'Líder Quilombola',
                'Líder de Movimento Social',
                'Coordenador Comunitário',
                'Representante de ONG',
            ],

            'Religioso' => [
                'Pastor',
                'Padre',
                'Bispo',
                'Líder Indígena',
                'Líder Quilombola',
                'Pai de Santo',
                'Mãe de Santo',
                'Liderança Religiosa (Outras)',
            ],

            'Imprensa e Comunicação' => [
                'Jornalista',
                'Radialista',
                'Apresentador',
                'Influenciador Digital',
                'Repórter',
                'Editor',
                'Blogueiro',
                'Comunicador Comunitário',
            ],

            'Pessoal' => [
                'Amigo Pessoal',
                'Amigo da Família',
                'Aliado Político',
                'Contato Estratégico',
                'Apoiador',
                'Colaborador Próximo',
            ],

        ];


        foreach ($classificacoes as $nomeClassificacao => $cargos) {

            $slugClassificacao = Str::slug($nomeClassificacao, '_');

            DB::table('classificacoes_lideranca')
                ->updateOrInsert(
                    ['slug' => $slugClassificacao],
                    ['nome' => $nomeClassificacao]
                );

            $classificacao = DB::table('classificacoes_lideranca')
                ->where('slug', $slugClassificacao)
                ->first();

            foreach ($cargos as $cargo) {
                $slugCargo = Str::slug($cargo, '_');

                DB::table('cargos_lideranca')
                    ->updateOrInsert(
                        [
                            'classificacao_id' => $classificacao->id,
                            'slug' => $slugCargo
                        ],
                        ['nome' => $cargo]
                    );
            }
        }
    }
}