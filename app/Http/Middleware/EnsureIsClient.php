<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsClient
{
    /**
     * Garante que o usuário é cliente (tem deputado_id) e NÃO é super_admin
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Super admin não pode acessar rotas de cliente
        if ($user->hasRole('super_admin')) {
            return response()->json([
                'message' => 'Super administradores não têm acesso a recursos de clientes',
            ], 403);
        }

        // Verifica se tem deputado vinculado
        if (! $user->deputado_id) {
            return response()->json([
                'message' => 'Usuário não vinculado a nenhum deputado',
            ], 403);
        }

        // Carrega deputado no request pra evitar N+1
        $request->merge(['deputado' => $user->deputado]);

        return $next($request);
    }
}
