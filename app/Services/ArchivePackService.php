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
    public function build(Document $document, User $actor, bool $allowActive = false): array
    {
        if (! $allowActive && $document->status !== DocumentStatus::Archive) {
            throw new InvalidArgumentException('Seuls les dossiers archivés peuvent être exportés en pack.');
        }

        $document->load([
            'type',
            'category',
            'structure',
            'ownerStructure',
            'classificationNode',
            'author',
            'versions',
            'attachments',
            'tags',
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
            'outgoingLinks.target',
            'officialVersion',
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
                'dossier_number' => $document->dossier_number,
                'object' => $document->object,
                'title' => $document->title,
                'description' => $document->description,
                'status' => $document->status->value ?? $document->status,
                'priority' => $document->priority->value ?? $document->priority,
                'confidentiality' => $document->confidentiality->value ?? $document->confidentiality,
                'origin' => $document->origin->value ?? $document->origin,
                'archive_status' => $document->archive_status->value ?? $document->archive_status,
                'type' => $document->type?->name,
                'category' => $document->category?->name,
                'classification' => $document->classificationNode?->path,
                'structure' => $document->structure?->only(['code', 'name']),
                'author' => $document->author?->name,
                'tags' => $document->tags->pluck('name'),
                'official_version' => $document->officialVersion?->version_number,
                'archived_at' => optional($document->archived_at)->toIso8601String(),
                'retention_until' => optional($document->retention_until)?->toDateString(),
            ],
            'linked_documents' => $document->outgoingLinks->map(fn ($l) => [
                'relation' => $l->relation_type,
                'target_id' => $l->target_document_id,
                'target_reference' => $l->target?->reference,
                'target_object' => $l->target?->object,
            ]),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $zip->addFromString('synthese.txt', implode("\n", [
            'FICHE DE SYNTHÈSE GED',
            str_repeat('=', 60),
            'Référence : '.$document->reference,
            'N° dossier : '.($document->dossier_number ?: '—'),
            'Objet : '.$document->object,
            'Titre : '.($document->title ?: '—'),
            'Type : '.($document->type?->name ?: '—'),
            'Auteur : '.($document->author?->name ?: '—'),
            'Structure : '.($document->structure?->name ?: '—'),
            'Statut : '.($document->status->value ?? $document->status),
            'Confidentialité : '.($document->confidentiality->value ?? $document->confidentiality),
            'Classement : '.($document->classificationNode?->path ?: '—'),
            'Version officielle : '.($document->officialVersion?->version_number ?: '—'),
            'Tags : '.$document->tags->pluck('name')->join(', '),
        ]));

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
