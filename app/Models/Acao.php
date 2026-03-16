<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Acao extends Model
{
    protected $table = 'acoes';

    protected $fillable = [
        'user_id',
        'id_municipio',
        'orgao_governo_id',
        'categoria_investimento_id',
        'tipo_acao_id',
        'status_id',
        'lideranca_solicitante_id',
        'titulo',
        'numero_sei',
        'instrumento_path',
        'valor',
        'ano',
        'observacao'
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'ano' => 'integer'
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function municipio()
    {
        return $this->belongsTo(Municipio::class, 'id_municipio', 'id_municipio');
    }

    public function orgao()
    {
        return $this->belongsTo(OrgaoGoverno::class, 'orgao_governo_id');
    }

    public function categoriaInvestimento()
    {
        return $this->belongsTo(CategoriaInvestimento::class, 'categoria_investimento_id');
    }

    public function tipoAcao()
    {
        return $this->belongsTo(TipoAcao::class, 'tipo_acao_id');
    }

    public function status()
    {
        return $this->belongsTo(StatusAcao::class, 'status_id');
    }

    public function liderancaSolicitante()
    {
        return $this->belongsTo(Lideranca::class, 'lideranca_solicitante_id');
    }

    public function historico()
    {
        return $this->hasMany(AcaoHistorico::class, 'acao_id')->orderBy('created_at');
    }
    
    // Scopes
    public function scopeFilter($query, $filters)
    {
        return $query
            // Município
            ->when($filters['municipio'] ?? null, fn ($q, $v) =>
                $q->where('id_municipio', $v)
            )
            
            // Status
            ->when($filters['status'] ?? null, fn ($q, $v) =>
                $q->where('status_id', $v)
            )
            
            // Ano (exato ou faixa)
            ->when($filters['ano'] ?? null, fn ($q, $v) =>
                $q->where('ano', $v)
            )
            ->when($filters['ano_min'] ?? null, fn ($q, $v) =>
                $q->where('ano', '>=', $v)
            )
            ->when($filters['ano_max'] ?? null, fn ($q, $v) =>
                $q->where('ano', '<=', $v)
            )
            
            // Valor (faixa)
            ->when($filters['valor_min'] ?? null, fn ($q, $v) =>
                $q->where('valor', '>=', $v)
            )
            ->when($filters['valor_max'] ?? null, fn ($q, $v) =>
                $q->where('valor', '<=', $v)
            )
            
            // Órgão
            ->when($filters['orgao'] ?? null, fn ($q, $v) =>
                $q->where('orgao_governo_id', $v)
            )
            
            // Categoria
            ->when($filters['categoria'] ?? null, fn ($q, $v) =>
                $q->where('categoria_investimento_id', $v)
            )
            
            // Tipo de Ação
            ->when($filters['tipo_acao'] ?? null, fn ($q, $v) =>
                $q->where('tipo_acao_id', $v)
            )
            
            // Esfera (Federal/Estadual) - via relacionamento
            ->when($filters['esfera'] ?? null, fn ($q, $v) =>
                $q->whereHas('orgao.tipoOrgao.esferaGoverno', fn ($subQ) =>
                    $subQ->where('id', $v)
                )
            )
            
            // Tipo de Órgão (Ministério, Autarquia, etc) - via relacionamento
            ->when($filters['tipo_orgao'] ?? null, fn ($q, $v) =>
                $q->whereHas('orgao.tipoOrgao', fn ($subQ) =>
                    $subQ->where('id', $v)
                )
            )
            
            // Busca textual (título ou N° SEI)
            ->when($filters['search'] ?? null, fn ($q, $v) =>
                $q->where(fn ($subQ) =>
                    $subQ->where('titulo', 'like', "%{$v}%")
                        ->orWhere('numero_sei', 'like', "%{$v}%")
                )
            );
    }
}