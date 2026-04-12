<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        // User::factory()->create([
        //     'name' => 'teste',
        //     'email' => 'teste@gmail.com',
        //     'password' => Hash::make('Vb102030@'),
        //     'titulo_eleitoral' => '0777437999999'
        // ]);

        // $this->call([
        //     LiderancaSeeder::class,
        // ]);

        $this->call([
            EsferaGovernoSeeder::class,
            TipoOrgaoSeeder::class,
            OrgaoGovernoSeeder::class,
            CategoriaInvestimentoSeeder::class,
            TipoAcaoSeeder::class,
            StatusAcaoSeeder::class,
        ]);
    }
}
