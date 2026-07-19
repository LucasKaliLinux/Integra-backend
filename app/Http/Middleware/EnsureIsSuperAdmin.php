<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureIsSuperAdmin
{
    /**
     * Garante que o usuário é super_admin
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user->hasRole('super_admin')) {
            return response()->json([
                'message' => 'Acesso negado. Apenas super administradores.',
            ], 403);
        }

        return $next($request);
    }
}
