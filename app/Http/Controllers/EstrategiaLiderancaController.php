<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Helpers\StringHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLiderancaPoliticaRequest;
use App\Http\Resources\EstrategiaLiderancaResource;
use App\Models\CargoLideranca;
use App\Models\ClassificacaoLideranca;
use App\Models\Lideranca;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class EstrategiaLiderancaController extends Controller
{
    private const ANO_ELEICAO = 2024;

    public function index(Request $request, $id)
    {
        $perPage = min($request->input('per_page', 12), 20);
        $page = $request->input('page', 1);

        $cacheKey = "candidatos_municipio_{$id}_page_{$page}_per_{$perPage}";
        
        $dados = Cache::remember($cacheKey, 3600, function() use ($id, $perPage, $page) {
            
            $prefeitos = DB::table('votacao as v')
                ->join('candidaturas as c', 'v.titulo_eleitoral_candidato', '=', 'c.titulo_eleitoral')
                ->where('v.id_municipio', $id)
                ->where('v.ano', self::ANO_ELEICAO)
                ->where('v.cargo', 'prefeito')
                ->select([
                    DB::raw('MAX(v.sequencial_candidato) as sequencial_candidato'),
                    'v.cargo',
                    'v.sigla_partido',
                    DB::raw('MAX(v.votos) as votos'),
                    DB::raw('MAX(v.resultado) as resultado'),
                    'v.ano',
                    DB::raw('MAX(c.nome) as nome'),
                    DB::raw('MAX(c.nome_urna) as nome_urna')
                ])
                ->groupBy('v.titulo_eleitoral_candidato', 'v.cargo', 'v.sigla_partido', 'v.ano')
                ->orderByDesc('votos')
                ->get();

            $vereadores = DB::table('votacao as v')
                ->join('candidaturas as c', 'v.titulo_eleitoral_candidato', '=', 'c.titulo_eleitoral')
                ->where('v.id_municipio', $id)
                ->where('v.ano', self::ANO_ELEICAO)
                ->where('v.cargo', 'vereador')
                ->select([
                    DB::raw('MAX(v.sequencial_candidato) as sequencial_candidato'),
                    'v.cargo',
                    'v.sigla_partido',
                    DB::raw('MAX(v.votos) as votos'),
                    DB::raw('MAX(v.resultado) as resultado'),
                    'v.ano',
                    DB::raw('MAX(c.nome) as nome'),
                    DB::raw('MAX(c.nome_urna) as nome_urna')
                ])
                ->groupBy('v.titulo_eleitoral_candidato', 'v.cargo', 'v.sigla_partido', 'v.ano')
                ->orderByDesc('votos')
                ->paginate($perPage, ['*'], 'page', $page);

            return [
                'prefeitos' => $prefeitos,
                'vereadores' => $vereadores
            ];
        });

        return response()->json([
            'prefeito'  => EstrategiaLiderancaResource::collection($dados['prefeitos']),
            'vereadores' => [
                'data'         => EstrategiaLiderancaResource::collection(collect($dados['vereadores']->items())),
                'current_page' => $dados['vereadores']->currentPage(),
                'last_page'    => $dados['vereadores']->lastPage(),
                'per_page'     => $dados['vereadores']->perPage(),
                'total'        => $dados['vereadores']->total(),
                'from'         => $dados['vereadores']->firstItem(),
                'to'           => $dados['vereadores']->lastItem()
            ]
        ]);
    }

    public function selecionados(Request $request, $idMunicipio)
    {
        $user = $request->user();

        $selecionados = DB::table('liderancas_politicas as lp')
            ->join('liderancas as l', 'lp.lideranca_id', '=', 'l.id')
            ->where('l.deputado_id', $user->deputado_id)
            ->where('l.id_municipio', $idMunicipio)
            ->where('lp.ano', self::ANO_ELEICAO)
            ->pluck('lp.sequencial_candidato')
            ->toArray();

        return response()->json(['selecionados' => $selecionados]);
    }

    public function store(StoreLiderancaPoliticaRequest $request)
    {
        $this->authorize('create', Lideranca::class);

        $user = $request->user();
        $idMunicipio = $request->id_municipio;
        $aliados = $request->aliados ?? [];
        $oposicao = $request->oposicao ?? [];
        
        $todosSelecionados = array_merge($aliados, $oposicao);

        if (empty($aliados) && empty($oposicao)) {
            return response()->json([
                'error' => 'Selecione pelo menos um candidato.'
            ], 422);
        }

        DB::beginTransaction();

        try {
            $idsValidos = DB::table('votacao')
                ->where('id_municipio', $idMunicipio)
                ->where('ano', self::ANO_ELEICAO)
                ->whereIn('cargo', ['prefeito', 'vereador'])
                ->whereIn('sequencial_candidato', $todosSelecionados)
                ->pluck('sequencial_candidato')
                ->toArray();

            if (count($idsValidos) !== count($todosSelecionados)) {
                $idsInvalidos = array_diff($todosSelecionados, $idsValidos);
                return response()->json([
                    'error' => 'IDs inválidos encontrados.',
                    'ids_invalidos' => array_values($idsInvalidos)
                ], 422);
            }

            $candidatos = DB::table('votacao as v')
                ->join('candidaturas as c', 'v.titulo_eleitoral_candidato', '=', 'c.titulo_eleitoral')
                ->where('v.id_municipio', $idMunicipio)
                ->where('v.ano', self::ANO_ELEICAO)
                ->whereIn('v.cargo', ['prefeito', 'vereador'])
                ->whereIn('v.sequencial_candidato', $todosSelecionados)
                ->select([
                    DB::raw('MAX(v.sequencial_candidato) as sequencial_candidato'),
                    'v.titulo_eleitoral_candidato',
                    'v.cargo',
                    DB::raw('MAX(v.resultado) as resultado'),
                    DB::raw('MAX(c.nome) as nome'),
                    DB::raw('MAX(c.nome_urna) as nome_urna'),
                    DB::raw('MAX(c.data_nascimento) as data_nascimento')
                ])
                ->groupBy('v.titulo_eleitoral_candidato', 'v.cargo')
                ->get();

            $sequenciais = $candidatos->pluck('sequencial_candidato')->toArray();
            
            $jaExiste = DB::table('liderancas_politicas')
                ->whereIn('sequencial_candidato', $sequenciais)
                ->where('ano', self::ANO_ELEICAO)
                ->exists();

            if ($jaExiste) {
                DB::rollBack();
                return response()->json([
                    'error' => 'Um ou mais candidatos já estão cadastrados como lideranças políticas.'
                ], 422);
            }

            $classificacaoPolitica = Cache::remember('classificacao_politica', 3600, function () {
                return ClassificacaoLideranca::where('slug', 'politica')->first();
            });

            if (!$classificacaoPolitica) {
                DB::rollBack();
                return response()->json(['error' => 'Classificação "politica" não encontrada.'], 500);
            }

            $cargosMap = Cache::remember("cargos_politica_{$classificacaoPolitica->id}", 3600, function () use ($classificacaoPolitica) {
                return $classificacaoPolitica->cargos()
                    ->whereIn('slug', ['prefeito', 'vereador'])
                    ->pluck('id', 'slug')
                    ->toArray();
            });

            if (empty($cargosMap['prefeito']) || empty($cargosMap['vereador'])) {
                DB::rollBack();
                return response()->json(['error' => 'Cargos "prefeito" ou "vereador" não encontrados.'], 500);
            }

            $liderancasParaInserir = [];

            foreach ($candidatos as $candidato) {
                $alinhamento = in_array($candidato->sequencial_candidato, $aliados) ? 'aliado' : 'oposicao';
                $funcaoId = $cargosMap[$candidato->cargo] ?? null;

                if (!$funcaoId) continue;

                $resultadoMapeado = str_starts_with($candidato->resultado, 'eleito') ? 'eleito' : 'nao_eleito';

                $liderancasParaInserir[] = [
                    'deputado_id' => $user->deputado_id,
                    'user_id' => $user->id,
                    'id_municipio' => $idMunicipio,
                    'classificacao_id' => $classificacaoPolitica->id,
                    'funcao_id' => $funcaoId,
                    'nome' => StringHelper::formatarNome($candidato->nome_urna),
                    'telefone' => null,
                    'data_nascimento' => $candidato->data_nascimento,
                    'alinhamento' => $alinhamento,
                    'observacao' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                    '_sequencial_candidato' => $candidato->sequencial_candidato,
                    '_titulo_eleitoral' => $candidato->titulo_eleitoral_candidato,
                    '_resultado' => $resultadoMapeado,
                ];
            }

            foreach ($liderancasParaInserir as $lideranca) {
                $sequencialCandidato = $lideranca['_sequencial_candidato'];
                $tituloEleitoral = $lideranca['_titulo_eleitoral'];
                $resultado = $lideranca['_resultado'];

                unset(
                    $lideranca['_sequencial_candidato'],
                    $lideranca['_titulo_eleitoral'],
                    $lideranca['_resultado']
                );

                $liderancaId = DB::table('liderancas')->insertGetId($lideranca);

                DB::table('liderancas_politicas')->insert([
                    'lideranca_id' => $liderancaId,
                    'sequencial_candidato' => $sequencialCandidato,
                    'ano' => self::ANO_ELEICAO,
                    'titulo_eleitoral' => $tituloEleitoral,
                    'resultado' => $resultado,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);
            }

            DB::commit();

            CacheHelper::invalidarMunicipio($idMunicipio, $request->user()->id);

            return response()->json([
                'message' => 'Lideranças políticas cadastradas com sucesso.',
                'total_cadastrados' => count($liderancasParaInserir),
                'aliados' => count(array_filter($liderancasParaInserir, fn($l) => $l['alinhamento'] === 'aliado')),
                'oposicao' => count(array_filter($liderancasParaInserir, fn($l) => $l['alinhamento'] === 'oposicao'))
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'error' => 'Erro ao cadastrar lideranças políticas.',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
