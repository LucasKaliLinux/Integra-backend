<?php

namespace App\Services;

use App\Models\Export;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PdfExportService
{
    private Export $export;
    private User $user;
    private array $filtros;

    public function __construct(Export $export, User $user, array $filtros)
    {
        $this->export = $export;
        $this->user = $user;
        $this->filtros = $filtros;
    }

    public function generate(): void
    {
        try {
            $this->export->markAsProcessing();

            // Busca as ações com os filtros aplicados
            $acoes = $this->fetchAcoes();

            // Prepara dados para o PDF
            $dados = $this->prepareData($acoes);

            // Gera o PDF
            $pdf = Pdf::loadView('pdf.acoes-export', $dados)
                ->setPaper('a4', 'landscape')
                ->setOption('defaultFont', 'DejaVu Sans');

            // Salva o arquivo
            $filePath = $this->export->getDownloadPath();
            
            // Cria diretório se não existir
            if (!file_exists(storage_path('app/exports'))) {
                mkdir(storage_path('app/exports'), 0755, true);
            }

            $pdf->save($filePath);

            $this->export->markAsCompleted();

        } catch (\Exception $e) {
            Log::error('Erro ao gerar PDF de ações', [
                'export_id' => $this->export->id,
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->export->markAsFailed($e->getMessage());
        }
    }

    private function fetchAcoes()
    {
        $query = $this->user->acoes()
            ->with([
                'municipio:id_municipio,nome',
                'orgao.tipoOrgao.esferaGoverno',
                'orgao:id,tipo_orgao_id,nome,sigla',
                'categoriaInvestimento:id,nome',
                'tipoAcao:id,nome',
                'status:id,nome,slug',
                'liderancaSolicitante:id,nome'
            ])
            ->select([
                'id',
                'titulo',
                'numero_sei',
                'id_municipio',
                'orgao_governo_id',
                'categoria_investimento_id',
                'tipo_acao_id',
                'status_id',
                'lideranca_solicitante_id',
                'valor',
                'ano',
                'observacao'
            ])
            ->filter($this->filtros);

        // Aplica ordenação se tiver
        if (!empty($this->filtros['sort_by'])) {
            $query->sort($this->filtros['sort_by'], $this->filtros['sort_order'] ?? 'asc');
        }

        // Limita a 500
        return $query->limit(500)->get();
    }

    private function prepareData($acoes): array
    {
        $acoesFormatadas = $acoes->map(function($acao) {
            return [
                'governo' => $acao->orgao->tipoOrgao->esferaGoverno->nome ?? '-',
                'titulo' => $acao->titulo,
                'orgao' => $acao->orgao->sigla ?? $acao->orgao->nome ?? '-',
                'categoria' => $acao->categoriaInvestimento->nome ?? '-',
                'municipio' => $acao->municipio->nome ?? '-',
                'valor' => (float) $acao->valor,
                'ano' => $acao->ano,
                'status' => $acao->status->nome ?? '-',
                'status_slug' => $acao->status->slug ?? 'solicitado', // ⬅️ NOVO
                'tipo' => $acao->tipoAcao->nome ?? '-',
                'lideranca' => $acao->liderancaSolicitante?->nome ?? $this->user->name,
                'numero_sei' => $acao->numero_sei,
                'observacao' => $acao->observacao
            ];
        })->toArray();

        return [
            'acoes' => $acoesFormatadas,
            'total' => count($acoesFormatadas),
            'filtros' => $this->formatFiltros(),
            'dataGeracao' => now()->format('d/m/Y H:i'),
            'usuario' => $this->user->name
        ];
    }

    private function formatFiltros(): array
    {
        $formatted = [];

        $labels = [
            'municipio' => 'Município',
            'status' => 'Status',
            'ano' => 'Ano',
            'ano_min' => 'Ano mínimo',
            'ano_max' => 'Ano máximo',
            'orgao' => 'Órgão',
            'categoria' => 'Categoria',
            'esfera' => 'Esfera',
            'search' => 'Busca'
        ];

        foreach ($this->filtros as $key => $value) {
            if (in_array($key, ['sort_by', 'sort_order', 'per_page', 'page'])) {
                continue; // Ignora parâmetros de paginação/ordenação
            }

            if (!empty($value)) {
                $formatted[$labels[$key] ?? $key] = $value;
            }
        }

        return $formatted;
    }
}