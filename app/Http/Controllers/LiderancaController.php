<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Http\Requests\StoreLiderancaRequest;
use App\Http\Requests\UpdateLiderancaRequest;
use App\Http\Resources\LiderancaResource;
use App\Models\CargoLideranca;
use App\Models\Lideranca;
use Illuminate\Http\Request;

class LiderancaController extends Controller
{
    public function index(Request $request)
    {
        $query = Lideranca::where('deputado_id', $request->user()->deputado_id)
            ->with([
                'municipio:id_municipio,nome',
                'classificacao:id,nome,slug',
                'funcao:id,nome,slug',
            ])
            ->filter($request->all())
            ->latest();

        if ($request->boolean('all')) {
            return LiderancaResource::collection($query->get());
        }

        $perPage = min($request->input('per_page', 12), 16);

        return LiderancaResource::collection($query->paginate($perPage)->withQueryString());
    }

    public function store(StoreLiderancaRequest $request)
    {
        $this->authorize('create', Lideranca::class);

        if (! Lideranca::cargoPertenceAClassificacao(
            $request->funcao_id,
            $request->classificacao_id
        )) {
            return response()->json([
                'error' => 'Função não pertence à classificação informada.',
            ], 422);
        }

        $lideranca = new Lideranca($request->validated());
        $lideranca->deputado_id = $request->user()->deputado_id;
        $lideranca->user_id = $request->user()->id;
        $lideranca->save();

        CacheHelper::invalidarMunicipio($lideranca->id_municipio, $request->user()->id);

        return new LiderancaResource($lideranca);
    }

    public function show(Request $request, string $id)
    {
        $lideranca = Lideranca::where('deputado_id', $request->user()->deputado_id)->with([
            'municipio',
            'classificacao',
            'funcao',
        ])->findOrFail($id);

        $this->authorize('view', $lideranca);

        return new LiderancaResource($lideranca);
    }

    public function update(UpdateLiderancaRequest $request, $id)
    {
        $lideranca = Lideranca::where('deputado_id', $request->user()->deputado_id)
            ->with('funcao')
            ->findOrFail($id);

        $this->authorize('update', $lideranca);

        $isPrefeitoOuVereador = in_array($lideranca->funcao?->slug, ['prefeito', 'vereador']);

        if ($isPrefeitoOuVereador) {
            // ⬇️ ATUALIZADO: Incluído 'instagram'
            $lideranca->update($request->only(['telefone', 'instagram', 'alinhamento', 'observacao']));
        } else {
            // Bloqueia tentativa de virar prefeito/vereador
            $novoCargo = CargoLideranca::find($request->funcao_id);
            if ($novoCargo && in_array($novoCargo->slug, ['prefeito', 'vereador'])) {
                return response()->json([
                    'error' => 'Não é permitido atribuir cargo de prefeito ou vereador manualmente.',
                ], 422);
            }

            if (! Lideranca::cargoPertenceAClassificacao($request->funcao_id, $request->classificacao_id)) {
                return response()->json([
                    'error' => 'Função não pertence à classificação informada.',
                ], 422);
            }

            $lideranca->update($request->validated());
        }

        CacheHelper::invalidarMunicipio($lideranca->id_municipio, $request->user()->id);

        return new LiderancaResource($lideranca->fresh());
    }

    public function destroy(Request $request, $id)
    {
        $lideranca = Lideranca::where('deputado_id', $request->user()->deputado_id)->findOrFail($id);
        $this->authorize('delete', $lideranca);

        $lideranca->delete();

        CacheHelper::invalidarMunicipio($lideranca->id_municipio, $request->user()->id);

        return response()->json(['message' => 'Deletado com sucesso']);
    }
}
