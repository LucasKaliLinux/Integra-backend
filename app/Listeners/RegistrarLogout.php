<?php

namespace App\Listeners;

use App\Models\AtividadeLog;
use App\Models\AtividadeSessao;
use App\Models\User;
use App\Support\AgenteUsuario;
use Illuminate\Auth\Events\Logout;

/**
 * No logout de um usuário do gabinete: fecha a sessão aberta (calculando a
 * duração) e grava o evento auth.logout.
 */
class RegistrarLogout
{
    public function handle(Logout $event): void
    {
        try {
            $user = $event->user;

            if (! $user instanceof User || ! $user->deputado_id || $user->hasRole('super_admin')) {
                return;
            }

            $sessao = AtividadeSessao::where('user_id', $user->id)
                ->whereNull('encerrada_em')
                ->latest('iniciada_em')
                ->first();

            if ($sessao) {
                $agora = now();
                $sessao->forceFill([
                    'encerrada_em' => $agora,
                    'duracao_segundos' => (int) abs($sessao->iniciada_em->diffInSeconds($agora)),
                ])->save();
            }

            $ua = request()?->userAgent();

            AtividadeLog::create([
                'deputado_id' => $user->deputado_id,
                'user_id' => $user->id,
                'sessao_id' => $sessao?->id,
                'categoria' => 'auth',
                'acao' => 'logout',
                'recurso_tipo' => null,
                'recurso_id' => null,
                'metodo_http' => 'POST',
                'rota' => 'logout',
                'plataforma' => AgenteUsuario::plataforma($ua),
                'navegador' => AgenteUsuario::navegador($ua),
                'contexto' => null,
            ]);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
