<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Deputado extends Model
{
    protected $fillable = [
        'titulo_eleitoral',
        'nome',
        'partido',
        'cargo',
        'ativo'
    ];

    protected $casts = [
        'ativo' => 'boolean'
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function acoes(): HasMany
    {
        return $this->hasMany(Acao::class);
    }

    public function liderancas(): HasMany
    {
        return $this->hasMany(Lideranca::class);
    }

    public function imports()
    {
        return $this->hasMany(Import::class);
    }

    public function exports()
    {
        return $this->hasMany(Export::class);
    }

    public function municipios(): BelongsToMany
    {
        return $this->belongsToMany(
            Municipio::class,
            'user_municipios',
            'deputado_id',
            'id_municipio'
        )->withPivot('user_id');
    }
}