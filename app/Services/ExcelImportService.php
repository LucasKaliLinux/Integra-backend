<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use App\Helpers\CacheHelper;
use App\Models\Import;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

class ExcelImportService
{
    private Import $import;
    private int $userId;

    // Limites de segurança
    private const MAX_TITULO_LENGTH = 255;
    private const MAX_SEI_LENGTH = 50;
    private const MAX_OBSERVACAO_LENGTH = 1000;
    private const MIN_ANO = 1900;
    private const MAX_ANO = 2100;
    private const MAX_VALOR = 999999999.99;

    public function __construct(Import $import, int $userId)
    {
        $this->import = $import;
        $this->userId = $userId;
    }

    public function process(string $filePath): void
    {
        try {
            $this->import->markAsProcessing();

            $reader = new Xlsx();
            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getActiveSheet();

            // ⬇️ MUDANÇA: Pega a última linha COM DADOS (ignora linhas vazias de validação)
            $highestRow = $this->getLastDataRow($sheet);
            $totalRows = max(0, $highestRow - 3); // Ignora header (linhas 1-3)

            // Se não tiver dados, marca como completo
            if ($totalRows === 0) {
                $this->import->update(['total_rows' => 0]);
                $this->import->markAsCompleted();
                return;
            }

            $this->import->update(['total_rows' => $totalRows]);

            $cache = $this->buildCache();

            $chunkSize = 500;
            
            for ($startRow = 4; $startRow <= $highestRow; $startRow += $chunkSize) {
                $endRow = min($startRow + $chunkSize - 1, $highestRow);
                $this->processChunk($sheet, $startRow, $endRow, $cache);
                gc_collect_cycles();
            }

            $this->import->markAsCompleted();
            CacheHelper::invalidarTudo($this->userId);

        } catch (\Exception $e) {
            Log::error('Erro na importação de ações', [
                'import_id' => $this->import->id,
                'erro' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            $this->import->markAsFailed($e->getMessage());
        } finally {
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }
    }

    /**
     * ⬇️ NOVO: Detecta a última linha que TEM DADOS (ignora linhas de validação)
     */
    private function getLastDataRow($sheet): int
    {
        $highestRow = $sheet->getHighestRow();
        
        // Percorre de trás pra frente até achar uma linha com dados
        for ($row = $highestRow; $row >= 4; $row--) {
            // Verifica se a linha tem pelo menos um campo preenchido
            $titulo = trim($sheet->getCell("F{$row}")->getValue() ?? '');
            $municipio = trim($sheet->getCell("H{$row}")->getValue() ?? '');
            
            // Se tiver título OU município, considera como linha com dados
            if (!empty($titulo) || !empty($municipio)) {
                return $row;
            }
        }
        
        // Nenhuma linha com dados encontrada
        return 3; // Retorna linha do header
    }

    private function buildCache(): array
    {
        return [
            'esferas' => DB::table('esferas_governo')
                ->pluck('id', 'nome')
                ->toArray(),
            
            'orgaos' => DB::table('orgaos_governo')
                ->pluck('id', 'nome')
                ->toArray(),
            
            'categorias' => DB::table('categorias_investimento')
                ->pluck('id', 'nome')
                ->toArray(),
            
            'tipos' => DB::table('tipos_acao')
                ->pluck('id', 'nome')
                ->toArray(),
            
            'status' => DB::table('status_acao')
                ->pluck('id', 'nome')
                ->toArray(),
            
            'municipios' => DB::table('municipios')
                ->pluck('id_municipio', 'nome')
                ->toArray()
        ];
    }

    private function processChunk($sheet, int $startRow, int $endRow, array $cache): void
    {
        $acoesParaInserir = [];

        for ($row = $startRow; $row <= $endRow; $row++) {
            try {
                // ⬇️ MUDANÇA: Sanitiza e valida cada campo
                $esferaNome = $this->sanitizeText($sheet->getCell("B{$row}")->getValue());
                $orgaoNome = $this->sanitizeText($sheet->getCell("C{$row}")->getValue());
                $categoriaNome = $this->sanitizeText($sheet->getCell("D{$row}")->getValue());
                $tipoNome = $this->sanitizeText($sheet->getCell("E{$row}")->getValue());
                $titulo = $this->sanitizeText($sheet->getCell("F{$row}")->getValue());
                $numeroSei = $this->sanitizeText($sheet->getCell("G{$row}")->getValue());
                $municipioNome = $this->sanitizeText($sheet->getCell("H{$row}")->getValue());
                $valor = $sheet->getCell("I{$row}")->getValue() ?? 0;
                $ano = $sheet->getCell("J{$row}")->getValue() ?? null;
                $statusNome = $this->sanitizeText($sheet->getCell("K{$row}")->getValue());

                // ⬇️ MUDANÇA: Pula linha vazia (melhoria na detecção)
                if ($this->isEmptyRow($titulo, $municipioNome, $orgaoNome, $categoriaNome)) {
                    $this->import->incrementProgress();
                    continue;
                }

                // ⬇️ VALIDAÇÕES DE SEGURANÇA
                
                // Valida tamanho do título
                if (strlen($titulo) > self::MAX_TITULO_LENGTH) {
                    $this->import->addError($row, "Título muito longo (máx. " . self::MAX_TITULO_LENGTH . " caracteres)");
                    $this->import->incrementProgress();
                    continue;
                }

                // Valida tamanho do N° SEI
                if (strlen($numeroSei) > self::MAX_SEI_LENGTH) {
                    $this->import->addError($row, "N° SEI muito longo (máx. " . self::MAX_SEI_LENGTH . " caracteres)");
                    $this->import->incrementProgress();
                    continue;
                }

                // Valida campos obrigatórios
                if (empty($titulo)) {
                    $this->import->addError($row, 'Título da ação é obrigatório');
                    $this->import->incrementProgress();
                    continue;
                }

                if (empty($municipioNome)) {
                    $this->import->addError($row, 'Município é obrigatório');
                    $this->import->incrementProgress();
                    continue;
                }

                if (empty($ano)) {
                    $this->import->addError($row, 'Ano é obrigatório');
                    $this->import->incrementProgress();
                    continue;
                }

                // Busca IDs no cache
                $orgaoId = $cache['orgaos'][$orgaoNome] ?? null;
                $categoriaId = $cache['categorias'][$categoriaNome] ?? null;
                $tipoId = $cache['tipos'][$tipoNome] ?? null;
                $statusId = $cache['status'][$statusNome] ?? null;
                $municipioId = $cache['municipios'][$municipioNome] ?? null;

                // Valida se encontrou os IDs
                if (!$orgaoId) {
                    $this->import->addError($row, "Órgão '{$orgaoNome}' não encontrado");
                    $this->import->incrementProgress();
                    continue;
                }

                if (!$categoriaId) {
                    $this->import->addError($row, "Categoria '{$categoriaNome}' não encontrada");
                    $this->import->incrementProgress();
                    continue;
                }

                if (!$tipoId) {
                    $this->import->addError($row, "Tipo de ação '{$tipoNome}' não encontrado");
                    $this->import->incrementProgress();
                    continue;
                }

                if (!$statusId) {
                    $this->import->addError($row, "Status '{$statusNome}' não encontrado");
                    $this->import->incrementProgress();
                    continue;
                }

                if (!$municipioId) {
                    $this->import->addError($row, "Município '{$municipioNome}' não encontrado");
                    $this->import->incrementProgress();
                    continue;
                }

                // ⬇️ VALIDAÇÃO DE VALOR
                $valor = $this->parseValor($valor);
                
                if ($valor < 0) {
                    $this->import->addError($row, 'Valor não pode ser negativo');
                    $this->import->incrementProgress();
                    continue;
                }

                if ($valor > self::MAX_VALOR) {
                    $this->import->addError($row, 'Valor muito alto (máx. R$ ' . number_format(self::MAX_VALOR, 2, ',', '.') . ')');
                    $this->import->incrementProgress();
                    continue;
                }

                // ⬇️ VALIDAÇÃO DE ANO
                $ano = $this->parseAno($ano);

                if (!$ano) {
                    $this->import->addError($row, 'Ano inválido');
                    $this->import->incrementProgress();
                    continue;
                }

                if ($ano < self::MIN_ANO || $ano > self::MAX_ANO) {
                    $this->import->addError($row, "Ano inválido (deve estar entre " . self::MIN_ANO . " e " . self::MAX_ANO . ")");
                    $this->import->incrementProgress();
                    continue;
                }

                // Verifica duplicata
                $existe = DB::table('acoes')
                    ->where('deputado_id', $this->import->deputado_id)
                    ->where('titulo', $titulo)
                    ->where('id_municipio', $municipioId)
                    ->where('ano', $ano)
                    ->exists();

                if ($existe) {
                    $this->import->addError($row, 'Ação duplicada (mesmo título, município e ano)');
                    $this->import->incrementProgress();
                    continue;
                }

                // Adiciona à lista
                $acoesParaInserir[] = [
                    'deputado_id' => $this->import->deputado_id,
                    'user_id' => $this->userId,
                    'id_municipio' => $municipioId,
                    'orgao_governo_id' => $orgaoId,
                    'categoria_investimento_id' => $categoriaId,
                    'tipo_acao_id' => $tipoId,
                    'status_id' => $statusId,
                    'lideranca_solicitante_id' => null,
                    'titulo' => $titulo,
                    'numero_sei' => $numeroSei ?: null,
                    'instrumento_path' => null,
                    'valor' => $valor,
                    'ano' => $ano,
                    'observacao' => null,
                    'created_at' => now(),
                    'updated_at' => now(),
                    '_status_id' => $statusId
                ];

            } catch (\Exception $e) {
                $this->import->addError($row, 'Erro ao processar: ' . $e->getMessage());
                $this->import->incrementProgress();
                continue;
            }
        }

        // Insere em lote
        if (!empty($acoesParaInserir)) {
            DB::transaction(function() use ($acoesParaInserir) {
                foreach ($acoesParaInserir as $acaoData) {
                    $statusId = $acaoData['_status_id'];
                    unset($acaoData['_status_id']);

                    $acaoId = DB::table('acoes')->insertGetId($acaoData);

                    DB::table('acoes_historico')->insert([
                        'acao_id' => $acaoId,
                        'status_id' => $statusId,
                        'user_id' => $this->userId,
                        'observacao' => 'Ação criada via importação Excel',
                        'created_at' => now()
                    ]);

                    $this->import->incrementSuccess();
                    $this->import->incrementProgress();
                }
            });
        }
    }

    /**
     * ⬇️ NOVO: Sanitiza texto (anti-XSS e trim)
     */
    private function sanitizeText(?string $value): string
    {
        if ($value === null) {
            return '';
        }

        // Remove tags HTML/JavaScript
        $value = strip_tags($value);
        
        // Remove caracteres de controle e espaços extras
        $value = trim(preg_replace('/\s+/', ' ', $value));
        
        // Remove caracteres especiais perigosos
        $value = str_replace(['<', '>', '"', "'", '\\'], '', $value);
        
        return $value;
    }

    /**
     * ⬇️ NOVO: Verifica se linha está vazia (ignora linhas de validação)
     */
    private function isEmptyRow(?string $titulo, ?string $municipio, ?string $orgao, ?string $categoria): bool
    {
        // Linha vazia se TODOS os campos principais estiverem vazios
        return empty($titulo) && empty($municipio) && empty($orgao) && empty($categoria);
    }

    private function parseValor($valor): float
    {
        if (is_numeric($valor)) {
            return (float) $valor;
        }

        // Remove formatação
        $valor = preg_replace('/[^0-9,.-]/', '', (string) $valor);
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);

        return max(0, (float) $valor); // Garante que não seja negativo
    }

    private function parseAno($ano): ?int
    {
        // ⬇️ IMPORTANTE: Retorna null se vazio (validação depois vai pegar)
        if ($ano === null || $ano === '') {
            return null;
        }

        // Converte pra string pra facilitar
        $anoStr = (string) $ano;

        // 1️⃣ Tenta como número inteiro direto
        if (is_numeric($ano)) {
            $anoInt = (int) $ano;
            
            // Se tá na faixa válida de anos
            if ($anoInt >= self::MIN_ANO && $anoInt <= self::MAX_ANO) {
                return $anoInt;
            }

            // Se tá na faixa de datas seriais do Excel
            if ($anoInt > 0 && $anoInt < 2958466) {
                try {
                    $dateObj = Date::excelToDateTimeObject($ano);
                    return (int) $dateObj->format('Y');
                } catch (\Exception $e) {
                    // Falhou, tenta regex
                }
            }
        }

        // 2️⃣ Tenta extrair 4 dígitos
        if (preg_match('/(\d{4})/', $anoStr, $matches)) {
            $extractedYear = (int) $matches[1];
            
            if ($extractedYear >= self::MIN_ANO && $extractedYear <= self::MAX_ANO) {
                return $extractedYear;
            }
        }

        // 3️⃣ Não conseguiu → retorna null (validação depois vai pegar)
        return null;
    }
}