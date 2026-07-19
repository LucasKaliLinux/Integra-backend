<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'deputado_id',
        'name',
        'email',
        'password',
        'ativo',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verified_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function deputado(): BelongsTo
    {
        return $this->belongsTo(Deputado::class);
    }

    public function acoes(): HasMany
    {
        // ⚠️ NOTA: Acões pertencem ao DEPUTADO, não ao user
        // Esta relação existe por compatibilidade, mas use deputado->acoes() quando possível
        return $this->hasMany(Acao::class);
    }

    public function liderancas(): HasMany
    {
        // ⚠️ NOTA: Lideranças pertencem ao DEPUTADO, não ao user
        // Esta relação existe por compatibilidade, mas use deputado->liderancas() quando possível
        return $this->hasMany(Lideranca::class);
    }

    public function atividadeLogs(): HasMany
    {
        return $this->hasMany(AtividadeLog::class);
    }

    public function atividadeSessoes(): HasMany
    {
        return $this->hasMany(AtividadeSessao::class);
    }

    public function municipios(): BelongsToMany
    {
        return $this->belongsToMany(
            Municipio::class,
            'user_municipios',
            'user_id',
            'id_municipio',
            'id',
            'id_municipio'
        );
    }

    public function municipiosSelecionados(): BelongsToMany
    {
        // ⚠️ DEPRECATED: Municípios agora são do DEPUTADO, não do usuário
        // Use $user->deputado->municipios() ao invés desta relação
        // Esta relação existe apenas por compatibilidade temporária
        return $this->belongsToMany(Municipio::class, 'user_municipios', 'user_id', 'id_municipio');
    }
}
