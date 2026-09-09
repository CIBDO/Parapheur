<?php

namespace App\Services;

use App\Enums\DocumentStatus;
use App\Models\Document;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;
use ZipArchive;

class ArchivePackService
{
    public function build(Document $document, User $actor): array
    {
        if ($document->status !== DocumentStatus::Archive) {
            throw new InvalidArgumentException('Seuls les dossiers archivés peuvent être exportés en pack.');
        }

        $document->load([
            'type',
            'structure',
            'author',
            'versions',
            'attachments',
            'comments.user',
            'actions.actor',
            'actions.delegator',
            'visas.user',
            'visas.delegator',
            'approvals.user',
            'approvals.delegator',
            'instructions.assignee',
            'transmissions.fromUser',
            'transmissions.toUser',
        ]);

        $tmpDir = storage_path('app/tmp/packs');
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $filename = sprintf('dossier_%s_%s.zip', Str::slug($document->reference ?: (string) $document->id), now()->format('Ymd_His'));
        $zipPath = $tmpDir.DIRECTORY_SEPARATOR.$filename;

        $zip = new ZipArchive;
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new InvalidArgumentException('Impossible de créer le pack ZIP.');
        }

        $zip->addFromString('manifest.json', json_encode([
            'exported_at' => now()->toIso8601String(),
            'exported_by' => $actor->only(['id', 'name', 'email']),
            'document' => [
                'id' => $document->id,
                'uuid' => $document->uuid,
                'reference' => $document->reference,
                'object' => $document->object,
                'status' => $document->status->value,
                'priority' => $document->priority->value ?? $document->priority,
                'confidentiality' => $document->confidentiality->value ?? $document->confidentiality,
                'type' => $document->type?->name,
                'structure' => $document->structure?->only(['code', 'name']),
                'author' => $document->author?->name,
                'archived_at' => optional($document->archived_at)->toIso8601String(),
            ],
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $zip->addFromString('historique.json', json_encode([
            'actions' => $document->actions,
            'comments' => $document->comments,
            'visas' => $document->visas,
            'approvals' => $document->approvals,
            'instructions' => $document->instructions,
            'transmissions' => $document->transmissions,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $zip->addFromString('historique.txt', $this->plainHistory($document));

        foreach ($document->versions as $version) {
            if (Storage::disk($version->disk)->exists($version->path)) {
                $zip->addFromString(
                    'versions/V'.$version->version_number.'_'.$version->original_name,
                    Storage::disk($version->disk)->get($version->path)
                );
            }
        }

        foreach ($document->attachments as $attachment) {
            if (Storage::disk($attachment->disk)->exists($attachment->path)) {
                $zip->addFromString(
                    'pieces_jointes/'.$attachment->kind.'_'.$attachment->original_name,
                    Storage::disk($attachment->disk)->get($attachment->path)
                );
            }
        }

        $zip->close();

        return [
            'path' => $zipPath,
            'filename' => $filename,
            'size' => filesize($zipPath) ?: 0,
        ];
    }

    private function plainHistory(Document $document): string
    {
        $lines = [
            'PACK D\'ARCHIVAGE — '.$document->reference,
            'Objet : '.$document->object,
            str_repeat('=', 60),
            '',
            'ACTIONS',
        ];

        foreach ($document->actions as $action) {
            $deleg = $action->delegator ? ' (par délégation de '.$action->delegator->name.')' : '';
            $actionType = $action->action_type instanceof \BackedEnum
                ? $action->action_type->value
                : (string) $action->action_type;
            $lines[] = sprintf(
                '- %s | %s%s | %s → %s | %s',
                optional($action->created_at)->format('Y-m-d H:i'),
                $action->actor?->name,
                $deleg,
                $action->from_status,
                $action->to_status,
                $actionType
            );
            if ($action->comment) {
                $lines[] = '  Commentaire : '.$action->comment;
            }
        }

        $lines[] = '';
        $lines[] = 'COMMENTAIRES / AVIS';
        foreach ($document->comments as $comment) {
            $lines[] = sprintf(
                '- %s | %s | %s',
                optional($comment->created_at)->format('Y-m-d H:i'),
                $comment->user?->name,
                $comment->kind
            );
            $lines[] = '  '.$comment->body;
        }

        return implode(PHP_EOL, $lines);
    }
}
