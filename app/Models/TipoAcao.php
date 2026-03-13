<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TipoAcao extends Model
{
    protected $table = 'tipos_acao';

    protected $fillable = ['nome', 'slug'];

    public function acoes()
    {
        return $this->hasMany(Acao::class, 'tipo_acao_id');
    }
}