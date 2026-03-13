<?php

namespace App\Http\Controllers;

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
        $orgaos = OrgaoGoverno::query()
            ->with([
                'tipoOrgao:id,nome,esfera_governo_id',
                'tipoOrgao.esferaGoverno:id,nome'
            ])
            ->select('id', 'tipo_orgao_id', 'nome', 'sigla')
            ->filter($request->all())
            ->orderBy('nome')
            ->get()
            ->map(function($orgao) {
                return [
                    'id' => $orgao->id,
                    'nome' => $orgao->nome,
                    'sigla' => $orgao->sigla,
                    'tipo_orgao' => $orgao->tipoOrgao->nome ?? '-',
                    'esfera' => $orgao->tipoOrgao->esferaGoverno->nome ?? '-'
                ];
            });

        return response()->json($orgaos);
    }

    /**
     * Exibe um órgão específico
     */
    public function show(string $id)
    {
        $orgao = OrgaoGoverno::with([
                'tipoOrgao:id,nome,esfera_governo_id',
                'tipoOrgao.esferaGoverno:id,nome'
            ])
            ->findOrFail($id);

        return response()->json([
            'id' => $orgao->id,
            'nome' => $orgao->nome,
            'sigla' => $orgao->sigla,
            'tipo_orgao' => [
                'id' => $orgao->tipoOrgao->id,
                'nome' => $orgao->tipoOrgao->nome
            ],
            'esfera' => [
                'id' => $orgao->tipoOrgao->esferaGoverno->id,
                'nome' => $orgao->tipoOrgao->esferaGoverno->nome
            ]
        ]);
    }
}