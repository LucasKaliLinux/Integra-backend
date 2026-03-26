<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Export extends Model
{
    protected $fillable = [
        'user_id',
        'tipo',
        'filename',
        'total_registros',
        'filtros',
        'status',
        'erro',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'filtros' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function markAsProcessing(): void
    {
        $this->update([
            'status' => 'processing',
            'started_at' => now()
        ]);
    }

    public function markAsCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now()
        ]);
    }

    public function markAsFailed(string $erro): void
    {
        $this->update([
            'status' => 'failed',
            'erro' => $erro,
            'completed_at' => now()
        ]);
    }

    public function getDownloadPath(): string
    {
        return storage_path("app/exports/{$this->filename}");
    }
}