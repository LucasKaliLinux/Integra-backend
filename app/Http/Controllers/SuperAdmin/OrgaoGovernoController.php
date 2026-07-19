<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\OrgaoGoverno;
use Illuminate\Http\Request;

class OrgaoGovernoController extends Controller
{
    /**
     * Lista órgãos com filtros
     *
     * Query params:
     * - search: Busca por nome ou sigla
     * - esfera_id: Filtra por esfera (Federal/Estadual)
     * - tipo_orgao_id: Filtra por tipo de órgão
     */
    public function index(Request $request)
    {
        $query = OrgaoGoverno::query()
            ->with([
                'tipoOrgao:id,nome,esfera_governo_id',
                'tipoOrgao.esferaGoverno:id,nome',
            ])
            ->select('id', 'tipo_orgao_id', 'nome', 'sigla')
            ->filter($request->all())
            ->orderBy('nome');

        $isSuperAdmin = $request->user()->hasRole('super_admin');

        if ($isSuperAdmin) {
            $query->withCount('acoes');
        }

        $transform = function ($orgao) use ($isSuperAdmin) {
            $data = [
                'id' => $orgao->id,
                'nome' => $orgao->nome,
                'sigla' => $orgao->sigla,
                'tipo_orgao' => $orgao->tipoOrgao->nome ?? '-',
                'esfera' => $orgao->tipoOrgao->esferaGoverno->nome ?? '-',
            ];

            if ($isSuperAdmin) {
                $data['em_uso'] = $orgao->acoes_count > 0;
            }

            return $data;
        };

        // Paginação ativada apenas quando `per_page` é informado, para não
        // quebrar os dropdowns que consomem a lista completa.
        if ($request->filled('per_page')) {
            $orgaos = $query->paginate((int) $request->per_page);
            $orgaos->getCollection()->transform($transform);

            return response()->json($orgaos);
        }

        $orgaos = $query->get()->map($transform);

        return response()->json($orgaos);
    }

    /**
     * Exibe um órgão específico
     */
    public function show(string $id)
    {
        $orgao = OrgaoGoverno::with([
            'tipoOrgao:id,nome,esfera_governo_id',
            'tipoOrgao.esferaGoverno:id,nome',
        ])
            ->findOrFail($id);

        return response()->json([
            'id' => $orgao->id,
            'nome' => $orgao->nome,
            'sigla' => $orgao->sigla,
            'tipo_orgao' => [
                'id' => $orgao->tipoOrgao->id,
                'nome' => $orgao->tipoOrgao->nome,
            ],
            'esfera' => [
                'id' => $orgao->tipoOrgao->esferaGoverno->id,
                'nome' => $orgao->tipoOrgao->esferaGoverno->nome,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'nome' => 'required|string|max:255',
            'sigla' => 'required|string|max:20',
            'tipo_orgao_id' => 'required|exists:tipos_orgao,id',
        ], [
            'nome.required' => 'O nome do órgão é obrigatório.',
            'sigla.required' => 'A sigla é obrigatória.',
            'tipo_orgao_id.required' => 'O tipo de órgão é obrigatório.',
            'tipo_orgao_id.exists' => 'Tipo de órgão inválido.',
        ]);

        $orgao = OrgaoGoverno::create([
            'nome' => $validated['nome'],
            'sigla' => strtoupper($validated['sigla']),
            'tipo_orgao_id' => $validated['tipo_orgao_id'],
        ]);

        return response()->json([
            'message' => 'Órgão criado com sucesso',
            'orgao' => $orgao->load('tipoOrgao.esferaGoverno'),
        ], 201);
    }

    public function update(Request $request, int $id)
    {
        $orgao = OrgaoGoverno::findOrFail($id);

        $validated = $request->validate([
            'nome' => 'sometimes|string|max:255',
            'sigla' => 'sometimes|string|max:20',
            'tipo_orgao_id' => 'sometimes|exists:tipos_orgao,id',
        ]);

        if (isset($validated['sigla'])) {
            $validated['sigla'] = strtoupper($validated['sigla']);
        }

        $orgao->update($validated);

        return response()->json([
            'message' => 'Órgão atualizado com sucesso',
            'orgao' => $orgao->load('tipoOrgao.esferaGoverno'),
        ]);
    }

    public function destroy(int $id)
    {
        $orgao = OrgaoGoverno::findOrFail($id);

        // ⬇️ Verificação OTIMIZADA
        if ($orgao->acoes()->exists()) {
            return response()->json([
                'message' => 'Não é possível deletar este órgão pois ele está sendo utilizado em ações.',
            ], 422);
        }

        $orgao->delete();

        return response()->json(['message' => 'Órgão deletado com sucesso']);
    }
}
