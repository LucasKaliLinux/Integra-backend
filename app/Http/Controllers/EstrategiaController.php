<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Http\Controllers\Controller;
use App\Http\Resources\MunicipioPortifolioResource;
use App\Models\Municipio;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class EstrategiaController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        //$search = $request->input('search'); // ⬅️ pega o search

        // 1️⃣ Cache do ano base (evita rodar toda hora)
        $anoBase = Cache::remember("ano_base_{$user->titulo_eleitoral}", 3600, function() use ($user) {
            return DB::table('votacao')
                ->where('titulo_eleitoral_candidato', $user->titulo_eleitoral)
                ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
                ->max('ano');
        });

        // Se não encontrou ano, retorna vazio mais rápido
        if (!$anoBase) {
            return Municipio::query()
                ->from('municipios as m')
                ->select([
                    'm.id_municipio',
                    'm.nome',
                    'm.populacao',
                    DB::raw('0 as total_eleitores'),
                    DB::raw('0 as votos'),
                    DB::raw('0 as selecionado')
                ])
                ->filter($request->all())
                ->orderBy('m.nome')
                ->paginate(9);
        }

        // 2️⃣ Subquery otimizada (já com ano fixo)
        $subVotos = DB::table('votacao')
            ->select('id_municipio', DB::raw('SUM(votos) as votos'))
            ->where('titulo_eleitoral_candidato', $user->titulo_eleitoral)
            ->where('ano', $anoBase) // Fixo, sem WHEN
            ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
            ->groupBy('id_municipio');

        // 3️⃣ Query principal
        $query = Municipio::query()
            ->from('municipios as m')
            ->select([
                'm.id_municipio',
                'm.nome',
                'm.populacao',
                DB::raw('IFNULL(e.total_eleitores, 0) as total_eleitores'),
                DB::raw('IFNULL(v.votos, 0) as votos'),
                DB::raw('IF(um.id IS NOT NULL, 1, 0) as selecionado')
            ])
            ->filter($request->all())
            ->leftJoinSub($subVotos, 'v', 'v.id_municipio', '=', 'm.id_municipio')
            ->leftJoin('eleitores as e', function ($join) use ($anoBase) {
                $join->on('e.id_municipio', '=', 'm.id_municipio')
                    ->where('e.ano', '=', $anoBase);
            })
            ->leftJoin('user_municipios as um', function ($join) use ($user) {
                $join->on('um.id_municipio', '=', 'm.id_municipio')
                    ->where('um.user_id', '=', $user->id);
            })
            ->orderBy('m.nome');

        return $query->paginate(9);
    }

    public function portifolio(Request $request)
    {
        $user = $request->user();
        $perPage = min($request->input('per_page', 10), 418);

        $sortBy = $request->input('sort_by', 'nome'); // Padrão: nome alfabético
        $sortOrder = $request->input('sort_order', 'asc');

        // Campos permitidos para ordenação
        $allowedSorts = [
            'nome',
            'populacao',
            'total_eleitores',
            'votos',
            'percentual_votos'
        ];

        // Valida
        if (!in_array($sortBy, $allowedSorts)) {
            $sortBy = 'nome';
        }

        $sortOrder = strtolower($sortOrder) === 'desc' ? 'desc' : 'asc';
    
        // Descobre ano base
        $anoBase = Cache::remember("ano_base_{$user->titulo_eleitoral}", 3600, function() use ($user) {
            return DB::table('votacao')
                ->where('titulo_eleitoral_candidato', $user->titulo_eleitoral)
                ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
                ->max('ano');
        });
        
        if (!$anoBase) {
            return response()->json(['message' => 'Nenhuma eleição encontrada.'], 404);
        }
        
        // Subquery de votos
        $subVotos = DB::table('votacao')
            ->select('id_municipio', DB::raw('SUM(votos) as votos'))
            ->where('titulo_eleitoral_candidato', $user->titulo_eleitoral)
            ->where('ano', $anoBase)
            ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
            ->groupBy('id_municipio');
        
        // Query usando relationship + Query Builder híbrido
        $query = $user->municipiosSelecionados()
            ->leftJoin('eleitores as e', function($join) use ($anoBase) {
                $join->on('e.id_municipio', '=', 'municipios.id_municipio')
                    ->where('e.ano', '=', $anoBase);
            })
            ->leftJoinSub($subVotos, 'v', 'v.id_municipio', '=', 'municipios.id_municipio')
            ->select([
                'municipios.id_municipio',
                'municipios.nome',
                'municipios.populacao',
                DB::raw('IFNULL(e.total_eleitores, 0) as total_eleitores'),
                DB::raw('IFNULL(v.votos, 0) as votos'),
                DB::raw('ROUND((IFNULL(v.votos, 0) / NULLIF(e.total_eleitores, 0)) * 100, 2) as percentual_votos')
            ])
            ->filter($request->all())
            ->orderBy($sortBy, $sortOrder);
        
        return MunicipioPortifolioResource::collection($query->paginate($perPage));
    }

    public function update(Request $request)
    {
        $request->validate([
            'municipios' => 'required|array|min:1',
            'municipios.*' => 'integer|exists:municipios,id_municipio'
        ]);

        $user = $request->user();

        $user->municipios()->sync($request->municipios);

        CacheHelper::invalidarTudo($user->id);

        return response()->json([
            'message' => 'Estratégia atualizada com sucesso.'
        ]);
    }

    public function selecionados(Request $request)
    {
        $user = $request->user();

        // 1️⃣ Cache do ano base
        $anoBase = Cache::remember("ano_base_{$user->titulo_eleitoral}", 3600, function() use ($user) {
            return DB::table('votacao')
                ->where('titulo_eleitoral_candidato', $user->titulo_eleitoral)
                ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
                ->max('ano');
        });

        if (!$anoBase) {
            return response()->json([]);
        }

        // 2️⃣ Subquery de votos
        $subVotos = DB::table('votacao')
            ->select('id_municipio', DB::raw('SUM(votos) as votos'))
            ->where('titulo_eleitoral_candidato', $user->titulo_eleitoral)
            ->where('ano', $anoBase)
            ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
            ->groupBy('id_municipio');

        // 3️⃣ Busca SOMENTE os municípios selecionados (user_municipios)
        $municipiosSelecionados = $user->municipiosSelecionados()
            ->leftJoin('eleitores as e', function($join) use ($anoBase) {
                $join->on('e.id_municipio', '=', 'municipios.id_municipio')
                    ->where('e.ano', '=', $anoBase);
            })
            ->leftJoinSub($subVotos, 'v', 'v.id_municipio', '=', 'municipios.id_municipio')
            ->select([
                'municipios.id_municipio',
                'municipios.populacao',
                DB::raw('IFNULL(e.total_eleitores, 0) as total_eleitores'),
                DB::raw('IFNULL(v.votos, 0) as votos')
            ])
            ->get()
            ->makeHidden('pivot');

        return response()->json($municipiosSelecionados);
    }

    public function autoSelect(Request $request)
    {
        $user = $request->user();

        // 1️⃣ Cache do ano base (evita rodar toda hora)
        $anoBase = Cache::remember("ano_base_{$user->titulo_eleitoral}", 3600, function() use ($user) {
            return DB::table('votacao')
                ->where('titulo_eleitoral_candidato', $user->titulo_eleitoral)
                ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
                ->max('ano');
        });

        if (!$anoBase) {
            return response()->json([
                'message' => 'Nenhuma eleição encontrada.'
            ], 404);
        }

        // Agrupa ANTES do JOIN = mais eficiente
        $subVotos = DB::table('votacao')
            ->select('id_municipio', DB::raw('SUM(votos) as votos'))
            ->where('titulo_eleitoral_candidato', $user->titulo_eleitoral)
            ->where('ano', $anoBase)
            ->whereIn('cargo', ['deputado estadual', 'deputado federal'])
            ->groupBy('id_municipio');

        $municipios = DB::table('eleitores as e')
            ->joinSub($subVotos, 'v', 'v.id_municipio', '=', 'e.id_municipio')
            ->where('e.ano', $anoBase)
            ->where('e.total_eleitores', '>', 0)
            ->whereRaw('(v.votos / e.total_eleitores) >= 0.02')
            ->select([
                'e.id_municipio',
                'v.votos',
                'e.total_eleitores',
                DB::raw('(v.votos * 0.7) + ((v.votos / e.total_eleitores) * e.total_eleitores * 0.3) as score')
            ])
            ->orderByDesc('score')
            ->limit(30)
            ->get();

        return response()->json([
            'message' => 'Seleção automática calculada com sucesso.',
            'ano_base' => $anoBase,
            'quantidade' => $municipios->count(),
            'municipios_selecionados' => $municipios
        ]);
    }
}
