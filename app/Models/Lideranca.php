<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lideranca extends Model
{
    protected $fillable = [
        'id_municipio',
        'deputado_id',      // ✅ Relacionamento principal
        'user_id',          // ⚠️ NOTA: Considera-se que é campo legado ou para audit
        'classificacao_id',
        'funcao_id',
        'nome',
        'telefone',
        'instagram',
        'data_nascimento',
        'alinhamento',
        'observacao',
    ];

    protected $hidden = [
        'slug',
    ];

    public function scopeForDeputado($query, $deputadoId)
    {
        return $query->where('deputado_id', $deputadoId);
    }

    public function scopeFilter($query, $filters)
    {
        return $query
            // Filtro por município
            ->when($filters['municipio'] ?? null, fn ($q, $v) => $q->where('id_municipio', $v)
            )

            // Filtro por alinhamento (aliado/oposicao)
            ->when($filters['alinhamento'] ?? null, fn ($q, $v) => $q->where('alinhamento', $v)
            )

            // Filtro por cargo (função)
            ->when($filters['cargo'] ?? null, fn ($q, $v) => $q->where('funcao_id', $v)
            )

            // Filtro por classificação
            ->when($filters['classificacao'] ?? null, fn ($q, $v) => $q->where('classificacao_id', $v)
            )

            // Busca textual (nome ou telefone)
            ->when($filters['search'] ?? null, fn ($q, $v) => $q->where(fn ($subQ) => $subQ->where('nome', 'like', "%{$v}%")
                ->orWhere('telefone', 'like', "%{$v}%")
            )
            );
    }

    public function isPolitica(): bool
    {
        return $this->classificacao?->slug === 'politica';
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

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function municipio()
    {
        return $this->belongsTo(Municipio::class, 'id_municipio', 'id_municipio');
    }

    public function classificacao()
    {
        return $this->belongsTo(ClassificacaoLideranca::class, 'classificacao_id');
    }

    public function funcao()
    {
        return $this->belongsTo(CargoLideranca::class, 'funcao_id');
    }

    public static function classificacaoEditavel($classificacaoId): bool
    {
        $classificacao = ClassificacaoLideranca::find($classificacaoId);

        if (! $classificacao) {
            return false;
        }

        // Lista de classificações que NÃO podem ser criadas/editadas manualmente
        $classificacoesProtegidas = ['politica']; // ⬅️ Fácil adicionar mais!

        return ! in_array($classificacao->slug, $classificacoesProtegidas);
    }

    public static function cargoPertenceAClassificacao($funcaoId, $classificacaoId): bool
    {
        return CargoLideranca::where('id', $funcaoId)
            ->where('classificacao_id', $classificacaoId)
            ->exists();
    }
}
