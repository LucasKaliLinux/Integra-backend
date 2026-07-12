<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AtividadeLog;
use App\Models\AtividadeSessao;
use App\Models\Deputado;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Leitura do Log de Atividades para o painel super_admin (aba "Atividades" nos
 * detalhes de um deputado). Tudo é rigorosamente escopado por deputado_id da
 * rota — sem vazamento entre deputados (sem IDOR).
 */
class AtividadeController extends Controller
{
    /** Períodos aceitos (em dias). */
    private const PERIODOS = [
        '7d' => 7,
        '30d' => 30,
        '90d' => 90,
        '180d' => 180,
        '365d' => 365,
    ];

    /**
     * Dashboard agregado de atividades do deputado.
     * GET /super-admin/deputados/{id}/atividades?periodo=30d
     */
    public function index(Request $request, int $id)
    {
        $deputado = Deputado::findOrFail($id);

        $dias = self::PERIODOS[$request->query('periodo', '30d')] ?? 30;
        $inicio = now()->startOfDay()->subDays($dias - 1);
        $fim = now();

        // ---- KPIs ----
        $totalLogins = AtividadeLog::where('deputado_id', $deputado->id)
            ->where('categoria', 'auth')
            ->where('acao', 'login')
            ->whereBetween('created_at', [$inicio, $fim])
            ->count();

        $usuariosAtivos = AtividadeLog::where('deputado_id', $deputado->id)
            ->whereNotNull('user_id')
            ->whereBetween('created_at', [$inicio, $fim])
            ->distinct('user_id')
            ->count('user_id');

        $totalEventos = AtividadeLog::where('deputado_id', $deputado->id)
            ->whereBetween('created_at', [$inicio, $fim])
            ->count();

        $tempoMedioSessao = (int) round(
            (float) AtividadeSessao::where('deputado_id', $deputado->id)
                ->whereNotNull('duracao_segundos')
                ->where('iniciada_em', '>=', $inicio)
                ->avg('duracao_segundos')
        );

        // ---- Módulos (agrupados por categoria, excluindo auth) ----
        $modulos = AtividadeLog::where('deputado_id', $deputado->id)
            ->where('categoria', '!=', 'auth')
            ->whereBetween('created_at', [$inicio, $fim])
            ->select('categoria', DB::raw('COUNT(*) as total'))
            ->groupBy('categoria')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($linha) => [
                'categoria' => $linha->categoria,
                'total' => (int) $linha->total,
            ]);

        // ---- Usuários mais ativos ----
        $usuariosMaisAtivos = AtividadeLog::where('atividade_logs.deputado_id', $deputado->id)
            ->whereNotNull('user_id')
            ->whereBetween('atividade_logs.created_at', [$inicio, $fim])
            ->join('users', 'users.id', '=', 'atividade_logs.user_id')
            ->select('users.id as user_id', 'users.name as nome', DB::raw('COUNT(*) as total'))
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->limit(10)
            ->get()
            ->map(fn ($linha) => [
                'user_id' => (int) $linha->user_id,
                'nome' => $linha->nome,
                'total' => (int) $linha->total,
            ]);

        // ---- Distribuição por horário (0..23, preenchendo zeros) ----
        $porHora = AtividadeLog::where('deputado_id', $deputado->id)
            ->whereBetween('created_at', [$inicio, $fim])
            ->select(DB::raw('HOUR(created_at) as hora'), DB::raw('COUNT(*) as total'))
            ->groupBy('hora')
            ->pluck('total', 'hora');

        $distribuicaoHoraria = collect(range(0, 23))->map(fn ($hora) => [
            'hora' => $hora,
            'total' => (int) ($porHora[$hora] ?? 0),
        ])->values();

        // ---- Evolução ao longo do período (preenchendo dias sem eventos) ----
        $porDia = AtividadeLog::where('deputado_id', $deputado->id)
            ->whereBetween('created_at', [$inicio, $fim])
            ->select(DB::raw('DATE(created_at) as dia'), DB::raw('COUNT(*) as total'))
            ->groupBy('dia')
            ->pluck('total', 'dia');

        $evolucao = collect();
        for ($cursor = $inicio->copy(); $cursor->lte($fim); $cursor->addDay()) {
            $chave = $cursor->format('Y-m-d');
            $evolucao->push([
                'data' => $chave,
                'total' => (int) ($porDia[$chave] ?? 0),
            ]);
        }

        return response()->json([
            'periodo' => [
                'inicio' => $inicio->format('Y-m-d'),
                'fim' => $fim->format('Y-m-d'),
                'dias' => $dias,
            ],
            'kpis' => [
                'total_logins' => $totalLogins,
                'usuarios_ativos' => $usuariosAtivos,
                'tempo_medio_sessao_segundos' => $tempoMedioSessao,
                'total_eventos' => $totalEventos,
            ],
            'modulos_mais_usados' => $modulos->take(5)->values(),
            'modulos_menos_usados' => $modulos->reverse()->take(5)->values(),
            'usuarios_mais_ativos' => $usuariosMaisAtivos,
            'distribuicao_horaria' => $distribuicaoHoraria,
            'evolucao' => $evolucao->values(),
        ]);
    }

    /**
     * Timeline paginada de eventos do deputado.
     * GET /super-admin/deputados/{id}/atividades/timeline?page=1&per_page=30
     */
    public function timeline(Request $request, int $id)
    {
        $deputado = Deputado::findOrFail($id);

        $validated = $request->validate([
            'per_page' => 'sometimes|integer|min:1|max:100',
            'categoria' => 'sometimes|string|max:40',
        ], [], [
            'per_page' => 'itens por página',
        ]);

        $perPage = $validated['per_page'] ?? 30;

        $query = AtividadeLog::where('deputado_id', $deputado->id)
            ->with('user:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if (! empty($validated['categoria'])) {
            $query->where('categoria', $validated['categoria']);
        }

        $logs = $query->paginate($perPage)->withQueryString();

        $logs->getCollection()->transform(fn (AtividadeLog $log) => [
            'id' => $log->id,
            'categoria' => $log->categoria,
            'acao' => $log->acao,
            'recurso_tipo' => $log->recurso_tipo,
            'recurso_id' => $log->recurso_id,
            'metodo_http' => $log->metodo_http,
            'rota' => $log->rota,
            'plataforma' => $log->plataforma,
            'navegador' => $log->navegador,
            'contexto' => $log->contexto,
            'usuario' => $log->user ? [
                'id' => $log->user->id,
                'nome' => $log->user->name,
            ] : null,
            'created_at' => optional($log->created_at)->format('d/m/Y H:i'),
        ]);

        return response()->json($logs);
    }
}
