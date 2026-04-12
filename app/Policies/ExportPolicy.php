<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Export;

class ExportPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }
        return null;
    }

    public function view(User $user, Export $export): bool
    {
        return $user->deputado_id === $export->deputado_id;
    }

    public function create(User $user): bool
    {
        return true; // Qualquer um pode exportar
    }

    public function download(User $user, Export $export): bool
    {
        return $user->deputado_id === $export->deputado_id;
    }
}