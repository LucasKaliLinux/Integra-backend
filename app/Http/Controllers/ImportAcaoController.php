<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportAcaoRequest;
use App\Models\Import;
use App\Jobs\ProcessAcaoImport;
use Illuminate\Http\Request;

class ImportAcaoController extends Controller
{
    /**
     * Upload do arquivo Excel e dispara job
     */
    public function store(ImportAcaoRequest $request)
    {
        $user = $request->user();

        // Salva arquivo temporariamente
        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $filename = time() . '_' . $originalName;
        $filePath = storage_path('app/temp/' . $filename);

        // Cria diretório se não existir
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0755, true);
        }

        $file->move(storage_path('app/temp'), $filename);

        // Cria registro de importação
        $import = Import::create([
            'user_id' => $user->id,
            'filename' => $filename,
            'original_filename' => $originalName,
            'status' => 'pending'
        ]);

        // Dispara job assíncrono
        ProcessAcaoImport::dispatch($import, $filePath, $user->id);

        return response()->json([
            'message' => 'Importação iniciada com sucesso.',
            'import_id' => $import->id
        ], 202);
    }

    /**
     * Consulta status da importação (polling)
     */
    public function show(Request $request, int $id)
    {
        $import = Import::where('user_id', $request->user()->id)
            ->findOrFail($id);

        return response()->json([
            'id' => $import->id,
            'filename' => $import->original_filename,
            'status' => $import->status,
            'total_rows' => $import->total_rows,
            'processed_rows' => $import->processed_rows,
            'success_count' => $import->success_count,
            'error_count' => $import->error_count,
            'progress_percent' => $import->total_rows > 0 
                ? round(($import->processed_rows / $import->total_rows) * 100, 2)
                : 0,
            'errors' => $import->errors,
            'started_at' => $import->started_at?->format('d/m/Y H:i:s'),
            'completed_at' => $import->completed_at?->format('d/m/Y H:i:s')
        ]);
    }

    /**
     * Histórico de importações do usuário
     */
    public function index(Request $request)
    {
        $imports = Import::where('user_id', $request->user()->id)
            ->orderBy('created_at', 'DESC')
            ->paginate(10);

        return response()->json($imports);
    }
}