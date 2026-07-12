<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Sessão de uso do gabinete. Aberta no login, fechada no logout ou por
 * inferência de timeout (comando agendado).
 */
class AtividadeSessao extends Model
{
    protected $table = 'atividade_sessoes';

    // Sem created_at/updated_at padrão — controlamos os timestamps na mão.
    public $timestamps = false;

    protected $fillable = [
        'deputado_id',
        'user_id',
        'iniciada_em',
        'ultima_atividade_em',
        'encerrada_em',
        'duracao_segundos',
        'origem',
    ];

    protected $casts = [
        'iniciada_em' => 'datetime',
        'ultima_atividade_em' => 'datetime',
        'encerrada_em' => 'datetime',
        'duracao_segundos' => 'integer',
    ];

    public function deputado(): BelongsTo
    {
        return $this->belongsTo(Deputado::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(AtividadeLog::class, 'sessao_id');
    }
}
