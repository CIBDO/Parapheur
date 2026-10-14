<?php

namespace App\Services;

use App\Models\Correspondence;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\File;

class MailSheetPdfService
{
    /**
     * @param  array<string, mixed>  $variables
     */
    public function writeCirculationSheet(array $variables): string
    {
        return $this->write('mail.circulation-sheet', $variables, 'fiche-circulation');
    }

    public function imputationSheet(Correspondence $correspondence, ?User $actor = null): string
    {
        return $this->write('mail.imputation-sheet', $this->imputationPayload($correspondence, $actor), 'fiche-imputation');
    }

    /**
     * @return array<string, mixed>
     */
    public function imputationPayload(Correspondence $correspondence, ?User $actor = null): array
    {
        $correspondence->loadMissing([
            'parties.correspondent',
            'assignments.toUser.structure:id,name',
            'assignments.toStructure:id,name',
            'assignments.fromUser',
            'registeredBy',
            'structure',
        ]);

        $from = $correspondence->parties->first(function ($party) {
            $role = $party->role instanceof \BackedEnum ? $party->role->value : $party->role;

            return $role === 'from';
        });

        $origine = $from?->correspondent?->sigle
            ?: $from?->organization
            ?: $from?->correspondent?->name
            ?: $from?->name
            ?: '';

        $latest = $correspondence->assignments->sortByDesc('id')->first();
        $saisiPar = $actor?->name
            ?: $latest?->fromUser?->name
            ?: $correspondence->registeredBy?->name
            ?: '';

        $imputations = $correspondence->assignments
            ->sortBy('created_at')
            ->map(function ($assignment) {
                $user = $assignment->toUser;
                $prenom = $user?->first_name ?: '';
                $nom = $user?->last_name ?: '';
                if ($prenom === '' && $nom === '' && $user?->name) {
                    $parts = preg_split('/\s+/', trim($user->name), 2) ?: [];
                    $prenom = $parts[0] ?? '';
                    $nom = $parts[1] ?? '';
                }

                return [
                    'date' => $assignment->created_at?->format('d/m/Y') ?? '',
                    'prenom' => $prenom,
                    'nom' => $nom,
                    'service' => $assignment->toStructure?->name ?: $user?->structure?->name ?: '',
                    'annotation' => $assignment->instruction_text ?? '',
                ];
            })
            ->values()
            ->all();

        if ($imputations === []) {
            $imputations[] = ['date' => '', 'prenom' => '', 'nom' => '', 'service' => '', 'annotation' => ''];
        }

        $identity = app(MailIdentityService::class)->forCorrespondence($correspondence);

        return [
            'saisiPar' => $correspondence->registeredBy?->name ?: $saisiPar,
            'structureEntete' => $identity['header'],
            'structureLine' => $identity['structure_line'],
            'ministry' => $identity['ministry'],
            'headerExtra' => $identity['extra'],
            'footer' => $identity['footer'],
            'republic' => $identity['republic'],
            'motto' => $identity['motto'],
            'emblem' => self::emblemPath(),
            'numeroCourrier' => $correspondence->arrival_number ?: $correspondence->departure_number,
            'numeroMinistere' => $correspondence->ministry_number,
            'origine' => $origine,
            'reference' => $correspondence->external_reference,
            'dateCourrier' => $correspondence->correspondence_date?->format('d/m/Y'),
            'dateReception' => $correspondence->received_at?->format('d/m/Y'),
            'objet' => $correspondence->subject,
            'imputations' => $imputations,
        ];
    }

    /**
     * Signataires de la structure émettrice, résolus depuis son identité documentaire.
     *
     * @return array{0: string, 1: string}
     */
    public function signatories(?\App\Models\Structure $structure): array
    {
        $identity = app(MailIdentityService::class)->forStructure($structure);

        return [$identity['signatory_left'], $identity['signatory_right']];
    }

    public static function safeFilename(string $name): string
    {
        return str_replace(['/', '\\', ':', '*', '?', '"', '<', '>', '|'], '-', $name);
    }

    public static function emblemPath(): ?string
    {
        $path = public_path('images/embleme-mali.png');

        return is_file($path) ? str_replace('\\', '/', $path) : null;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function write(string $view, array $data, string $prefix): string
    {
        $dir = storage_path('app/temp');
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0755, true);
        }

        $path = $dir.'/'.uniqid($prefix.'_', true).'.pdf';
        Pdf::loadView($view, $data)->setPaper('a4')->save($path);

        return $path;
    }
}
