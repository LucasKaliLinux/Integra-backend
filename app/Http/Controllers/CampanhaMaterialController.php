<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCampanhaMaterialRequest;
use App\Http\Requests\UpdateCampanhaMaterialRequest;
use App\Http\Resources\CampanhaMaterialResource;
use App\Models\CampanhaMaterial;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CampanhaMaterialController extends Controller
{
    /**
     * Eager loads padrão para evitar N+1 nas listagens e no detalhe.
     */
    private const RELACOES = [
        'municipio:id_municipio,nome',
        'lideranca:id,nome',
        'tipoMaterial:id,nome,ativo,categoria,unidade,variantes',
        'user:id,name',
        'politicoEstadual:id,nome,nome_urna,numero_eleitoral,partido,cor_principal',
        'politicoFederal:id,nome,nome_urna,numero_eleitoral,partido,cor_principal',
        'politicoSenador:id,nome,nome_urna,numero_eleitoral,partido,cor_principal',
        'politicoGovernador:id,nome,nome_urna,numero_eleitoral,partido,cor_principal',
        'politicoPresidente:id,nome,nome_urna,numero_eleitoral,partido,cor_principal',
    ];

    public function index(Request $request)
    {
        $this->authorize('viewAny', CampanhaMaterial::class);

        // Query params precisam ser escalares/limitados: arrays ou valores
        // fora da faixa causariam erro 500 no scopeFilter/paginate.
        $request->validate([
            'per_page' => 'sometimes|nullable|integer|min:1|max:50',
            'search' => 'sometimes|nullable|string|max:255',
            'status' => 'sometimes|nullable|string|max:255',
            'tipo_material_id' => 'sometimes|nullable|integer',
            'municipio' => 'sometimes|nullable|integer',
        ], [
            'per_page.integer' => 'O parâmetro per_page deve ser um número inteiro.',
            'per_page.min' => 'O parâmetro per_page deve ser no mínimo 1.',
            'per_page.max' => 'O parâmetro per_page deve ser no máximo 50.',
            'search.string' => 'O parâmetro search deve ser um texto.',
            'status.string' => 'O parâmetro status deve ser um texto.',
            'tipo_material_id.integer' => 'O parâmetro tipo_material_id deve ser um número inteiro.',
            'municipio.integer' => 'O parâmetro municipio deve ser um número inteiro.',
        ]);

        $query = CampanhaMaterial::where('deputado_id', $request->user()->deputado_id)
            ->with(self::RELACOES)
            ->filter($request->all())
            ->latest();

        // Contadores por status calculados sobre o conjunto filtrado completo
        // (não apenas a página atual) — usados nos KPIs do frontend.
        $contagens = (clone $query)
            ->reorder()
            ->select('status', DB::raw('COUNT(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        $porStatus = [];
        foreach (CampanhaMaterial::STATUS as $status) {
            $porStatus[$status] = (int) ($contagens[$status] ?? 0);
        }

        $meta = [
            'por_status' => $porStatus,
            'total_filtrado' => array_sum($porStatus),
        ];

        if ($request->boolean('all')) {
            return CampanhaMaterialResource::collection($query->get())
                ->additional(['meta' => $meta]);
        }

        $perPage = (int) ($request->input('per_page') ?: 12);

        return CampanhaMaterialResource::collection($query->paginate($perPage)->withQueryString())
            ->additional(['meta' => $meta]);
    }

    public function store(StoreCampanhaMaterialRequest $request)
    {
        $this->authorize('create', CampanhaMaterial::class);

        $material = new CampanhaMaterial($request->validated());
        $material->deputado_id = $request->user()->deputado_id;
        $material->user_id = $request->user()->id;
        $material->save();

        $material->load(self::RELACOES);

        return $this->respostaUnica($material, 201);
    }

    public function show(Request $request, string $id)
    {
        $material = CampanhaMaterial::where('deputado_id', $request->user()->deputado_id)
            ->with(self::RELACOES)
            ->findOrFail($id);

        $this->authorize('view', $material);

        return $this->respostaUnica($material);
    }

    public function update(UpdateCampanhaMaterialRequest $request, string $id)
    {
        $material = CampanhaMaterial::where('deputado_id', $request->user()->deputado_id)
            ->findOrFail($id);

        $this->authorize('update', $material);

        $material->update($request->validated());

        $material->refresh()->load(self::RELACOES);

        return $this->respostaUnica($material);
    }

    public function destroy(Request $request, string $id)
    {
        $material = CampanhaMaterial::where('deputado_id', $request->user()->deputado_id)
            ->findOrFail($id);

        $this->authorize('delete', $material);

        $material->delete();

        return response()->json(['message' => 'Material removido com sucesso.']);
    }

    /**
     * Resposta de objeto único SEMPRE envelopada em "data".
     *
     * Necessário porque o payload do material contém um campo chamado "data"
     * (data de entrega) e, nesse caso, o Laravel suprime o wrapper padrão do
     * JsonResource — o que deixaria show/store/update com shape diferente do
     * restante da API.
     */
    private function respostaUnica(CampanhaMaterial $material, int $status = 200)
    {
        return response()->json([
            'data' => (new CampanhaMaterialResource($material))->resolve(),
        ], $status);
    }
}
