<?php

namespace App\Policies;

use App\Models\CampanhaPolitico;
use App\Models\User;

class CampanhaPoliticoPolicy
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

    public function view(User $user, CampanhaPolitico $politico): bool
    {
        return $user->deputado_id === $politico->deputado_id
            && $user->hasAnyRole(['admin', 'manager', 'cabinet']);
    }

    public function create(User $user): bool
    {
        // Decisão explícita: os três papéis do gabinete podem criar.
        return $user->hasAnyRole(['admin', 'manager', 'cabinet']);
    }

    public function update(User $user, CampanhaPolitico $politico): bool
    {
        return $user->deputado_id === $politico->deputado_id
            && $user->hasAnyRole(['admin', 'manager', 'cabinet']);
    }

    public function delete(User $user, CampanhaPolitico $politico): bool
    {
        // Decisão explícita: diferente de lideranças, cabinet também pode excluir.
        return $user->deputado_id === $politico->deputado_id
            && $user->hasAnyRole(['admin', 'manager', 'cabinet']);
    }
}
