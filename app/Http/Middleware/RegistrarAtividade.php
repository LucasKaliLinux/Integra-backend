<?php

namespace App\Http\Middleware;

use App\Models\AtividadeLog;
use App\Models\AtividadeSessao;
use App\Support\AgenteUsuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware terminável que registra o uso do sistema pelo gabinete.
 *
 * Grava no terminate() (pós-resposta, latência ~0) e é 100% silencioso:
 * qualquer falha é engolida — logar jamais pode quebrar o request.
 *
 * Regras:
 *  - só loga em resposta 2xx;
 *  - deputado_id sempre do usuário autenticado (nunca de path/body);
 *  - resolve Controller@ação contra config('atividades.mapa'); sem match ou em
 *    denylist → não grava;
 *  - camada B (navegação/GET) tem throttle de 1 evento por (user, categoria)
 *    a cada 10 min;
 *  - sinal de presença: todo request autenticado do gabinete atualiza a
 *    sessão aberta, mesmo sem gerar evento;
 *  - nunca persiste IP nem user-agent bruto (só buckets).
 */
class RegistrarAtividade
{
    /** TTL do throttle de camada B (navegação/GET), em minutos. */
    private const THROTTLE_VISITA_MIN = 10;

    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $user = $request->user();

            // Só usuários do gabinete (com deputado). super_admin não tem deputado_id.
            if (! $user || ! $user->deputado_id || $user->hasRole('super_admin')) {
                return;
            }

            $route = $request->route();

            if (! $route instanceof RoutingRoute) {
                return;
            }

            // Sinal de presença — em TODO request autenticado do gabinete.
            $sessao = $this->tocarSessao($user->id);

            // A partir daqui, só eventos de resposta bem-sucedida (2xx).
            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                return;
            }

            $chave = $this->chaveAcao($route);
            if ($chave === null) {
                return;
            }

            if (in_array($chave, config('atividades.denylist', []), true)) {
                return;
            }

            $def = config('atividades.mapa', [])[$chave] ?? null;
            if ($def === null) {
                return;
            }

            // Throttle de camada B: 1 evento por (user, categoria) a cada 10 min.
            if (($def['camada'] ?? 'A') === 'B') {
                $cacheKey = "atividade:visita:{$user->id}:{$def['categoria']}";

                if (! Cache::add($cacheKey, 1, now()->addMinutes(self::THROTTLE_VISITA_MIN))) {
                    return;
                }
            }

            $ua = $request->userAgent();

            AtividadeLog::create([
                'deputado_id' => $user->deputado_id,
                'user_id' => $user->id,
                'sessao_id' => $sessao?->id,
                'categoria' => $def['categoria'],
                'acao' => $def['acao'],
                'recurso_tipo' => $def['recurso_tipo'] ?? null,
                'recurso_id' => $this->extrairRecursoId($route),
                'metodo_http' => $request->method(),
                'rota' => $this->rotaPadrao($route),
                'plataforma' => AgenteUsuario::plataforma($ua),
                'navegador' => AgenteUsuario::navegador($ua),
                'contexto' => null,
            ]);
        } catch (\Throwable $e) {
            // Silencioso: logar atividade nunca pode impactar o request.
            report($e);
        }
    }

    /**
     * Atualiza `ultima_atividade_em` da sessão aberta do usuário (UPDATE de 1
     * linha) e a devolve para vincular ao log. Não cria sessão aqui — sessões
     * nascem no login.
     */
    private function tocarSessao(int $userId): ?AtividadeSessao
    {
        $sessao = AtividadeSessao::where('user_id', $userId)
            ->whereNull('encerrada_em')
            ->latest('iniciada_em')
            ->first();

        if ($sessao) {
            $sessao->forceFill(['ultima_atividade_em' => now()])->save();
        }

        return $sessao;
    }

    /** `class_basename(controller).'@'.metodo`, ou null se não for controller. */
    private function chaveAcao(RoutingRoute $route): ?string
    {
        $action = $route->getActionName();

        if (! str_contains($action, '@')) {
            return null;
        }

        [$controller, $metodo] = explode('@', $action, 2);

        return class_basename($controller).'@'.$metodo;
    }

    /** Primeiro parâmetro de rota numérico, cru (sem validação cross-deputado). */
    private function extrairRecursoId(RoutingRoute $route): ?int
    {
        foreach ($route->parameters() as $valor) {
            if (is_numeric($valor)) {
                return (int) $valor;
            }
        }

        return null;
    }

    /** Padrão de rota do Laravel, sem o prefixo `api/` e sem query string. */
    private function rotaPadrao(RoutingRoute $route): string
    {
        return preg_replace('#^api/#', '', $route->uri());
    }
}
