<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        Role::create(['name' => 'super_admin']); // Devs/Gerentes
        Role::create(['name' => 'admin']);       // Deputado/Chefe Gabinete
        Role::create(['name' => 'manager']);     // Coordenador
        Role::create(['name' => 'cabinet']);        // Assessor
    }
}
