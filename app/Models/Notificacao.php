<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Notificacao extends Model
{
    protected $table = 'notificacoes';

    protected $fillable = [
        'titulo',
        'mensagem',
        'data_envio',
        'audiencia',
        'enviada',
    ];

    protected $casts = [
        'data_envio' => 'datetime',
        'enviada' => 'boolean',
    ];

    /**
     * Deputados específicos (se audiencia = 'especifico')
     */
    public function deputados(): BelongsToMany
    {
        return $this->belongsToMany(Deputado::class, 'notificacao_deputados');
    }

    /**
     * Usuários que leram
     */
    public function leituras(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'notificacao_leituras')
            ->withPivot('lida_em');
    }

    /**
     * Total de visualizações
     */
    public function totalVisualizacoes(): int
    {
        return $this->leituras()->count();
    }

    /**
     * Total esperado (quantos deveriam ver)
     */
    public function totalEsperado(): int
    {
        if ($this->audiencia === 'todos') {
            return User::whereNotNull('deputado_id')->count();
        }

        // Específico: soma users dos deputados selecionados
        $deputadosIds = $this->deputados()->pluck('deputados.id');

        return User::whereIn('deputado_id', $deputadosIds)->count();
    }
}
