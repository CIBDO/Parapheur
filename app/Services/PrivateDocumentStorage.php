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

    public function storeContent(string $contents, string $originalName, string $mimeType, string $directory = 'documents'): array
    {
        $safeName = Str::uuid()->toString().'_'.Str::slug(pathinfo($originalName, PATHINFO_FILENAME));
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $filename = $extension ? "{$safeName}.{$extension}" : $safeName;
        $path = $directory.'/'.now()->format('Y/m').'/'.$filename;

        Storage::disk('local')->put($path, $contents);
        $full = Storage::disk('local')->path($path);

        return [
            'disk' => 'local',
            'path' => $path,
            'original_name' => $originalName,
            'mime_type' => $mimeType,
            'size' => strlen($contents),
            'checksum' => is_file($full) ? hash_file('sha256', $full) : hash('sha256', $contents),
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
