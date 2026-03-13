<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CategoriaInvestimento;

class CategoriaInvestimentoSeeder extends Seeder
{
    public function run(): void
    {
        $categorias = [
            ['nome' => 'Pavimentação', 'slug' => 'pavimentacao'],
            ['nome' => 'Cultura', 'slug' => 'cultura'],
            ['nome' => 'Turismo', 'slug' => 'turismo'],
            ['nome' => 'Segurança Pública', 'slug' => 'seguranca_publica'],
            ['nome' => 'Saúde', 'slug' => 'saude'],
            ['nome' => 'Educação', 'slug' => 'educacao'],
            ['nome' => 'Energia', 'slug' => 'energia'],
            ['nome' => 'Infraestrutura Urbana', 'slug' => 'infraestrutura_urbana'],
            ['nome' => 'Mobilidade e Estradas', 'slug' => 'mobilidade_estradas'],
            ['nome' => 'Saneamento', 'slug' => 'saneamento'],
            ['nome' => 'Habitação', 'slug' => 'habitacao'],
            ['nome' => 'Agricultura', 'slug' => 'agricultura'],
            ['nome' => 'Meio Ambiente', 'slug' => 'meio_ambiente'],
            ['nome' => 'Esporte e Lazer', 'slug' => 'esporte_lazer'],
            ['nome' => 'Assistência Social', 'slug' => 'assistencia_social'],
            ['nome' => 'Desenvolvimento Econômico', 'slug' => 'desenvolvimento_economico'],
        ];

        foreach ($categorias as $categoria) {
            CategoriaInvestimento::updateOrCreate(
                ['slug' => $categoria['slug']],
                $categoria
            );
        }
    }
}