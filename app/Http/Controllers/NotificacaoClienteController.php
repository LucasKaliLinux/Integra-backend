<?php

namespace App\Http\Controllers;

use App\Models\Notificacao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificacaoClienteController extends Controller
{
    /**
     * Lista notificações do usuário
     */
    public function index(Request $request)
    {
        $user = $request->user();

        // Notificações que o usuário DEVE ver
        $notificacoes = Notificacao::where('enviada', true)
            ->where('data_envio', '<=', now())
            ->where(function ($query) use ($user) {
                $query->where('audiencia', 'todos')
                    ->orWhereHas('deputados', function ($q) use ($user) {
                        $q->where('deputados.id', $user->deputado_id);
                    });
            })
            ->with(['leituras' => function ($q) use ($user) {
                $q->where('user_id', $user->id);
            }])
            ->orderBy('created_at', 'desc')
            ->take(15)
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'titulo' => $n->titulo,
                'mensagem' => $n->mensagem,
                'lida' => $n->leituras->isNotEmpty(),
                'lida_em' => $n->leituras->first()?->pivot->lida_em,
                'created_at' => $n->created_at->format('d/m/Y H:i'),
            ]);

        return response()->json($notificacoes);
    }

    /**
     * Marca todas como lidas
     */
    public function markAllAsRead(Request $request)
    {
        $user = $request->user();

        // Pega IDs de notificações NÃO lidas
        $notificacoesNaoLidas = Notificacao::where('enviada', true)
            ->where('data_envio', '<=', now())
            ->where(function ($query) use ($user) {
                $query->where('audiencia', 'todos')
                    ->orWhereHas('deputados', function ($q) use ($user) {
                        $q->where('deputados.id', $user->deputado_id);
                    });
            })
            ->whereDoesntHave('leituras', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->pluck('id');

        // Marca como lida (insert ignore duplicates)
        foreach ($notificacoesNaoLidas as $notifId) {
            DB::table('notificacao_leituras')->insertOrIgnore([
                'notificacao_id' => $notifId,
                'user_id' => $user->id,
                'lida_em' => now(),
            ]);
        }

        return response()->json(['message' => 'Notificações marcadas como lidas']);
    }

    /**
     * Conta não lidas
     */
    public function unreadCount(Request $request)
    {
        $user = $request->user();

        $count = Notificacao::where('enviada', true)
            ->where('data_envio', '<=', now())
            ->where(function ($query) use ($user) {
                $query->where('audiencia', 'todos')
                    ->orWhereHas('deputados', function ($q) use ($user) {
                        $q->where('deputados.id', $user->deputado_id);
                    });
            })
            ->whereDoesntHave('leituras', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            })
            ->count();

        return response()->json(['unread_count' => $count]);
    }
}
