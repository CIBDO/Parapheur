<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PrivateDocumentStorage
{
    public function store(UploadedFile $file, string $directory = 'documents'): array
    {
        $safeName = Str::uuid()->toString().'_'.Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $extension = $file->getClientOriginalExtension();
        $filename = $extension ? "{$safeName}.{$extension}" : $safeName;
        $path = $file->storeAs($directory.'/'.now()->format('Y/m'), $filename, 'local');

        return [
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size' => $file->getSize() ?: 0,
            'checksum' => hash_file('sha256', $file->getRealPath()),
        ];
    }

    public function absolutePath(string $disk, string $path): string
    {
        return Storage::disk($disk)->path($path);
    }

    public function exists(string $disk, string $path): bool
    {
        return Storage::disk($disk)->exists($path);
    }
}
