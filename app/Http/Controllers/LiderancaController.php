<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLiderancaRequest;
use App\Http\Requests\UpdateLiderancaPoliticaRequest;
use App\Http\Requests\UpdateLiderancaRequest;
use App\Http\Resources\LiderancaResource;
use App\Models\CargoLideranca;
use App\Models\Lideranca;
use Illuminate\Http\Request;

class LiderancaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = $request->user()
            ->liderancas()
            ->with([
                'municipio:id_municipio,nome',
                'classificacao:id,nome,slug',
                'funcao:id,nome,slug'
            ])
            ->filter($request->all())
            ->latest();

        if ($request->boolean('all')) {
            return LiderancaResource::collection($query->get());
        }

        $perPage = min($request->input('per_page', 12), 16);

        return LiderancaResource::collection($query->paginate($perPage)->withQueryString());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreLiderancaRequest $request)
    {
        if (!Lideranca::cargoPertenceAClassificacao(
            $request->funcao_id,
            $request->classificacao_id
        )) {
            return response()->json([
                'error' => 'Função não pertence à classificação informada.'
            ], 422);
        }

        $lideranca = $request->user()->liderancas()->create($request->validated());
        CacheHelper::invalidarMunicipio($lideranca->id_municipio, $request->user()->id);

        return new LiderancaResource($lideranca);
    }


    /**
     * Display the specified resource.
     */
    public function show(Request $request ,string $id)
    {
        $lideranca = $request->user()->liderancas()->with([
            'municipio',
            'classificacao',
            'funcao'
        ])->findOrFail($id);

        return new LiderancaResource($lideranca);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateLiderancaRequest $request, $id)
    {
        $lideranca = $request->user()
            ->liderancas()
            ->with('funcao')
            ->findOrFail($id);

        $isPrefeitoOuVereador = in_array($lideranca->funcao?->slug, ['prefeito', 'vereador']);

        if ($isPrefeitoOuVereador) {
            $lideranca->update($request->only(['telefone', 'alinhamento', 'observacao']));
        } else {
            // Bloqueia tentativa de virar prefeito/vereador
            $novoCargo = CargoLideranca::find($request->funcao_id);
            if ($novoCargo && in_array($novoCargo->slug, ['prefeito', 'vereador'])) {
                return response()->json([
                    'error' => 'Não é permitido atribuir cargo de prefeito ou vereador manualmente.'
                ], 422);
            }

            if (!Lideranca::cargoPertenceAClassificacao($request->funcao_id, $request->classificacao_id)) {
                return response()->json([
                    'error' => 'Função não pertence à classificação informada.'
                ], 422);
            }

            $lideranca->update($request->validated());
        }

        CacheHelper::invalidarMunicipio($lideranca->id_municipio, $request->user()->id);

        return new LiderancaResource($lideranca->fresh());
    }


    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, $id)
    {
        $lideranca = $request->user()
            ->liderancas()
            ->findOrFail($id);

        $lideranca->delete();

        CacheHelper::invalidarMunicipio($lideranca->id_municipio, $request->user()->id);

        return response()->json(['message' => 'Deletado com sucesso']);
    }
}
