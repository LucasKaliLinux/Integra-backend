<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CampanhaPolitico extends Model
{
    /**
     * Cargos aceitos nos cadastros de políticos de campanha.
     * Validados no FormRequest (não usamos ENUM MySQL).
     */
    public const CARGOS = ['estadual', 'federal', 'senador', 'governador', 'presidente'];

    protected $table = 'campanha_politicos';

    /**
     * "deputado_id" fica FORA do fillable de propósito: é atribuído
     * explicitamente no controller a partir do usuário autenticado.
     */
    protected $fillable = [
        'nome',
        'nome_urna',
        'numero_eleitoral',
        'cargo',
        'partido',
        'federacao',
        'slogan',
        'cor_principal',
        'coordenador',
        'telefone',
        'foto_url',
        'ativo',
    ];

    protected $casts = [
        'ativo' => 'boolean',
    ];

    public function scopeForDeputado($query, $deputadoId)
    {
        return $query->where('deputado_id', $deputadoId);
    }

    public function scopeFilter($query, $filters)
    {
        return $query
            // Filtro por cargo (estadual|federal|senador|governador|presidente)
            ->when($filters['cargo'] ?? null, fn ($q, $v) => $q->where('cargo', $v))

            // Filtro por partido
            ->when($filters['partido'] ?? null, fn ($q, $v) => $q->where('partido', $v))

            // Filtro por ativo (true/false)
            ->when(
                isset($filters['ativo']) && $filters['ativo'] !== '',
                fn ($q) => $q->where('ativo', filter_var($filters['ativo'], FILTER_VALIDATE_BOOLEAN))
            )

            // Busca textual (nome, nome de urna, partido ou número eleitoral)
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($subQ) => $subQ
                ->where('nome', 'like', "%{$v}%")
                ->orWhere('nome_urna', 'like', "%{$v}%")
                ->orWhere('partido', 'like', "%{$v}%")
                ->orWhere('numero_eleitoral', 'like', "%{$v}%")
            ));
    }

    /*
    |--------------------------------------------------------------------------
    | RELACIONAMENTOS
    |--------------------------------------------------------------------------
    */

    public function deputado()
    {
        return $this->belongsTo(Deputado::class);
    }
}
