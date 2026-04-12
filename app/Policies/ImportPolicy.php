<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Import;

class ImportPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }
        return null;
    }

    public function view(User $user, Import $import): bool
    {
        return $user->deputado_id === $import->deputado_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager']);
    }
}