<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAcaoRequest;
use App\Models\Acao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
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
                'orgao.tipoOrgao.esferaGoverno', 
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
            ])
            ->findOrFail($id);

        // ⬇️ NOVO: Processa informações do instrumento
        $instrumentoInfo = null;
        if ($acao->instrumento_path && Storage::disk('public')->exists($acao->instrumento_path)) {
            $fullPath = Storage::disk('public')->path($acao->instrumento_path);
            $extension = strtoupper(pathinfo($acao->instrumento_path, PATHINFO_EXTENSION));
            $sizeInBytes = filesize($fullPath);
            
            // Formata tamanho em MB/KB
            if ($sizeInBytes >= 1048576) { // >= 1MB
                $sizeFormatted = round($sizeInBytes / 1048576, 2) . ' MB';
            } else {
                $sizeFormatted = round($sizeInBytes / 1024, 2) . ' KB';
            }

            $instrumentoInfo = [
                'exists' => true,
                'filename' => basename($acao->instrumento_path),
                'extension' => $extension,
                'size_bytes' => $sizeInBytes,
                'size_formatted' => $sizeFormatted,
                'mime_type' => Storage::disk('public')->mimeType($acao->instrumento_path),
                'uploaded_at' => $acao->updated_at->format('d/m/Y H:i'), // Aproximação
            ];
        } else {
            $instrumentoInfo = [
                'exists' => false,
                'filename' => null,
                'extension' => null,
                'size_bytes' => null,
                'size_formatted' => null,
                'mime_type' => null,
                'uploaded_at' => null,
            ];
        }

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
            'instrumento' => $instrumentoInfo, // ⬅️ NOVO: Objeto completo
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
        // ⬇️ Validação completa e segura
        $request->validate([
            'instrumento' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png', // Tipos permitidos
                'max:20480', // 20MB
                function ($attribute, $value, $fail) {
                    // ⬇️ SEGURANÇA: Valida MIME type real (não só extensão)
                    $allowedMimes = [
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'image/jpeg',
                        'image/png',
                    ];

                    if (!in_array($value->getMimeType(), $allowedMimes)) {
                        $fail('O tipo de arquivo não é permitido.');
                    }

                    // ⬇️ SEGURANÇA: Valida conteúdo do arquivo (anti-spoofing)
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $realMime = finfo_file($finfo, $value->getRealPath());
                    finfo_close($finfo);

                    if (!in_array($realMime, $allowedMimes)) {
                        $fail('O arquivo possui conteúdo inválido.');
                    }
                }
            ]
        ], [
            'instrumento.required' => 'Você precisa enviar um arquivo.',
            'instrumento.file' => 'O arquivo enviado é inválido.',
            'instrumento.mimes' => 'O arquivo deve ser PDF, DOC, DOCX, JPG ou PNG.',
            'instrumento.max' => 'O arquivo deve ter no máximo 20MB.',
        ]);

        // ⬇️ RACE CONDITION: Usa lock otimista
        $acao = $request->user()->acoes()->lockForUpdate()->findOrFail($id);

        // ⬇️ SEGURANÇA: Nome de arquivo único e sanitizado
        $extension = $request->file('instrumento')->extension();
        $safeFilename = Str::uuid() . '_' . time() . '.' . $extension;
        
        // ⬇️ Usa transaction para garantir atomicidade
        DB::beginTransaction();
        try {
            // Salva novo arquivo
            $path = $request->file('instrumento')
                ->storeAs('instrumentos', $safeFilename, 'public');

            // Deleta arquivo antigo se existir
            if ($acao->instrumento_path && Storage::disk('public')->exists($acao->instrumento_path)) {
                Storage::disk('public')->delete($acao->instrumento_path);
            }

            // Atualiza banco
            $acao->update(['instrumento_path' => $path]);

            DB::commit();

            // ⬇️ Retorna informações completas do arquivo
            $fullPath = Storage::disk('public')->path($path);
            $sizeInBytes = filesize($fullPath);
            
            if ($sizeInBytes >= 1048576) {
                $sizeFormatted = round($sizeInBytes / 1048576, 2) . ' MB';
            } else {
                $sizeFormatted = round($sizeInBytes / 1024, 2) . ' KB';
            }

            return response()->json([
                'message' => 'Instrumento enviado com sucesso.',
                'instrumento' => [
                    'exists' => true,
                    'filename' => $safeFilename,
                    'extension' => strtoupper($extension),
                    'size_bytes' => $sizeInBytes,
                    'size_formatted' => $sizeFormatted,
                    'mime_type' => Storage::disk('public')->mimeType($path),
                    'uploaded_at' => now()->format('d/m/Y H:i'),
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();

            // Remove arquivo se deu erro no banco
            if (isset($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }

            return response()->json([
                'message' => 'Erro ao enviar instrumento.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove instrumento
     */
    public function deleteInstrumento(Request $request, string $id)
    {
        // ⬇️ RACE CONDITION: Lock otimista
        $acao = $request->user()->acoes()->lockForUpdate()->findOrFail($id);

        if (!$acao->instrumento_path) {
            return response()->json(['message' => 'Nenhum instrumento anexado.'], 404);
        }

        DB::beginTransaction();
        try {
            // Deleta arquivo físico
            if (Storage::disk('public')->exists($acao->instrumento_path)) {
                Storage::disk('public')->delete($acao->instrumento_path);
            }

            // Atualiza banco
            $acao->update(['instrumento_path' => null]);

            DB::commit();

            return response()->json(['message' => 'Instrumento removido com sucesso.']);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Erro ao remover instrumento.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function downloadInstrumento(Request $request, string $id)
    {
        $acao = $request->user()->acoes()->findOrFail($id);

        if (!$acao->instrumento_path) {
            return response()->json(['message' => 'Nenhum instrumento anexado.'], 404);
        }

        // ⬇️ SEGURANÇA: Valida que arquivo ainda existe
        if (!Storage::disk('public')->exists($acao->instrumento_path)) {
            // Limpa referência órfã do banco
            $acao->update(['instrumento_path' => null]);
            return response()->json(['message' => 'Arquivo não encontrado.'], 404);
        }

        // ⬇️ SEGURANÇA: Valida que caminho não foi manipulado (path traversal)
        $fullPath = Storage::disk('public')->path($acao->instrumento_path);
        $basePath = Storage::disk('public')->path('instrumentos');
        
        if (strpos(realpath($fullPath), realpath($basePath)) !== 0) {
            abort(403, 'Acesso negado.');
        }

        // ⬇️ Nome de arquivo seguro para download
        $extension = pathinfo($acao->instrumento_path, PATHINFO_EXTENSION);
        $safeFilename = 'instrumento_acao_' . $acao->id . '_' . date('Ymd') . '.' . $extension;

        // ⬇️ MIME type correto
        $mime = Storage::disk('public')->mimeType($acao->instrumento_path);

        return response()->download($fullPath, $safeFilename, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $safeFilename . '"',
            'X-Content-Type-Options' => 'nosniff', // Previne MIME sniffing
        ]);
    }
}