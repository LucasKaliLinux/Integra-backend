<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class StatusAcao extends Model
{
    protected $table = 'status_acao';

    protected $fillable = ['nome', 'slug', 'ordem'];

    public function acoes()
    {
        return $this->hasMany(Acao::class, 'status_id');
    }

    public function historicos()
    {
        return $this->hasMany(AcaoHistorico::class, 'status_id');
    }
}