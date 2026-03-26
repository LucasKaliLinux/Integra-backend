<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Export;
use App\Models\User;
use App\Services\PdfExportService;

class GeneratePdfExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 300; // 5 minutos
    public $tries = 1;

    protected Export $export;
    protected User $user;
    protected array $filtros;

    public function __construct(Export $export, User $user, array $filtros)
    {
        $this->export = $export;
        $this->user = $user;
        $this->filtros = $filtros;
    }

    public function handle(): void
    {
        $service = new PdfExportService($this->export, $this->user, $this->filtros);
        $service->generate();
    }

    public function failed(\Throwable $exception): void
    {
        $this->export->markAsFailed($exception->getMessage());
    }
}