<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Registro append-only de uso do sistema pelo gabinete + eventos de auth.
 *
 * Imutabilidade e privacidade são garantidas AQUI, no model, para que nenhum
 * call-site consiga furar as regras:
 *  - update/delete via Eloquent são bloqueados (append-only).
 *  - `contexto` passa por uma whitelist estrita deny-by-default.
 */
class AtividadeLog extends Model
{
    protected $table = 'atividade_logs';

    // Append-only: só created_at, sem updated_at.
    public $timestamps = true;

    const UPDATED_AT = null;

    /**
     * Chaves permitidas em `contexto`. Deny-by-default: qualquer outra chave é
     * descartada. Nunca inclua aqui campos livres do usuário nem PII (titulo,
     * valor, nome/e-mail/CPF/telefone de liderança, nome de arquivo, etc.).
     */
    public const CONTEXTO_PERMITIDO = [
        'acao_id',
        'lideranca_id',
        'municipio_id',
        'import_id',
        'export_id',
        'status_slug',
        'categoria_slug',
        'tipo_slug',
        'origem',
        'quantidade',
        'total',
    ];

    /** Tamanho máximo de um valor string dentro do contexto. */
    public const CONTEXTO_MAX_STRING = 64;

    /** Tamanho máximo do JSON serializado do contexto (bytes). */
    public const CONTEXTO_MAX_BYTES = 2048;

    protected $fillable = [
        'deputado_id',
        'user_id',
        'sessao_id',
        'categoria',
        'acao',
        'recurso_tipo',
        'recurso_id',
        'metodo_http',
        'rota',
        'plataforma',
        'navegador',
        'contexto',
    ];

    protected $casts = [
        'recurso_id' => 'integer',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Garante append-only na camada de aplicação.
        static::updating(function () {
            throw new RuntimeException('AtividadeLog é append-only: update não é permitido.');
        });

        static::deleting(function () {
            throw new RuntimeException('AtividadeLog é append-only: delete não é permitido.');
        });
    }

    public function deputado(): BelongsTo
    {
        return $this->belongsTo(Deputado::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sessao(): BelongsTo
    {
        return $this->belongsTo(AtividadeSessao::class, 'sessao_id');
    }

    /**
     * Whitelist estrita (deny-by-default) para o contexto:
     *  - só chaves da whitelist;
     *  - só valores escalares (profundidade 1 — arrays/objetos são descartados);
     *  - strings acima do limite são descartadas;
     *  - JSON final acima do limite de bytes vira null.
     */
    public function setContextoAttribute($value): void
    {
        if (! is_array($value)) {
            $this->attributes['contexto'] = null;

            return;
        }

        $limpo = [];

        foreach ($value as $chave => $item) {
            if (! in_array($chave, self::CONTEXTO_PERMITIDO, true)) {
                continue;
            }

            // Profundidade 1: nada de arrays/objetos.
            if (! is_scalar($item)) {
                continue;
            }

            if (is_string($item) && mb_strlen($item) > self::CONTEXTO_MAX_STRING) {
                continue;
            }

            $limpo[$chave] = $item;
        }

        if ($limpo === []) {
            $this->attributes['contexto'] = null;

            return;
        }

        $json = json_encode($limpo, JSON_UNESCAPED_UNICODE);

        if ($json === false || strlen($json) > self::CONTEXTO_MAX_BYTES) {
            $this->attributes['contexto'] = null;

            return;
        }

        $this->attributes['contexto'] = $json;
    }

    public function getContextoAttribute($value)
    {
        return $value ? json_decode($value, true) : null;
    }
}
