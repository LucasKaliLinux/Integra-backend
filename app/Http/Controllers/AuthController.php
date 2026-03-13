<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

class AuthController extends Controller
{
    // public function senha(){
    //     $senha = Hash::make('Vb102030@');
    //     return response()->json([
    //         'hash' => $senha
    //     ]);
    // }

    public function login(LoginRequest $request){

        if (!Auth::attempt($request->validated())) {
            return response()->json([
                'message' => 'Credenciais inválidas'
            ], 401);
        }

        $user = $request->user();

        $token = $user->createToken('api_token')->plainTextToken;

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    public function me(Request $request) {
        return response()->json($request->user(), 200);
    }

    public function dashboard(Request $request)
    {
        $user = $request->user();

        // Cache por 30 minutos (dashboard muda com frequência)
        $dados = Cache::remember("dashboard_{$user->id}", 1800, function() use ($user) {
            
            // ========== KPIs ========== 
            
            // 1️⃣ Total de municípios selecionados
            $totalMunicipios = $user->municipios()->count();

            // 2️⃣ Ano base mais recente
            $anoBase = DB::table('votacao')
                ->where('titulo_eleitoral_candidato', $user->titulo_eleitoral)
                ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
                ->max('ano');

            $totalEleitores = 0;
            $totalVotos = 0;
            
            if ($anoBase && $totalMunicipios > 0) {
                // IDs dos municípios selecionados
                $municipiosSelecionados = $user->municipios()->pluck('municipios.id_municipio');

                // 3️⃣ Total de eleitores (soma dos municípios selecionados)
                $totalEleitores = DB::table('eleitores')
                    ->whereIn('id_municipio', $municipiosSelecionados)
                    ->where('ano', $anoBase)
                    ->sum('total_eleitores');

                // 4️⃣ Total de votos (soma dos municípios selecionados)
                $totalVotos = DB::table('votacao')
                    ->where('titulo_eleitoral_candidato', $user->titulo_eleitoral)
                    ->whereIn('id_municipio', $municipiosSelecionados)
                    ->where('ano', $anoBase)
                    ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
                    ->sum('votos');
            }

            // 5️⃣ Total de lideranças
            $totalLiderancas = $user->liderancas()->count();

            // 6️⃣  VOTOS POTENCIAIS DAS LIDERANÇAS ALIADAS
            // $totalAcoes = $user->acoes()->count();
            $votosPotenciais = 0;

            $municipiosComLiderancas = $user->liderancas()
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
                    // Tem prefeito aliado — usa só os votos dele
                    $votosPotenciais += $prefeitos->sum('votos');
                } else {
                    // Não tem prefeito — soma todos os vereadores aliados
                    $votosPotenciais += $liderancas->sum('votos');
                }
            }

            // ========== TOP MUNICÍPIOS ==========
            
            $topMunicipios = collect([]);
            
            if ($anoBase && $totalMunicipios > 0) {
                // Subquery de votos
                $subVotos = DB::table('votacao')
                    ->select('id_municipio', DB::raw('SUM(votos) as votos'))
                    ->where('titulo_eleitoral_candidato', $user->titulo_eleitoral)
                    ->where('ano', $anoBase)
                    ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
                    ->groupBy('id_municipio');

                // Query com score (igual autoSelect)
                $topMunicipios = DB::table('eleitores as e')
                    ->join('municipios as m', 'm.id_municipio', '=', 'e.id_municipio')
                    ->joinSub($subVotos, 'v', 'v.id_municipio', '=', 'e.id_municipio')
                    ->whereIn('e.id_municipio', $municipiosSelecionados) // ⬅️ SÓ os selecionados!
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
                    ->limit(8) // ⬅️ Top 5
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
            
            $acoesRecentes = $user->acoes()
                ->with([
                    'municipio:id_municipio,nome',
                    'orgao:id,nome,sigla',
                    'status:id,nome,slug'
                ])
                ->select([
                    'id',
                    'titulo',                    // ⬅️ Título da ação
                    'id_municipio',
                    'orgao_governo_id',          // ⬅️ Pra carregar órgão
                    'status_id',                 // ⬅️ Pra carregar status
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
                        'titulo' => $acao->titulo,                           // ⬅️ Mais descritivo
                        'municipio' => $acao->municipio?->nome ?? 'N/A',
                        'orgao' => $acao->orgao?->sigla ?? $acao->orgao?->nome ?? 'N/A', // ⬅️ Sigla ou nome
                        'valor' => (float) $acao->valor,
                        'status' => $acao->status?->nome ?? 'N/A',           // ⬅️ Nome do status
                        'ano' => $acao->ano,
                        'dias_atras' => $acao->created_at->diffForHumans()   // ⬅️ "há 2 dias"
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

    public function logout(Request $request){
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Token Revoked'
        ], 200);
    }
}
