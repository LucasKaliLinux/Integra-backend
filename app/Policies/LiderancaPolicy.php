<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Lideranca;

class LiderancaPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }
        return null;
    }

    public function viewAny(User $user): bool
    {
        return true; // Qualquer usuário logado pode listar
    }

    public function view(User $user, Lideranca $lideranca): bool
    {
        return $user->deputado_id === $lideranca->deputado_id;
    }

    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'cabinet']);
    }

    public function update(User $user, Lideranca $lideranca): bool
    {
        return $user->deputado_id === $lideranca->deputado_id
            && $user->hasAnyRole(['admin', 'manager', 'cabinet']);
    }

    public function delete(User $user, Lideranca $lideranca): bool
    {
        return $user->deputado_id === $lideranca->deputado_id
            && $user->hasAnyRole(['admin', 'manager']);
    }
}