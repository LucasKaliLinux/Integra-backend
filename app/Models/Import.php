<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Import extends Model
{
    protected $fillable = [
        'deputado_id',
        'user_id',
        'filename',
        'original_filename',
        'total_rows',
        'processed_rows',
        'success_count',
        'error_count',
        'status',
        'errors',
        'started_at',
        'completed_at'
    ];

    protected $casts = [
        'errors' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    public function deputado()
    {
        return $this->belongsTo(Deputado::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function addError(int $linha, string $erro): void
    {
        $errors = $this->errors ?? [];
        $errors[] = ['linha' => $linha, 'erro' => $erro];
        $this->errors = $errors;
        $this->error_count = count($errors);
        $this->save();
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
            'errors' => [['linha' => 0, 'erro' => $erro]],
            'completed_at' => now()
        ]);
    }

    public function incrementProgress(): void
    {
        $this->increment('processed_rows');
    }

    public function incrementSuccess(): void
    {
        $this->increment('success_count');
    }
}