<?php

namespace App\Jobs;

use App\Models\Import;
use App\Services\ExcelImportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessAcaoImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 3600; // 1 hora

    public $tries = 1; // Não tenta novamente se falhar

    protected Import $import;

    protected string $filePath;

    protected int $userId;

    public function __construct(Import $import, string $filePath, int $userId)
    {
        $this->import = $import;
        $this->filePath = $filePath;
        $this->userId = $userId;
    }

    public function handle(): void
    {
        $service = new ExcelImportService($this->import, $this->userId);
        $service->process($this->filePath);
    }

    public function failed(\Throwable $exception): void
    {
        $this->import->markAsFailed($exception->getMessage());
    }
}
