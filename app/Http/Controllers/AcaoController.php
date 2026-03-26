<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAcaoRequest;
use App\Models\Acao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AcaoController extends Controller
{
    /**
     * Lista todas as ações do usuário
     */
    public function index(Request $request)
    {
        $query = $request->user()
            ->acoes()
            ->with([
                'municipio:id_municipio,nome',
                'orgao.tipoOrgao.esferaGoverno', // ⬅️ Pega governo (federal/estadual)
                'orgao:id,tipo_orgao_id,nome,sigla',
                'categoriaInvestimento:id,nome',
                'status:id,nome,slug'
            ])
            ->filter($request->all())
            ->sort($request->input('sort_by'), $request->input('sort_order'));

        $perPage = min($request->input('per_page', 10), 20);

        $paginated = $query->paginate($perPage)->withQueryString();

        $paginated->getCollection()->transform(function ($acao) {
            return [
                'id' => $acao->id,
                'governo' => $acao->orgao->tipoOrgao->esferaGoverno->nome ?? '-', // ⬅️ Federal/Estadual
                'titulo' => $acao->titulo,
                'orgao' => $acao->orgao->sigla ?? $acao->orgao->nome ?? '-',
                'categoria' => $acao->categoriaInvestimento->nome ?? '-',
                'municipio' => $acao->municipio->nome ?? '-',
                'valor' => (float) $acao->valor,
                'ano' => $acao->ano,
                'status' => $acao->status->nome ?? '-',
                'observacao' => $acao->observacao,
                'instrumento_path' => $acao->instrumento_path ? md5($acao->instrumento_path) : null,
            ];
        });

        return response()->json($paginated);
    }

    /**
     * Cria uma nova ação
     */
    public function store(StoreAcaoRequest $request)
    {
        $validated = $request->validated();
        
        // Remove observacao_mudanca (não vai pro banco)
        unset($validated['observacao_mudanca']);

        $acao = $request->user()->acoes()->create($validated);

        CacheHelper::invalidarMunicipio($acao->id_municipio, $request->user()->id);

        // Retorna com relationships
        $acao->load([
            'municipio:id_municipio,nome',
            'orgao:id,nome,sigla',
            'categoriaInvestimento:id,nome',
            'tipoAcao:id,nome',
            'status:id,nome',
            'liderancaSolicitante:id,nome'
        ]);

        return response()->json($acao, 201);
    }

    /**
     * Exibe detalhes de uma ação específica
     */
    public function show(Request $request, string $id)
    {
        $acao = $request->user()
            ->acoes()
            ->with([
                'municipio:id_municipio,nome',
                'orgao.tipoOrgao.esferaGoverno',
                'orgao:id,tipo_orgao_id,nome,sigla',
                'categoriaInvestimento:id,nome',
                'tipoAcao:id,nome',
                'status:id,nome,slug',
                'liderancaSolicitante:id,nome',
                // 'historico.status:id,nome',
                // 'historico.user:id,name'
            ])
            ->findOrFail($id);

        return response()->json([
            'id' => $acao->id,
            'governo' => $acao->orgao->tipoOrgao->esferaGoverno->nome ?? '-',
            'titulo' => $acao->titulo,
            'numero_sei' => $acao->numero_sei,
            'orgao' => [
                'id' => $acao->orgao->id,
                'nome' => $acao->orgao->nome,
                'sigla' => $acao->orgao->sigla
            ],
            'categoria' => [
                'id' => $acao->categoriaInvestimento->id,
                'nome' => $acao->categoriaInvestimento->nome
            ],
            'tipo' => [
                'id' => $acao->tipoAcao->id,
                'nome' => $acao->tipoAcao->nome
            ],
            'municipio' => [
                'id' => $acao->municipio->id_municipio,
                'nome' => $acao->municipio->nome
            ],
            'lideranca_solicitante' => $acao->liderancaSolicitante ? [
                'id' => $acao->liderancaSolicitante->id,
                'nome' => $acao->liderancaSolicitante->nome
            ] : null,
            'valor' => (float) $acao->valor,
            'ano' => $acao->ano,
            'status' => [
                'id' => $acao->status->id,
                'nome' => $acao->status->nome
            ],
            'observacao' => $acao->observacao,
            'instrumento_path' => $acao->instrumento_path ? md5($acao->instrumento_path) : null,
            // 'historico' => $acao->historico->map(fn($h) => [
            //     'status' => $h->status->nome,
            //     'usuario' => $h->user->name,
            //     'observacao' => $h->observacao,
            //     'data' => $h->created_at->format('d/m/Y H:i')
            // ]),
            // 'created_at' => $acao->created_at->format('d/m/Y H:i'),
            // 'updated_at' => $acao->updated_at->format('d/m/Y H:i')
        ]);
    }

    /**
     * Atualiza uma ação
     */
    public function update(StoreAcaoRequest $request, string $id)
    {
        $acao = $request->user()->acoes()->findOrFail($id);

        $validated = $request->validated();
        
        // Remove observacao_mudanca (não vai pro banco)
        unset($validated['observacao_mudanca']);

        $acao->update($validated);

        CacheHelper::invalidarMunicipio($acao->id_municipio, $request->user()->id);

        // Retorna com relationships
        $acao->load([
            'municipio:id_municipio,nome',
            'orgao:id,nome,sigla',
            'categoriaInvestimento:id,nome',
            'tipoAcao:id,nome',
            'status:id,nome',
            'liderancaSolicitante:id,nome'
        ]);

        return response()->json($acao);
    }

    /**
     * Remove uma ação
     */
    public function destroy(Request $request, string $id)
    {
        $acao = $request->user()->acoes()->findOrFail($id);

        $idMunicipio = $acao->id_municipio;

        $acao->delete();

        CacheHelper::invalidarMunicipio($idMunicipio, $request->user()->id);

        return response()->json(['message' => 'Ação removida com sucesso.']);
    }

    /**
     * Upload de instrumento
     */
    public function uploadInstrumento(Request $request, string $id)
    {
        $request->validate([
            'instrumento' => 'required|file|mimes:pdf,doc,docx|max:20480' // 10MB
        ], [
            'instrumento.required' => 'Você precisa enviar um arquivo.',
            'instrumento.file' => 'O arquivo enviado é inválido.',
            'instrumento.mimes' => 'O arquivo deve ser PDF, DOC ou DOCX.',
            'instrumento.max' => 'O arquivo deve ter no máximo 20MB.',
        ]);

        $acao = $request->user()->acoes()->findOrFail($id);

        $filename = Str::uuid().'.'.$request->file('instrumento')->extension();
        
        // Salva novo arquivo
        $path = $request->file('instrumento')
            ->storeAs('instrumentos', $filename, 'public');

        // Deleta arquivo antigo se existir
        if ($acao->instrumento_path) {
            \Storage::disk('public')->delete($acao->instrumento_path);
        }

        $acao->update(['instrumento_path' => $path]);

        return response()->json([
            'message' => 'Instrumento enviado com sucesso.',
            'path' => $path,
            'url' => \Storage::url($path)
        ]);
    }

    /**
     * Remove instrumento
     */
    public function deleteInstrumento(Request $request, string $id)
    {
        $acao = $request->user()->acoes()->findOrFail($id);

        if ($acao->instrumento_path) {
            \Storage::disk('public')->delete($acao->instrumento_path);
            $acao->update(['instrumento_path' => null]);
        }

        return response()->json(['message' => 'Instrumento removido com sucesso.']);
    }

    public function downloadInstrumento(Request $request, string $id)
    {
        $acao = $request->user()->acoes()->findOrFail($id);

        if (!$acao->instrumento_path) {
            return response()->json(['message' => 'Nenhum instrumento anexado.'], 404);
        }

        if (!\Storage::disk('public')->exists($acao->instrumento_path)) {
            return response()->json(['message' => 'Arquivo não encontrado.'], 404);
        }

        $fullPath  = \Storage::disk('public')->path($acao->instrumento_path);
        $extension = pathinfo($acao->instrumento_path, PATHINFO_EXTENSION); // pdf, doc, docx
        $filename  = 'instrumento-' . $acao->id . '.' . $extension;

        $mime = \Storage::disk('public')->mimeType($acao->instrumento_path);

        return response()->download($fullPath, $filename, [
            'Content-Type' => $mime,
        ]);
    }
}