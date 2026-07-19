<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Jobs\MarcarNotificacaoComoEnviada;
use App\Models\Notificacao;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificacaoController extends Controller
{
    /**
     * Lista todas notificações
     */
    public function index()
    {
        $notificacoes = Notificacao::with('deputados:id,nome')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($n) => [
                'id' => $n->id,
                'titulo' => $n->titulo,
                'mensagem' => $n->mensagem,
                'data_envio' => $n->data_envio?->format('d/m/Y H:i'),
                'audiencia' => $n->audiencia,
                'deputados' => $n->audiencia === 'especifico'
                    ? $n->deputados->pluck('nome')
                    : null,
                'enviada' => $n->enviada,
                'visualizacoes' => $n->totalVisualizacoes(),
                'total_esperado' => $n->totalEsperado(),
                'created_at' => $n->created_at->format('d/m/Y H:i'),
            ]);

        return response()->json($notificacoes);
    }

    /**
     * Cria notificação
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'titulo' => 'required|string|max:255',
            'mensagem' => 'required|string',
            'data_envio' => 'nullable|date|after_or_equal:now',
            'audiencia' => 'required|in:todos,especifico',
            'deputados' => 'required_if:audiencia,especifico|array',
            'deputados.*' => 'exists:deputados,id',
        ], [
            'titulo.required' => 'O título é obrigatório.',
            'mensagem.required' => 'A mensagem é obrigatória.',
            'data_envio.after_or_equal' => 'A data de envio deve ser futura.',
            'audiencia.required' => 'A audiência é obrigatória.',
            'deputados.required_if' => 'Selecione pelo menos um deputado.',
        ]);

        DB::beginTransaction();
        try {
            $notificacao = Notificacao::create([
                'titulo' => $validated['titulo'],
                'mensagem' => $validated['mensagem'],
                'data_envio' => $validated['data_envio'] ?? now(),
                'audiencia' => $validated['audiencia'],
                'enviada' => is_null($validated['data_envio']), // Se NULL, envia agora
            ]);

            // Vincula deputados (se específico)
            if ($validated['audiencia'] === 'especifico') {
                $notificacao->deputados()->attach($validated['deputados']);
            }

            // Se agendada para o futuro, agenda o job que marca como enviada
            // quando a data chegar (requer o worker da fila em execução).
            if (! $notificacao->enviada) {
                MarcarNotificacaoComoEnviada::dispatch($notificacao)->delay($notificacao->data_envio);
            }

            DB::commit();

            return response()->json([
                'message' => 'Notificação criada com sucesso',
                'notificacao' => $notificacao,
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Erro ao criar notificação'], 500);
        }
    }

    /**
     * Detalhes da notificação (quem leu/não leu)
     */
    public function show(int $id)
    {
        $notificacao = Notificacao::with([
            'leituras.deputado:id,nome',
            'deputados',
        ])->findOrFail($id);

        $formatarDeputado = fn ($user) => $user->deputado ? [
            'id' => $user->deputado->id,
            'nome' => $user->deputado->nome,
        ] : null;

        $visualizaram = $notificacao->leituras->map(fn ($user) => [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'lida_em' => $user->pivot->lida_em,
            'deputado' => $formatarDeputado($user),
        ]);

        // Usuários que DEVERIAM ver mas NÃO leram
        if ($notificacao->audiencia === 'todos') {
            $todosUsers = User::whereNotNull('deputado_id')->with('deputado:id,nome')->get();
        } else {
            $deputadosIds = $notificacao->deputados->pluck('id');
            $todosUsers = User::whereIn('deputado_id', $deputadosIds)->with('deputado:id,nome')->get();
        }

        $naoVisualizaram = $todosUsers->filter(function ($user) use ($visualizaram) {
            return ! $visualizaram->contains('id', $user->id);
        })->map(fn ($u) => [
            'id' => $u->id,
            'name' => $u->name,
            'email' => $u->email,
            'deputado' => $formatarDeputado($u),
        ])->values();

        return response()->json([
            'notificacao' => [
                'id' => $notificacao->id,
                'titulo' => $notificacao->titulo,
                'mensagem' => $notificacao->mensagem,
                'data_envio' => $notificacao->data_envio->format('d/m/Y H:i'),
                'audiencia' => $notificacao->audiencia,
            ],
            'visualizaram' => $visualizaram,
            'nao_visualizaram' => $naoVisualizaram,
            'total_visualizaram' => $visualizaram->count(),
            'total_nao_visualizaram' => $naoVisualizaram->count(),
        ]);
    }

    /**
     * Deleta notificação
     */
    public function destroy(int $id)
    {
        $notificacao = Notificacao::findOrFail($id);
        $notificacao->delete();

        return response()->json(['message' => 'Notificação deletada com sucesso']);
    }
}
