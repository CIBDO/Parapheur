<?php

namespace App\Services;

use App\Models\Correspondence;
use App\Models\Structure;
use App\Models\StructureDocumentProfile;
use App\Models\StructurePosition;
use App\Models\StructureTypeDocumentProfile;
use App\Models\User;
use App\Models\UserStructureAssignment;

class MailIdentityService
{
    /**
     * @return array<string, mixed>
     */
    public function forCorrespondence(Correspondence $correspondence): array
    {
        if ($correspondence->letterhead_frozen_at && is_array($correspondence->letterhead_snapshot)) {
            return $correspondence->letterhead_snapshot + [
                'structure_line' => null,
                'ministry' => null,
                'extra' => null,
                'footer' => null,
                'republic' => null,
                'motto' => null,
            ];
        }

        $correspondence->loadMissing('structure');

        return $this->forStructure($correspondence->structure);
    }

    public function freeze(Correspondence $correspondence, bool $force = false): Correspondence
    {
        if ($correspondence->letterhead_frozen_at && ! $force) {
            return $correspondence;
        }

        $correspondence->loadMissing('structure');
        $identity = $this->forStructure($correspondence->structure);

        $correspondence->forceFill([
            'letterhead_snapshot' => $identity,
            'signatory_left' => $identity['signatory_left'],
            'signatory_right' => $identity['signatory_right'],
            'signatory_user_id' => $identity['signatory_user_id'],
            'signatory_function' => $identity['signatory_function'],
            'signatory_capacity' => $identity['signatory_capacity'],
            'letterhead_frozen_at' => now(),
        ])->save();

        return $correspondence;
    }

    /**
     * @return array<string, mixed>
     */
    public function forStructure(?Structure $structure): array
    {
        $boundary = $this->boundary($structure);
        $boundary?->loadMissing('type');
        $typeProfile = $boundary?->structure_type_id
            ? StructureTypeDocumentProfile::query()->where('structure_type_id', $boundary->structure_type_id)->first()
            : null;
        $profile = $boundary
            ? StructureDocumentProfile::query()->where('structure_id', $boundary->id)->first()
            : null;

        $signatories = $this->signatories($boundary, $profile, $typeProfile);
        [$header, $structureLine] = $this->headerLines($boundary, $typeProfile, $profile);

        return [
            'ministry' => $typeProfile?->ministry_line ?: $profile?->ministry_line ?: 'MINISTÈRE DE L\'ÉCONOMIE ET DES FINANCES.',
            'header' => $header,
            'structure_line' => $structureLine,
            'footer' => $profile?->footer_text ?: $typeProfile?->footer_text,
            'extra' => $this->extraLine($profile) ?: $this->extraLineFrom(
                $typeProfile?->address,
                $typeProfile?->phone,
                $typeProfile?->email,
            ),
            'republic' => $typeProfile?->republic_line ?: $profile?->republic_line ?: 'République du Mali',
            'motto' => $typeProfile?->motto ?: $profile?->motto ?: 'Un Peuple – Un But – Une Foi',
            'signatory_left' => $signatories['left'],
            'signatory_right' => $signatories['right'],
            'signatory_user_id' => $signatories['user_id'],
            'signatory_function' => $signatories['function'],
            'signatory_capacity' => $signatories['capacity'],
            'structure_id' => $boundary?->id,
            'structure_code' => $boundary?->code,
        ];
    }

    private function boundary(?Structure $structure): ?Structure
    {
        if (! $structure) {
            return null;
        }

        if ($structure->boundary_structure_id && (int) $structure->boundary_structure_id !== (int) $structure->id) {
            return Structure::query()->find($structure->boundary_structure_id) ?? $structure;
        }

        return $structure;
    }

    /**
     * @return array{left: string, right: string, user_id: ?int, function: ?string, capacity: ?string}
     */
    private function signatories(?Structure $boundary, ?StructureDocumentProfile $profile, ?StructureTypeDocumentProfile $typeProfile): array
    {
        if ($this->hasLabel($profile?->signatory_left_label) || $this->hasLabel($profile?->signatory_right_label)) {
            return $this->configuredLabels($profile?->signatory_left_label, $profile?->signatory_right_label);
        }

        if ($this->hasLabel($typeProfile?->signatory_left_label) || $this->hasLabel($typeProfile?->signatory_right_label)) {
            return $this->configuredLabels($typeProfile?->signatory_left_label, $typeProfile?->signatory_right_label);
        }

        $fromPositions = $boundary ? $this->fromPositions($boundary) : [];

        if (count($fromPositions) >= 1) {
            return [
                'left' => $fromPositions[0]['label'],
                'right' => $fromPositions[1]['label'] ?? ($profile?->signatory_right_label ?: 'Le responsable'),
                'user_id' => $fromPositions[0]['user_id'] ?? ($fromPositions[1]['user_id'] ?? null),
                'function' => $fromPositions[1]['function'] ?? $fromPositions[0]['function'],
                'capacity' => $fromPositions[1]['capacity'] ?? $fromPositions[0]['capacity'],
            ];
        }

        $fromUsers = $boundary ? $this->fromUsers($boundary) : [];
        if (count($fromUsers) >= 1) {
            return [
                'left' => $fromUsers[0]['label'],
                'right' => $fromUsers[1]['label'] ?? ($profile?->signatory_right_label ?: ($boundary?->name ?: 'Le responsable')),
                'user_id' => $fromUsers[0]['user_id'],
                'function' => $fromUsers[0]['function'],
                'capacity' => $fromUsers[0]['capacity'],
            ];
        }

        $name = $boundary?->name ?: 'la structure';

        return [
            'left' => $profile?->signatory_left_label ?: 'Le responsable',
            'right' => $profile?->signatory_right_label ?: $name,
            'user_id' => null,
            'function' => $profile?->signatory_right_label ?: 'Responsable',
            'capacity' => $name,
        ];
    }

