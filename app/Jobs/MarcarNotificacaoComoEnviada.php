<?php

namespace App\Jobs;

use App\Models\Notificacao;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MarcarNotificacaoComoEnviada implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;

    protected int $notificacaoId;

    public function __construct(Notificacao $notificacao)
    {
        $this->notificacaoId = $notificacao->id;
    }

    public function handle(): void
    {
        Notificacao::whereKey($this->notificacaoId)
            ->where('enviada', false)
            ->update(['enviada' => true]);
    }
}
