<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Lideranca extends Model
{
    protected $table = 'liderancas';

    protected $fillable = [
        'id_municipio',
        'classificacao_id',
        'funcao_id',
        'nome',
        'telefone',
        'data_nascimento',
        'alinhamento',
        'observacao'
    ];

    protected $hidden = [
        'slug'
    ];

    public function scopeFilter($query, $filters)
    {
        return $query
            ->when($filters['cidade'] ?? null, fn ($q, $v) =>
                $q->where('id_municipio', $v)
            )
            ->when($filters['alinhamento'] ?? null, fn ($q, $v) =>
                $q->where('alinhamento', $v)
            )
            ->when($filters['search'] ?? null, fn ($q, $v) =>
                $q->where('nome', 'like', "%{$v}%")
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
        
        if (!$classificacao) {
            return false;
        }
        
        // Lista de classificações que NÃO podem ser criadas/editadas manualmente
        $classificacoesProtegidas = ['politica']; // ⬅️ Fácil adicionar mais!
        
        return !in_array($classificacao->slug, $classificacoesProtegidas);
    }

    public static function cargoPertenceAClassificacao($funcaoId, $classificacaoId): bool
    {
        return CargoLideranca::where('id', $funcaoId)
            ->where('classificacao_id', $classificacaoId)
            ->exists();
    }
}
