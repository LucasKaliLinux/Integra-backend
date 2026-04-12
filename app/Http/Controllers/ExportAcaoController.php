<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Export;
use App\Models\Acao;
use App\Jobs\GeneratePdfExport;
use Illuminate\Http\Request;

class ExportAcaoController extends Controller
{
    /**
     * Inicia exportação de PDF
     */
    public function store(Request $request)
    {
        $this->authorize('create', Export::class);

        $user = $request->user();
        $filtros = $request->all();

        // ⬇️ VALIDAÇÃO: Deve ter pelo menos 1 filtro válido
        $filtrosValidos = ['municipio', 'status', 'ano', 'ano_min', 'ano_max', 'orgao', 'categoria', 'esfera', 'tipo_orgao', 'search', 'tipo_acao'];
        
        $temFiltro = collect($filtrosValidos)->some(fn($key) => !empty($filtros[$key]));

        if (!$temFiltro) {
            return response()->json([
                'error' => 'É necessário aplicar pelo menos um filtro para gerar o PDF.'
            ], 422);
        }

        // ⬇️ VALIDAÇÃO: Conta quantas ações seriam exportadas
        $query = Acao::where('deputado_id', $user->deputado_id)->filter($filtros);
        
        $totalAcoes = $query->count();

        if ($totalAcoes === 0) {
            return response()->json([
                'error' => 'Nenhuma ação encontrada com os filtros aplicados.'
            ], 404);
        }

        if ($totalAcoes > 500) {
            return response()->json([
                'error' => "Muitas ações encontradas ({$totalAcoes}). O limite é de 500 ações por PDF. Refine seus filtros."
            ], 422);
        }

        // Cria registro de exportação
        $export = Export::create([
            'deputado_id' => $user->deputado_id,
            'user_id' => $user->id,
            'tipo' => 'pdf',
            'filename' => 'acoes_' . time() . '.pdf',
            'total_registros' => $totalAcoes,
            'filtros' => $filtros,
            'status' => 'pending'
        ]);

        // Dispara job assíncrono
        GeneratePdfExport::dispatch($export, $user, $filtros);

        return response()->json([
            'message' => 'Exportação iniciada com sucesso.',
            'export_id' => $export->id,
            'total_acoes' => $totalAcoes
        ], 202);
    }

    /**
     * Consulta status da exportação (polling)
     */
    public function show(Request $request, int $id)
    {
        $export = Export::where('deputado_id', $request->user()->deputado_id)
            ->findOrFail($id);
        $this->authorize('view', $export);

        return response()->json([
            'id' => $export->id,
            'status' => $export->status,
            'total_registros' => $export->total_registros,
            'filtros' => $export->filtros,
            'erro' => $export->erro,
            'started_at' => $export->started_at?->format('d/m/Y H:i:s'),
            'completed_at' => $export->completed_at?->format('d/m/Y H:i:s')
        ]);
    }

    /**
     * Download do PDF
     */
    public function download(Request $request, int $id)
    {
        $export = Export::where('deputado_id', $request->user()->deputado_id)
            ->where('status', 'completed')
            ->findOrFail($id);
        $this->authorize('view', $export);

        $filePath = $export->getDownloadPath();

        if (!file_exists($filePath)) {
            return response()->json(['error' => 'Arquivo não encontrado.'], 404);
        }

        return response()->download($filePath, $export->filename, [
            'Content-Type' => 'application/pdf'
        ])->deleteFileAfterSend(true);
    }

    /**
     * Histórico de exportações
     */
    public function index(Request $request)
    {
        $exports = Export::where('deputado_id', $request->user()->deputado_id)
            ->orderBy('created_at', 'DESC')
            ->paginate(10);

        return response()->json($exports);
    }
}
