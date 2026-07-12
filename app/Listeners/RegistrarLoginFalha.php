<?php

namespace App\Listeners;

use App\Models\AtividadeLog;
use App\Models\User;
use App\Support\AgenteUsuario;
use Illuminate\Auth\Events\Failed;

/**
 * Falha de login. PRIVACIDADE: o e-mail digitado NUNCA é persistido — é usado
 * apenas (normalizado) para casar contra usuários existentes E ativos. Só grava
 * quando casa com um usuário do gabinete; tentativa anônima não gera registro
 * (defesa de brute force é o throttle:login, não este log).
 */
class RegistrarLoginFalha
{
    public function handle(Failed $event): void
    {
        try {
            $email = strtolower(trim((string) ($event->credentials['email'] ?? '')));

            if ($email === '') {
                return;
            }

            $user = User::where('email', $email)
                ->where('ativo', true)
                ->first();

            // Não casou (tentativa anônima) → não grava nada.
            // super_admin ou usuário sem deputado → fora do escopo.
            if (! $user || ! $user->deputado_id || $user->hasRole('super_admin')) {
                return;
            }

            $ua = request()?->userAgent();

            AtividadeLog::create([
                'deputado_id' => $user->deputado_id,
                'user_id' => $user->id,
                'sessao_id' => null,
                'categoria' => 'auth',
                'acao' => 'login_falha',
                'recurso_tipo' => null,
                'recurso_id' => null,
                'metodo_http' => 'POST',
                'rota' => 'login',
                'plataforma' => AgenteUsuario::plataforma($ua),
                'navegador' => AgenteUsuario::navegador($ua),
                'contexto' => null,
            ]);

            // $email é descartado ao sair do escopo — nunca persistido.
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