    /**
     * @return list<array{label: string, user_id: ?int, function: string, capacity: string}>
     */
    private function fromPositions(Structure $boundary): array
    {
        $positions = StructurePosition::query()
            ->where('structure_id', $boundary->id)
            ->where('can_sign', true)
            ->where('is_active', true)
            ->orderBy('id')
            ->limit(2)
            ->get();

        $labels = [];
        foreach ($positions as $position) {
            $holder = UserStructureAssignment::query()
                ->with('user:id,name')
                ->where('structure_position_id', $position->id)
                ->where('status', 'active')
                ->where(function ($query) {
                    $query->whereNull('starts_on')->orWhereDate('starts_on', '<=', now()->toDateString());
                })
                ->where(function ($query) {
                    $query->whereNull('ends_on')->orWhereDate('ends_on', '>=', now()->toDateString());
                })
                ->first();

            $function = $position->name;
            $name = $holder?->user?->name;
            $labels[] = [
                'label' => $name ? $name.' — '.$function : $function,
                'user_id' => $holder?->user_id,
                'function' => $function,
                'capacity' => $function,
            ];
        }

        return $labels;
    }

    /**
     * @return list<array{label: string, user_id: int, function: string, capacity: string}>
     */
    private function fromUsers(Structure $boundary): array
    {
        return User::query()
            ->where('structure_id', $boundary->id)
            ->where('is_active', true)
            ->whereNotNull('position_title')
            ->where('position_title', '!=', '')
            ->orderBy('id')
            ->limit(2)
            ->get(['id', 'name', 'position_title'])
            ->map(fn (User $user) => [
                'label' => $user->name.' — '.$user->position_title,
                'user_id' => $user->id,
                'function' => $user->position_title,
                'capacity' => $user->position_title,
            ])
            ->all();
    }

    /**
     * @return array{0: string, 1: ?string}
     */
    private function headerLines(?Structure $boundary, ?StructureTypeDocumentProfile $typeProfile, ?StructureDocumentProfile $profile): array
    {
        $structureName = $boundary?->name;
        $typeTitle = trim((string) ($typeProfile?->header_title ?: $profile?->header_title ?: ''));

        if ($typeTitle !== '') {
            $append = $typeProfile?->append_structure_name ?? true;

            return [
                mb_strtoupper($typeTitle, 'UTF-8'),
                $append && $structureName ? mb_strtoupper($structureName, 'UTF-8') : null,
            ];
        }

        return [
            mb_strtoupper($structureName ?: 'Direction Générale du Trésor et de la Comptabilité Publique', 'UTF-8'),
            null,
        ];
    }

    /**
     * @return array{left: string, right: string, user_id: null, function: string, capacity: string}
     */
    private function configuredLabels(?string $left, ?string $right): array
    {
        $leftLabel = trim((string) $left) !== '' ? trim((string) $left) : '—';
        $rightLabel = trim((string) $right) !== '' ? trim((string) $right) : '—';

        return [
            'left' => $leftLabel,
            'right' => $rightLabel,
            'user_id' => null,
            'function' => $rightLabel,
            'capacity' => $rightLabel,
        ];
    }

    private function hasLabel(?string $value): bool
    {
        return trim((string) $value) !== '';
    }

    private function extraLine(?StructureDocumentProfile $profile): ?string
    {
        if (! $profile) {
            return null;
        }

        return $this->extraLineFrom($profile->address, $profile->phone, $profile->email);
    }

    private function extraLineFrom(?string $address, ?string $phone, ?string $email): ?string
    {
        $parts = array_filter([
            $address,
            $phone ? 'Tél. '.$phone : null,
            $email,
        ]);

        return $parts === [] ? null : implode(' · ', $parts);
    }
}
