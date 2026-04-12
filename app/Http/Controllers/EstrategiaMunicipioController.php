<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Resources\AcaoMunicipioResource;
use App\Models\Acao;
use App\Models\Lideranca;
use App\Models\Municipio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EstrategiaMunicipioController extends Controller
{
    public function show(Request $request, $id)
    {
        $user = $request->user();

        $dados = Cache::remember("municipio_detalhes_{$id}_{$user->id}", 1800, function() use ($id, $user) {
            
            $municipio = Municipio::where('id_municipio', $id)
                ->select('id_municipio', 'nome')
                ->first();

            if (!$municipio) {
                return null;
            }

            $anoMaisRecenteGlobal = DB::table('eleitores')->max('ano');

            $totalEleitores = DB::table('eleitores')
                ->where('id_municipio', $id)
                ->where('ano', $anoMaisRecenteGlobal)
                ->value('total_eleitores') ?? 0;

            $anoMaisRecenteUser = DB::table('votacao')
                ->where('titulo_eleitoral_candidato', $user->deputado->titulo_eleitoral)
                ->where('id_municipio', $id)
                ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
                ->max('ano');

            $votosObtidos = 0;
            if ($anoMaisRecenteUser) {
                $votosObtidos = DB::table('votacao')
                    ->where('titulo_eleitoral_candidato', $user->deputado->titulo_eleitoral)
                    ->where('id_municipio', $id)
                    ->where('ano', $anoMaisRecenteUser)
                    ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
                    ->sum('votos');
            }

            $statusDirecionados = DB::table('status_acao')
                ->whereIn('slug', ['aprovada', 'em_execucao', 'concluida'])
                ->pluck('id');

            $investimentoDirecionado = Acao::where('deputado_id', $user->deputado_id)
                ->where('id_municipio', $id)
                ->whereIn('status_id', $statusDirecionados)
                ->sum('valor') ?? 0;

            $statusConcluida = DB::table('status_acao')
                ->where('slug', 'concluida')
                ->value('id');

            $acoesConcluidas = Acao::where('deputado_id', $user->deputado_id)
                ->where('id_municipio', $id)
                ->where('status_id', $statusConcluida)
                ->count();

            $statusNaoConcluidos = DB::table('status_acao')
                ->whereIn('slug', ['solicitado', 'em_articulacao', 'aprovada', 'em_execucao'])
                ->pluck('id');

            $acoesNaoConcluidas = Acao::where('deputado_id', $user->deputado_id)
                ->where('id_municipio', $id)
                ->whereIn('status_id', $statusNaoConcluidos)
                ->count();

            return [
                'id_municipio' => $municipio->id_municipio,
                'nome' => $municipio->nome,
                'total_eleitores' => (int) $totalEleitores,
                'ano_eleitores' => $anoMaisRecenteGlobal,
                'votos_obtidos' => (int) $votosObtidos,
                'ano_votos' => $anoMaisRecenteUser,
                'investimento_direcionado' => (float) $investimentoDirecionado,
                'acoes_concluidas' => $acoesConcluidas,
                'acoes_nao_concluidas' => $acoesNaoConcluidas,
                'acoes_total' => $acoesConcluidas + $acoesNaoConcluidas
            ];
        });

        if (!$dados) {
            return response()->json(['message' => 'Município não encontrado.'], 404);
        }

        return response()->json($dados);
    } 
    
    public function overview(Request $request, $id)
    {
        $user = $request->user();

        $dados = Cache::remember("municipio_overview_{$id}_{$user->id}", 3600, function() use ($id, $user) {
            
            $populacao = Municipio::where('id_municipio', $id)->value('populacao') ?? 0;

            $anoMaisRecenteGlobal = DB::table('eleitores')->max('ano');
            
            $totalEleitores = DB::table('eleitores')
                ->where('id_municipio', $id)
                ->where('ano', $anoMaisRecenteGlobal)
                ->value('total_eleitores') ?? 0;

            $evolucaoVotos = DB::table('votacao')
                ->select('ano', DB::raw('SUM(votos) as total_votos'))
                ->where('titulo_eleitoral_candidato', $user->deputado->titulo_eleitoral)
                ->where('id_municipio', $id)
                ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
                ->groupBy('ano')
                ->orderBy('ano', 'ASC')
                ->get();

            $potencialEleitoral = 'Sem histórico';
            
            if ($evolucaoVotos->count() >= 2) {
                $primeiroAno = $evolucaoVotos->first();
                $ultimoAno = $evolucaoVotos->last();
                
                $crescimentoPercentual = $primeiroAno->total_votos > 0
                    ? (($ultimoAno->total_votos - $primeiroAno->total_votos) / $primeiroAno->total_votos) * 100
                    : null;
                
                if ($crescimentoPercentual > 15) {
                    $potencialEleitoral = 'Alto';
                } elseif ($crescimentoPercentual > 0) {
                    $potencialEleitoral = 'Médio';
                } else {
                    $potencialEleitoral = 'Baixo';
                }
            } elseif ($evolucaoVotos->count() == 1) {
                $potencialEleitoral = 'Indefinido';
            }

            $liderancas = Lideranca::where('deputado_id', $user->deputado_id)
                ->select(
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN alinhamento = "aliado" THEN 1 ELSE 0 END) as aliados'),
                    DB::raw('SUM(CASE WHEN alinhamento = "oposicao" THEN 1 ELSE 0 END) as oposicao')
                )
                ->where('id_municipio', $id)
                ->first();

            $alinhamentoPolitico = 'Neutro';

            if ($liderancas && $liderancas->total > 0) {
                if ($liderancas->aliados > $liderancas->oposicao) {
                    $alinhamentoPolitico = 'Favorável';
                } elseif ($liderancas->oposicao > $liderancas->aliados) {
                    $alinhamentoPolitico = 'Desfavorável';
                }
            }

            $statusConcluida = DB::table('status_acao')->where('slug', 'concluida')->value('id');

            $acoesConcluidas = Acao::where('deputado_id', $user->deputado_id)
                ->with([
                    'orgao:id,sigla',
                    'categoriaInvestimento:id,nome'
                ])
                ->where('id_municipio', $id)
                ->where('status_id', $statusConcluida)
                ->select(['id', 'titulo', 'orgao_governo_id', 'categoria_investimento_id', 'valor'])
                ->orderBy('updated_at', 'DESC')
                ->limit(5)
                ->get()
                ->map(fn($a) => [
                    'id' => $a->id,
                    'titulo' => $a->titulo,
                    'orgao' => $a->orgao?->sigla ?? 'N/A',
                    'categoria' => $a->categoriaInvestimento?->nome ?? 'N/A',
                    'valor' => (float) $a->valor,
                ]);

            return [
                'populacao' => (int) $populacao,
                'total_eleitores' => (int) $totalEleitores,
                'ano_eleitores' => $anoMaisRecenteGlobal,
                'potencial_eleitoral' => $potencialEleitoral,
                'crescimento_percentual' => isset($crescimentoPercentual) ? round($crescimentoPercentual, 2) : null,
                'alinhamento_politico' => $alinhamentoPolitico,
                'liderancas' => [
                    'total' => (int) ($liderancas->total ?? 0),
                    'aliados' => (int) ($liderancas->aliados ?? 0),
                    'oposicao' => (int) ($liderancas->oposicao ?? 0)
                ],
                'acoes_concluidas' => $acoesConcluidas,
            ];
        });

        return response()->json($dados);
    }


    public function eleitoral(Request $request, $id)
    {
        $user = $request->user();

        $dados = Cache::remember("municipio_eleitoral_{$id}_{$user->id}", 3600, function() use ($id, $user) {
            
            return DB::table('votacao as v')
                ->leftJoin('eleitores as e', function($join) {
                    $join->on('e.id_municipio', '=', 'v.id_municipio')
                        ->on('e.ano', '=', 'v.ano');
                })
                ->select([
                    'v.ano',
                    DB::raw('SUM(v.votos) as votos_obtidos'),
                    DB::raw('MAX(e.total_eleitores) as total_eleitores')
                ])
                ->where('v.titulo_eleitoral_candidato', $user->deputado->titulo_eleitoral)
                ->where('v.id_municipio', $id)
                ->whereIn('v.cargo', ['deputado estadual', 'deputado federal'])
                ->groupBy('v.ano')
                ->orderBy('v.ano', 'ASC')
                ->get()
                ->map(function($item) {
                    return [
                        'ano' => (int) $item->ano,
                        'votos_obtidos' => (int) $item->votos_obtidos,
                        'total_eleitores' => (int) ($item->total_eleitores ?? 0)
                    ];
                });
        });

        return response()->json($dados);
    }


    public function estrutura(Request $request, $id)
    {
        $user = $request->user();

        $dados = [
            'liderancas' => collect(),
            'voto_previsao' => 0,
            'voto_previsao_label' => null,
        ];

        $liderancas = Lideranca::where('deputado_id', $user->deputado_id)
            ->with(['funcao:id,nome', 'classificacao:id,slug'])
            ->where('id_municipio', $id)
            ->select(['id', 'nome', 'funcao_id', 'classificacao_id', 'alinhamento'])
            ->orderBy('alinhamento', 'ASC')
            ->get();

        $dadosPoliticos = DB::table('liderancas_politicas as lp')
            ->join('votacao as v', function($join) {
                $join->on('v.sequencial_candidato', '=', 'lp.sequencial_candidato')
                    ->on('v.ano', '=', 'lp.ano');
            })
            ->join('cargos_lideranca as cl', function($join) use ($liderancas) {
                $join->on('cl.id', '=', DB::raw(
                    '(SELECT funcao_id FROM liderancas WHERE id = lp.lideranca_id LIMIT 1)'
                ));
            })
            ->whereIn('lp.lideranca_id', $liderancas->pluck('id'))
            ->select([
                'lp.lideranca_id',
                'lp.resultado',
                'cl.slug as cargo_slug',
                'v.votos',
                'v.sigla_partido',
            ])
            ->get()
            ->keyBy('lideranca_id');

        $lista = $liderancas->map(function($l) use ($dadosPoliticos) {
            $politico = $dadosPoliticos->get($l->id);

            return [
                'id'          => $l->id,
                'nome'        => $l->nome,
                'cargo'       => $l->funcao->nome ?? 'Sem cargo',
                'alinhamento' => $l->alinhamento,
                'is_politico' => $politico !== null,
                'eleito'      => $politico?->resultado === 'eleito',
                'votos'       => $politico ? (int) $politico->votos : null,
                'partido'     => $politico?->sigla_partido ?? null,
                'cargo_slug'  => $politico?->cargo_slug ?? null,
            ];
        });

        $politicosAliados = $lista->filter(fn($l) => $l['is_politico'] && $l['alinhamento'] === 'aliado');
        $prefeitos = $politicosAliados->filter(fn($l) => $l['cargo_slug'] === 'prefeito');

        if ($prefeitos->isNotEmpty()) {
            $votoPrevisao = $prefeitos->sum('votos');
            $votoPrevisaoLabel = 'Baseado no voto do Prefeito';
        } elseif ($politicosAliados->isNotEmpty()) {
            $votoPrevisao = $politicosAliados->sum('votos');
            $votoPrevisaoLabel = 'Somatória dos votos de Vereadores';
        } else {
            $votoPrevisao = 0;
            $votoPrevisaoLabel = null;
        }

        $dados = [
            'liderancas' => $lista->sortByDesc(fn($l) => $l['votos'] ?? 0)->values(),
            'voto_previsao'      => (int) $votoPrevisao,
            'voto_previsao_label' => $votoPrevisaoLabel,
        ];

        return response()->json($dados);
    }


    public function acoes(Request $request, $id)
    {
        $user = $request->user();
        $perPage = min($request->input('per_page', 10), 50);

        $acoes = Acao::where('deputado_id', $user->deputado_id)
            ->with([
                'liderancas:id,nome',
                'status:id,nome',
                'tipoAcao:id,nome',
                'categoriaInvestimento:id,nome',
                'orgao:id,nome,sigla'
            ])
            ->where('id_municipio', $id)
            ->select([
                'id',
                'titulo',
                'tipo_acao_id',
                'categoria_investimento_id',
                'orgao_governo_id',
                // 'lideranca_solicitante_id',
                'valor',
                'status_id'
            ])
            ->latest()
            ->paginate($perPage);

        return AcaoMunicipioResource::collection($acoes);
    }

    public function historico(Request $request, $id)
    {
        $user = $request->user();

        $dados = Cache::remember("municipio_historico_{$id}_{$user->id}", 1800, function () use ($id, $user) {

            return Acao::where('deputado_id', $user->deputado_id)
                ->with([
                    'liderancas:id,nome',
                    'categoriaInvestimento:id,nome',
                    'tipoAcao:id,nome',
                    'status:id,nome',
                ])
                ->where('id_municipio', $id)
                ->select([
                    'id',
                    'titulo',
                    'valor',
                    'ano',
                    'status_id',
                    'tipo_acao_id',
                    'categoria_investimento_id',
                    // 'lideranca_solicitante_id',
                    'created_at',
                ])
                ->orderBy('created_at', 'DESC')
                ->get()
                ->groupBy(fn($acao) => $acao->created_at->year)
                ->map(fn($acoesAno, $ano) => [
                    'ano' => $ano,
                    'total_acoes' => $acoesAno->count(),
                    'total_investido' => (float) $acoesAno->sum('valor'),
                    'meses' => $acoesAno
                        ->groupBy(fn($acao) => $acao->created_at->month)
                        ->map(fn($acoesMes, $mes) => [
                            'mes' => $mes,
                            'mes_nome' => now()->month($mes)->translatedFormat('F'),
                            'total_acoes' => $acoesMes->count(),
                            'total_investido' => (float) $acoesMes->sum('valor'),
                            'acoes' => $acoesMes->map(fn($acao) => [
                                'id'         => $acao->id,
                                'data'       => $acao->created_at->format('d/m/Y'),
                                'titulo'     => $acao->titulo,
                                'tipo'       => $acao->tipoAcao->nome ?? '-',
                                'categoria'  => $acao->categoriaInvestimento->nome ?? '-',
                                'responsavel'=> $acao->liderancas->first()?->nome ?? $user->name,
                                'valor'      => (float) $acao->valor,
                                'status'     => $acao->status->nome ?? '-',
                            ]),
                        ])
                        ->sortKeysDesc()
                        ->values(),
                ])
                ->sortKeysDesc()
                ->values();
        });

        return response()->json($dados);
    }


}
