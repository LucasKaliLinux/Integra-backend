<?php

namespace App\Http\Controllers;

use App\Helpers\CacheHelper;
use App\Helpers\InstrumentoHelper;
use App\Http\Resources\AcaoResource;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAcaoRequest;
use App\Http\Requests\UpdateAcaoRequest;
use App\Models\Acao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AcaoController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->baseAcaoQuery($request)
            ->select([
                'id',
                'titulo',
                'id_municipio',
                'orgao_governo_id',
                'categoria_investimento_id',
                'status_id',
                'valor',
                'ano',
                'observacao',
                'instrumento_path'
            ])
            ->with($this->listRelations())
            ->filter($request->all())
            ->sort($request->input('sort_by'), $request->input('sort_order'));

        $perPage = min($request->input('per_page', 10), 20);

        $paginated = $query->paginate($perPage)->withQueryString();

        $paginated->getCollection()->transform(fn (Acao $acao) => $this->transformListItem($acao));

        return response()->json($paginated);
    }

    public function store(StoreAcaoRequest $request)
    {
        $this->authorize('create', Acao::class);

        $data = $request->validated();
        unset($data['observacao_mudanca']);

        $acao = DB::transaction(function () use ($request, $data) {
            $acao = Acao::create([
                ...$data,
                'deputado_id' => $request->user()->deputado_id,
                'user_id' => $request->user()->id,
            ]);

            // Sync lideranças
            if ($request->has('liderancas')) {
                $acao->liderancas()->sync($request->liderancas);
            }

            CacheHelper::invalidarMunicipio($acao->id_municipio, $request->user()->id);

            return $acao;
        });

        // Recarregar após transação
        $acao->load($this->detailRelations());

        return new AcaoResource($acao);
    }

    public function show(Request $request, string $id)
    {
        $acao = $this->fetchAcao($request, $id, $this->detailRelations());
        $this->authorize('view', $acao);

        return new AcaoResource($acao);
    }

    public function update(UpdateAcaoRequest $request, string $id)
    {
        $acao = $this->fetchAcao($request, $id);
        $this->authorize('update', $acao);

        $data = $request->validated();
        unset($data['observacao_mudanca']);

        DB::transaction(function () use ($acao, $data, $request) {
            $acao->update($data);

            // Sync lideranças
            if ($request->has('liderancas')) {
                $acao->liderancas()->sync($request->liderancas);
            }

            CacheHelper::invalidarMunicipio($acao->id_municipio, $request->user()->id);
        });

        // Recarregar após transação
        $acao->load($this->detailRelations());

        return new AcaoResource($acao);
    }

    public function destroy(Request $request, string $id)
    {
        $acao = $this->fetchAcao($request, $id);
        $this->authorize('delete', $acao);

        $idMunicipio = $acao->id_municipio;
        $acao->delete();

        CacheHelper::invalidarMunicipio($idMunicipio, $request->user()->id);

        return response()->json(['message' => 'Ação removida com sucesso.']);
    }

    public function uploadInstrumento(Request $request, string $id)
    {
        $this->validateInstrumento($request);

        $acao = $this->baseAcaoQuery($request)
            ->lockForUpdate()
            ->findOrFail($id);
        $this->authorize('update', $acao);

        $extension = $request->file('instrumento')->extension();
        $safeFilename = Str::uuid() . '_' . time() . '.' . $extension;

        DB::beginTransaction();

        try {
            $path = $this->storeInstrumentoFile($request->file('instrumento'), $safeFilename);
            $this->removeInstrumentoFile($acao->instrumento_path);

            $acao->update(['instrumento_path' => $path]);
            DB::commit();

            return response()->json([
                'message' => 'Instrumento enviado com sucesso.',
                'instrumento' => InstrumentoHelper::buildFromPath($acao->fresh()->instrumento_path, $acao->fresh()->updated_at)
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            $this->removeInstrumentoFile($path ?? null);

            return response()->json([
                'message' => 'Erro ao enviar instrumento.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function deleteInstrumento(Request $request, string $id)
    {
        $acao = $this->baseAcaoQuery($request)
            ->lockForUpdate()
            ->findOrFail($id);
        $this->authorize('update', $acao);

        if (!$acao->instrumento_path) {
            return response()->json(['message' => 'Nenhum instrumento anexado.'], 404);
        }

        DB::beginTransaction();

        try {
            $this->removeInstrumentoFile($acao->instrumento_path);
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
        $acao = $this->fetchAcao($request, $id);
        $this->authorize('view', $acao);

        if (!$acao->instrumento_path) {
            return response()->json(['message' => 'Nenhum instrumento anexado.'], 404);
        }

        $fullPath = Storage::disk('public')->path($acao->instrumento_path);
        $basePath = Storage::disk('public')->path('instrumentos');

        if (!Storage::disk('public')->exists($acao->instrumento_path)) {
            $acao->update(['instrumento_path' => null]);
            return response()->json(['message' => 'Arquivo não encontrado.'], 404);
        }

        if (strpos(realpath($fullPath), realpath($basePath)) !== 0) {
            abort(403, 'Acesso negado.');
        }

        $extension = pathinfo($acao->instrumento_path, PATHINFO_EXTENSION);
        $safeFilename = 'instrumento_acao_' . $acao->id . '_' . date('Ymd') . '.' . $extension;
        $mime = Storage::disk('public')->mimeType($acao->instrumento_path);

        return response()->download($fullPath, $safeFilename, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'attachment; filename="' . $safeFilename . '"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function baseAcaoQuery(Request $request)
    {
        return Acao::where('deputado_id', $request->user()->deputado_id);
    }

    private function listRelations(): array
    {
        return [
            'municipio:id_municipio,nome',
            'orgao.tipoOrgao.esferaGoverno',
            'orgao:id,tipo_orgao_id,nome,sigla',
            'categoriaInvestimento:id,nome',
            'status:id,nome,slug'
        ];
    }

    private function detailRelations(): array
    {
        return [
            'municipio:id_municipio,nome',
            'orgao.tipoOrgao.esferaGoverno',
            'orgao:id,tipo_orgao_id,nome,sigla',
            'categoriaInvestimento:id,nome',
            'tipoAcao:id,nome',
            'status:id,nome',
            'liderancas:id,nome'
        ];
    }

    private function transformListItem(Acao $acao): array
    {
        return [
            'id' => $acao->id,
            'governo' => $acao->orgao->tipoOrgao->esferaGoverno->nome ?? '-',
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
    }

    private function fetchAcao(Request $request, string $id, array $relations = []): Acao
    {
        return $this->baseAcaoQuery($request)
            ->with($relations)
            ->findOrFail($id);
    }


    private function validateInstrumento(Request $request): void
    {
        $request->validate([
            'instrumento' => [
                'required',
                'file',
                'mimes:pdf,doc,docx,jpg,jpeg,png',
                'max:20480',
                function ($attribute, $value, $fail) {
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
    }

    private function storeInstrumentoFile($file, string $filename): string
    {
        return $file->storeAs('instrumentos', $filename, 'public');
    }

    private function removeInstrumentoFile(?string $path): void
    {
        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
