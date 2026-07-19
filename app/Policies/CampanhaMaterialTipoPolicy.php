<?php

namespace App\Policies;

use App\Models\CampanhaMaterialTipo;
use App\Models\User;

class CampanhaMaterialTipoPolicy
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
        // Listagem sempre escopada pelo deputado do usuário; a role é exigida
        // como defesa em profundidade, igual às abilities de escrita.
        return $user->hasAnyRole(['admin', 'manager', 'cabinet']);
    }

    public function view(User $user, CampanhaMaterialTipo $tipo): bool
    {
        return $user->deputado_id === $tipo->deputado_id
            && $user->hasAnyRole(['admin', 'manager', 'cabinet']);
    }

    public function create(User $user): bool
    {
        // Decisão explícita: os três papéis do gabinete podem criar.
        return $user->hasAnyRole(['admin', 'manager', 'cabinet']);
    }

    public function update(User $user, CampanhaMaterialTipo $tipo): bool
    {
        return $user->deputado_id === $tipo->deputado_id
            && $user->hasAnyRole(['admin', 'manager', 'cabinet']);
    }

    public function delete(User $user, CampanhaMaterialTipo $tipo): bool
    {
        // Decisão explícita: diferente de lideranças, cabinet também pode excluir.
        return $user->deputado_id === $tipo->deputado_id
            && $user->hasAnyRole(['admin', 'manager', 'cabinet']);
    }
}
