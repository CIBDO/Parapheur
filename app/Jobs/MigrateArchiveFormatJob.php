<?php

namespace App\Jobs;

use App\Models\ArchivePreservationEvent;
use App\Models\ArchiveRecord;
use App\Models\User;
use App\Services\Archives\ArchiveAuditService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MigrateArchiveFormatJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public int $recordId,
        public int $actorId,
        public string $targetProfile = 'pdfa',
    ) {}

    public function handle(ArchiveAuditService $audit): void
    {
        $record = ArchiveRecord::query()->with(['documentVersion', 'document.officialVersion', 'document.latestVersion'])->find($this->recordId);
        $actor = User::query()->find($this->actorId);
        if (! $record || ! $actor) {
            return;
        }

        $version = $record->documentVersion
            ?: ($record->document?->officialVersion ?: $record->document?->latestVersion);

        if (! $version || ! $version->disk || ! $version->path || ! Storage::disk($version->disk)->exists($version->path)) {
            $this->markPlanned($record, $actor, $audit, 'Fichier source indisponible');

            return;
        }

        $gs = (string) config('archives.format_migration.ghostscript_binary', 'gs');
        $available = $this->binaryAvailable($gs);
        if (! $available) {
            $this->markPlanned($record, $actor, $audit, 'Moteur Ghostscript indisponible ('.$gs.')');

            return;
        }

        $sourceAbs = Storage::disk($version->disk)->path($version->path);
        $outRel = 'migrations/'.$record->permanent_id.'/'.Str::uuid().'.pdf';
        $outAbs = Storage::disk('archives')->path($outRel);
        $outDir = dirname($outAbs);
        if (! is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $result = Process::run([
            $gs,
            '-dPDFA=2',
            '-dBATCH',
            '-dNOPAUSE',
            '-sColorConversionStrategy=RGB',
            '-sDEVICE=pdfwrite',
            '-sOutputFile='.$outAbs,
            $sourceAbs,
        ]);

        if (! $result->successful() || ! is_file($outAbs)) {
            $this->markPlanned($record, $actor, $audit, 'Conversion échouée: '.$result->errorOutput());

            return;
        }

        $checksum = hash_file('sha256', $outAbs);
        $record->preservation_status = 'format_migrated';
        $record->save();

        ArchivePreservationEvent::query()->create([
            'record_id' => $record->id,
            'event_type' => 'format_migration_executed',
            'actor_id' => $actor->id,
            'payload' => [
                'target_profile' => $this->targetProfile,
                'status' => 'done',
                'output_disk' => 'archives',
                'output_path' => $outRel,
                'checksum' => $checksum,
                'original_preserved' => true,
            ],
            'occurred_at' => now(),
        ]);

        $audit->log('preservation.format_migration_executed', $record, $actor, [
            'path' => $outRel,
            'checksum' => $checksum,
        ]);
    }

    private function markPlanned(ArchiveRecord $record, User $actor, ArchiveAuditService $audit, string $reason): void
    {
        $record->preservation_status = 'migration_planned';
        $record->save();

        ArchivePreservationEvent::query()->create([
            'record_id' => $record->id,
            'event_type' => 'format_migration',
            'actor_id' => $actor->id,
            'payload' => [
                'target_profile' => $this->targetProfile,
                'status' => 'planned',
                'note' => $reason,
            ],
            'occurred_at' => now(),
        ]);

        $audit->log('preservation.format_migration_planned', $record, $actor, [
            'reason' => $reason,
        ]);
    }

    private function binaryAvailable(string $binary): bool
    {
        $which = Process::run(['where', $binary]);
        if ($which->successful() && trim($which->output()) !== '') {
            return true;
        }

        $version = Process::run([$binary, '--version']);

        return $version->successful();
    }
}
