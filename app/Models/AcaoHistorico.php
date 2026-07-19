<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class AcaoHistorico extends Model
{
    use SoftDeletes;

    protected $table = 'acoes_historico';

    protected $fillable = [
        'acao_id',
        'status_id',
        'user_id',          // ⚠️ NOTA: Representa 'quem fez a alteração', considere renomear para 'updated_by_user_id'
        'observacao',
    ];

    // Só created_at (não precisa de updated_at)
    const UPDATED_AT = null;

    public function acao()
    {
        return $this->belongsTo(Acao::class, 'acao_id');
    }

    public function status()
    {
        return $this->belongsTo(StatusAcao::class, 'status_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
