<?php

namespace App\Observers;

use App\Models\Acao;
use App\Models\AcaoHistorico;

class AcaoObserver
{
    /**
     * Handle the Acao "created" event.
     */
    public function created(Acao $acao): void
    {
        AcaoHistorico::create([
            'acao_id' => $acao->id,
            'status_id' => $acao->status_id,
            'user_id' => auth()->id() ?? $acao->user_id,  // Quem criou
            'observacao' => 'Ação criada'
        ]);
    }

    /**
     * Handle the Acao "updating" event.
     */
    public function updating(Acao $acao): void
    {
        // Só registra se o status mudou
        if ($acao->isDirty('status_id')) {
            AcaoHistorico::create([
                'acao_id' => $acao->id,
                'status_id' => $acao->getOriginal('status_id'), // Status ANTERIOR
                'user_id' => auth()->id() ?? $acao->user_id,    // Quem alterou
                'observacao' => request('observacao_mudanca') ?? 'Status alterado'
            ]);
        }
    }

    /**
     * Handle the Acao "updated" event.
     */
    public function updated(Acao $acao): void
    {
        // Registra o NOVO status após a atualização
        if ($acao->wasChanged('status_id')) {
            AcaoHistorico::create([
                'acao_id' => $acao->id,
                'status_id' => $acao->status_id, // Status NOVO
                'user_id' => auth()->id() ?? $acao->user_id,
                'observacao' => request('observacao_mudanca') ?? 'Status alterado para ' . $acao->status->nome
            ]);
        }
    }
}