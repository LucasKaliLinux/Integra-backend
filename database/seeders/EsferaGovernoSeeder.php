<?php

namespace Database\Seeders;

use App\Models\EsferaGoverno;
use Illuminate\Database\Seeder;

class EsferaGovernoSeeder extends Seeder
{
    public function run(): void
    {
        $esferas = [
            ['nome' => 'Federal', 'slug' => 'federal'],
            ['nome' => 'Estadual', 'slug' => 'estadual'],
        ];

        foreach ($esferas as $esfera) {
            EsferaGoverno::updateOrCreate(
                ['slug' => $esfera['slug']],
                $esfera
            );
        }
    }
}
