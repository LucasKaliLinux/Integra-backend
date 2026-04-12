<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Storage;

class InstrumentoHelper
{
    public static function buildFromPath(?string $path, $updatedAt): array
    {
        if (!$path || !Storage::disk('public')->exists($path)) {
            return [
                'exists' => false,
                'filename' => null,
                'extension' => null,
                'size_bytes' => null,
                'size_formatted' => null,
                'mime_type' => null,
                'uploaded_at' => null,
            ];
        }

        $fullPath = Storage::disk('public')->path($path);
        $extension = strtoupper(pathinfo($path, PATHINFO_EXTENSION));
        $sizeInBytes = filesize($fullPath);
        $sizeFormatted = $sizeInBytes >= 1048576
            ? round($sizeInBytes / 1048576, 2) . ' MB'
            : round($sizeInBytes / 1024, 2) . ' KB';

        return [
            'exists' => true,
            'filename' => basename($path),
            'extension' => $extension,
            'size_bytes' => $sizeInBytes,
            'size_formatted' => $sizeFormatted,
            'mime_type' => Storage::disk('public')->mimeType($path),
            'uploaded_at' => $updatedAt?->format('d/m/Y H:i'),
        ];
    }
}
