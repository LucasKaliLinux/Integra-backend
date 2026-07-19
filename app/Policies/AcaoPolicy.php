<?php

namespace App\Policies;

use App\Models\Acao;
use App\Models\User;

class AcaoPolicy
{
    /**
     * Super admins podem tudo
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->hasRole('super_admin')) {
            return true;
        }

        return null;
    }

    /**
     * Ver ação (mesmo deputado)
     */
    public function view(User $user, Acao $acao): bool
    {
        return $user->deputado_id === $acao->deputado_id;
    }

    /**
     * Criar ação
     */
    public function create(User $user): bool
    {
        return $user->hasAnyRole(['admin', 'manager', 'cabinet']);
    }

    /**
     * Atualizar ação (mesmo deputado)
     */
    public function update(User $user, Acao $acao): bool
    {
        return $user->deputado_id === $acao->deputado_id
            && $user->hasAnyRole(['admin', 'manager', 'cabinet']);
    }

    /**
     * Deletar ação (apenas admin/manager)
     */
    public function delete(User $user, Acao $acao): bool
    {
        return $user->deputado_id === $acao->deputado_id
            && $user->hasAnyRole(['admin', 'manager']);
    }
}
