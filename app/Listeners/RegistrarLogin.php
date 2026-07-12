<?php

namespace App\Listeners;

use App\Models\AtividadeLog;
use App\Models\AtividadeSessao;
use App\Models\User;
use App\Support\AgenteUsuario;
use Illuminate\Auth\Events\Login;

/**
 * No login de um usuário do gabinete: abre uma sessão e grava o evento
 * auth.login. Logins de super_admin (sem deputado) são ignorados.
 */
class RegistrarLogin
{
    public function handle(Login $event): void
    {
        try {
            $user = $event->user;

            if (! $user instanceof User || ! $user->deputado_id || $user->hasRole('super_admin')) {
                return;
            }

            $sessao = AtividadeSessao::create([
                'deputado_id' => $user->deputado_id,
                'user_id' => $user->id,
                'iniciada_em' => now(),
                'ultima_atividade_em' => now(),
                'origem' => 'login',
            ]);

            $ua = request()?->userAgent();

            AtividadeLog::create([
                'deputado_id' => $user->deputado_id,
                'user_id' => $user->id,
                'sessao_id' => $sessao->id,
                'categoria' => 'auth',
                'acao' => 'login',
                'recurso_tipo' => null,
                'recurso_id' => null,
                'metodo_http' => 'POST',
                'rota' => 'login',
                'plataforma' => AgenteUsuario::plataforma($ua),
                'navegador' => AgenteUsuario::navegador($ua),
                'contexto' => null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
