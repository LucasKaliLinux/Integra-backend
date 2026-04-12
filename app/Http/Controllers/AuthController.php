<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Acao;
use App\Models\Lideranca;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    public function metadata(Request $request)
    {
        $user = $request->user();

        $dados = Cache::remember("acoes_metadata_{$user->id}", 1800, function() use ($user) {
            
            $stats = Acao::where('deputado_id', $user->deputado_id)
                ->selectRaw('
                    MIN(ano) as ano_min,
                    MAX(ano) as ano_max,
                    MIN(valor) as valor_min,
                    MAX(valor) as valor_max,
                    COUNT(*) as total_acoes
                ')
                ->first();

            if (!$stats || $stats->total_acoes === 0) {
                return [
                    'ano_min' => date('Y') - 5,
                    'ano_max' => date('Y'),
                    'valor_min' => 0,
                    'valor_max' => 1000000,
                    'total_acoes' => 0
                ];
            }

            return [
                'ano_min' => (int) $stats->ano_min,
                'ano_max' => (int) $stats->ano_max,
                'valor_min' => (float) $stats->valor_min,
                'valor_max' => (float) $stats->valor_max,
                'total_acoes' => (int) $stats->total_acoes
            ];
        });

        return response()->json($dados);
    }

    public function login(LoginRequest $request)
    {
        if (!Auth::attempt($request->validated())) {
            return response()->json([
                'message' => 'Credenciais inválidas'
            ], 401);
        }

        $user = $request->user();

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'deputado' => [
                    'id' => $user->deputado->id,
                    'nome' => $user->deputado->nome,
                    'partido' => $user->deputado->partido,
                    'cargo' => $user->deputado->cargo,
                ],
                'role' => $user->getRoleNames(), // ⬅️ NOVO: Spatie roles
            ]
        ]);
    }

    public function me(Request $request)
    {
        $user = $request->user();
        
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'deputado' => [
                'id' => $user->deputado->id,
                'nome' => $user->deputado->nome,
                'partido' => $user->deputado->partido,
                'cargo' => $user->deputado->cargo,
            ],
            'roles' => $user->getRoleNames(),
            'permissions' => $user->getAllPermissions()->pluck('name'),
        ], 200);
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();

        // ⬇️ MUDANÇA: Carrega deputado pra evitar N+1
        $deputado = $user->deputado;

        if (!$deputado) {
            return response()->json([
                'error' => 'Usuário não vinculado a nenhum deputado'
            ], 403);
        }

        $dados = Cache::remember("dashboard_{$user->id}", 1800, function() use ($user, $deputado) {
            
            // ========== KPIs ========== 
            
            // 1️⃣ Total de municípios selecionados
            $totalMunicipios = $deputado->municipios()->count(); // ⬅️ MUDANÇA

            // 2️⃣ Ano base mais recente
            $anoBase = DB::table('votacao')
                ->where('titulo_eleitoral_candidato', $deputado->titulo_eleitoral) // ⬅️ MUDANÇA
                ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
                ->max('ano');

            $totalEleitores = 0;
            $totalVotos = 0;
            
            if ($anoBase && $totalMunicipios > 0) {
                // IDs dos municípios selecionados
                $municipiosSelecionados = $deputado->municipios()->pluck('municipios.id_municipio'); // ⬅️ MUDANÇA

                // 3️⃣ Total de eleitores
                $totalEleitores = DB::table('eleitores')
                    ->whereIn('id_municipio', $municipiosSelecionados)
                    ->where('ano', $anoBase)
                    ->sum('total_eleitores');

                // 4️⃣ Total de votos
                $totalVotos = DB::table('votacao')
                    ->where('titulo_eleitoral_candidato', $deputado->titulo_eleitoral) // ⬅️ MUDANÇA
                    ->whereIn('id_municipio', $municipiosSelecionados)
                    ->where('ano', $anoBase)
                    ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
                    ->sum('votos');
            }

            // 5️⃣ Total de lideranças
            $totalLiderancas = Lideranca::where('deputado_id', $user->deputado_id)->count();

            // 6️⃣ Votos potenciais
            $votosPotenciais = 0;

            $municipiosComLiderancas = Lideranca::where('deputado_id', $user->deputado_id)
                ->join('liderancas_politicas as lp', 'lp.lideranca_id', '=', 'liderancas.id')
                ->join('votacao as v', function($join) {
                    $join->on('v.sequencial_candidato', '=', 'lp.sequencial_candidato')
                        ->on('v.ano', '=', 'lp.ano');
                })
                ->join('cargos_lideranca as cl', 'cl.id', '=', 'liderancas.funcao_id')
                ->where('liderancas.alinhamento', 'aliado')
                ->select([
                    'liderancas.id_municipio',
                    'cl.slug as cargo_slug',
                    'v.votos',
                ])
                ->get()
                ->groupBy('id_municipio');

            foreach ($municipiosComLiderancas as $idMunicipio => $liderancas) {
                $prefeitos = $liderancas->filter(fn($l) => $l->cargo_slug === 'prefeito');
                
                if ($prefeitos->isNotEmpty()) {
                    $votosPotenciais += $prefeitos->sum('votos');
                } else {
                    $votosPotenciais += $liderancas->sum('votos');
                }
            }

            // ========== TOP MUNICÍPIOS ==========
            
            $topMunicipios = collect([]);
            
            if ($anoBase && $totalMunicipios > 0) {
                // Subquery de votos
                $subVotos = DB::table('votacao')
                    ->select('id_municipio', DB::raw('SUM(votos) as votos'))
                    ->where('titulo_eleitoral_candidato', $deputado->titulo_eleitoral) // ⬅️ MUDANÇA
                    ->where('ano', $anoBase)
                    ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
                    ->groupBy('id_municipio');

                $topMunicipios = DB::table('eleitores as e')
                    ->join('municipios as m', 'm.id_municipio', '=', 'e.id_municipio')
                    ->joinSub($subVotos, 'v', 'v.id_municipio', '=', 'e.id_municipio')
                    ->whereIn('e.id_municipio', $municipiosSelecionados)
                    ->where('e.ano', $anoBase)
                    ->where('e.total_eleitores', '>', 0)
                    ->select([
                        'e.id_municipio',
                        'm.nome',
                        'v.votos',
                        'e.total_eleitores',
                        DB::raw('ROUND((v.votos / e.total_eleitores) * 100, 2) as percentual_votos'),
                        DB::raw('(v.votos * 0.7) + ((v.votos / e.total_eleitores) * e.total_eleitores * 0.3) as score')
                    ])
                    ->orderByDesc('score')
                    ->limit(8)
                    ->get()
                    ->map(function($item) {
                        return [
                            'id_municipio' => $item->id_municipio,
                            'nome' => $item->nome,
                            'votos' => (int) $item->votos,
                            'total_eleitores' => (int) $item->total_eleitores,
                            'percentual_votos' => (float) $item->percentual_votos
                        ];
                    });
            }

            // ========== AÇÕES RECENTES ==========
            
            $acoesRecentes = Acao::where('deputado_id', $user->deputado_id)
                ->with([
                    'municipio:id_municipio,nome',
                    'orgao:id,nome,sigla',
                    'status:id,nome,slug'
                ])
                ->select([
                    'id',
                    'titulo',
                    'id_municipio',
                    'orgao_governo_id',
                    'status_id',
                    'valor',
                    'ano',
                    'created_at'
                ])
                ->orderBy('created_at', 'DESC')
                ->limit(6)
                ->get()
                ->map(function($acao) {
                    return [
                        'id' => $acao->id,
                        'titulo' => $acao->titulo,
                        'municipio' => $acao->municipio?->nome ?? 'N/A',
                        'orgao' => $acao->orgao?->sigla ?? $acao->orgao?->nome ?? 'N/A',
                        'valor' => (float) $acao->valor,
                        'status' => $acao->status?->nome ?? 'N/A',
                        'ano' => $acao->ano,
                        'dias_atras' => $acao->created_at->diffForHumans()
                    ];
                });

            return [
                'kpis' => [
                    'total_municipios' => $totalMunicipios,
                    'total_eleitores' => (int) $totalEleitores,
                    'total_votos' => (int) $totalVotos,
                    'total_liderancas' => $totalLiderancas,
                    'votos_potenciais' => (int) $votosPotenciais,
                ],
                'top_municipios' => $topMunicipios,
                'acoes_recentes' => $acoesRecentes
            ];
        });

        return response()->json($dados);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Token Revoked'
        ], 200);
    }
}