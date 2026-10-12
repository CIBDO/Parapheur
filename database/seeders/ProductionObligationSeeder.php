<?php

namespace Database\Seeders;

use App\Enums\InstructionKind;
use App\Enums\InstructionStatus;
use App\Enums\ProductionFrequency;
use App\Models\Instruction;
use App\Models\ProductionFolderTemplate;
use App\Models\ProductionObligation;
use App\Models\ProductionObligationActor;
use App\Models\ProductionReminderRule;
use App\Models\Structure;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role as SpatieRole;

class ProductionObligationSeeder extends Seeder
{
    public function run(): void
    {
        $acct = Structure::query()->where('code', 'ACCT')->first()
            ?? Structure::query()->where('name', 'like', '%ACCT%')->first()
            ?? Structure::query()->where('name', 'like', '%Comptabilit%')->first();

        $pgt = Structure::query()->where('code', 'PGT')->first()
            ?? Structure::query()->where('name', 'like', '%PGT%')->first()
            ?? Structure::query()->where('name', 'like', '%trésorerie%')->first();

        $producerAcct = $acct
            ? User::query()->where('structure_id', $acct->id)->where('is_active', true)->orderBy('id')->first()
            : User::query()->where('is_active', true)->orderBy('id')->first();

        $producerPgt = $pgt
            ? User::query()->where('structure_id', $pgt->id)->where('is_active', true)->orderBy('id')->first()
            : $producerAcct;

        $dgRole = SpatieRole::findByName('Directeur Général');
        $dgaRole = SpatieRole::findByName('DGA');

        $acctObligations = [
            ['code' => 'ACCT-SCF', 'label' => 'Situation des comptes financiers'],
            ['code' => 'ACCT-SCB', 'label' => 'Situation des comptes bancaires'],
            ['code' => 'ACCT-PTR', 'label' => 'Plan de trésorerie'],
            ['code' => 'ACCT-RBA', 'label' => 'Relevés bancaires'],
        ];

        $pgtObligations = [
            ['code' => 'PGT-SDU', 'label' => 'Situation des dépenses urgentes'],
            ['code' => 'PGT-SAR', 'label' => 'Situation des anticipations de dépenses et de recettes'],
        ];

        $codes = [];

        foreach ($acctObligations as $item) {
            $codes[] = $item['code'];
            $this->seedObligation($item, $acct?->id, $producerAcct?->id, $dgRole?->name, $dgaRole?->name);
        }

        foreach ($pgtObligations as $item) {
            $codes[] = $item['code'];
            $this->seedObligation($item, $pgt?->id, $producerPgt?->id, $dgRole?->name, $dgaRole?->name);
        }

        ProductionFolderTemplate::query()->updateOrCreate(
            ['code' => 'daily_treasury'],
            [
                'label' => 'Dossier quotidien de trésorerie',
                'obligation_codes' => $codes,
                'frequency' => ProductionFrequency::BusinessDays->value,
                'is_active' => true,
            ]
        );

        $this->seedExamples($acct?->id ?? $pgt?->id, $producerAcct?->id ?? $producerPgt?->id);
    }

    private function seedExamples(?int $structureId, ?int $producerId): void
    {
        $examples = [
            ['code' => 'EX-A', 'label' => 'Rapport hebdomadaire', 'frequency' => ProductionFrequency::Weekly, 'schedule_config' => ['weekday' => Carbon::FRIDAY]],
            ['code' => 'EX-B', 'label' => 'Situation mensuelle', 'frequency' => ProductionFrequency::Monthly, 'schedule_config' => ['day' => 5]],
            ['code' => 'EX-C', 'label' => 'Revue trimestrielle', 'frequency' => ProductionFrequency::Quarterly, 'schedule_config' => ['day' => 10]],
            ['code' => 'EX-D', 'label' => 'Bilan semestriel', 'frequency' => ProductionFrequency::Semiannual, 'schedule_config' => ['day' => 15]],
            ['code' => 'EX-E', 'label' => 'Rapport annuel', 'frequency' => ProductionFrequency::Annual, 'schedule_config' => ['month' => 12, 'day' => 31]],
            ['code' => 'EX-F', 'label' => 'Compte rendu ponctuel', 'frequency' => ProductionFrequency::Once, 'schedule_config' => ['date' => now()->toDateString()]],
            ['code' => 'EX-G', 'label' => 'Production exceptionnelle', 'frequency' => ProductionFrequency::Once, 'schedule_config' => ['date' => now()->addDay()->toDateString()], 'submit_to_parapheur' => true],
            ['code' => 'EX-H', 'label' => 'Point deux fois par mois', 'frequency' => ProductionFrequency::Semimonthly, 'schedule_config' => ['days' => [1, 15]]],
        ];

        $codes = [];
        foreach ($examples as $example) {
            $codes[] = $example['code'];
            ProductionObligation::query()->updateOrCreate(
                ['code' => $example['code']],
                [
                    'label' => $example['label'],
                    'description' => 'Exemple de configuration, modifiable dans le référentiel.',
                    'category' => 'exemple',
                    'structure_id' => $structureId,
                    'producer_id' => $producerId,
                    'frequency' => $example['frequency']->value,
                    'schedule_config' => $example['schedule_config'],
                    'deadline_time' => '10:00:00',
                    'timezone' => 'Africa/Bamako',
                    'confidentiality' => 'normal',
                    'allowed_formats' => ['pdf', 'docx', 'xlsx'],
                    'min_files' => 1,
                    'max_files' => 5,
                    'validation_required' => false,
                    'submit_to_parapheur' => (bool) ($example['submit_to_parapheur'] ?? false),
                    'is_active' => true,
                ]
            );
        }

        ProductionFolderTemplate::query()->updateOrCreate(
            ['code' => 'examples_pack'],
            [
                'label' => 'Dossier des exemples de périodicité',
                'obligation_codes' => $codes,
                'frequency' => ProductionFrequency::Monthly->value,
                'is_active' => true,
            ]
        );

        if ($producerId) {
            Instruction::query()->updateOrCreate(
                ['reference' => 'INS/EXEMPLE/DIRECTIVE'],
                [
                    'kind' => InstructionKind::Directive->value,
                    'title' => 'Directive exemple — suivi des productions',
                    'body' => 'Les directives restent des instructions. Elles ne sont pas des obligations de production.',
                    'issuer_id' => $producerId,
                    'assignee_id' => $producerId,
                    'structure_id' => $structureId,
                    'status' => InstructionStatus::Emise->value,
                    'issued_at' => now(),
                ]
            );
        }
    }

    /**
     * @param  array{code: string, label: string}  $item
     */
    private function seedObligation(
        array $item,
        ?int $structureId,
        ?int $producerId,
        ?string $dgRole,
        ?string $dgaRole,
    ): void {
        $obligation = ProductionObligation::query()->updateOrCreate(
            ['code' => $item['code']],
            [
                'label' => $item['label'],
                'description' => 'Obligation quotidienne de trésorerie — configurable.',
                'category' => 'tresorerie',
                'domain' => 'situations_quotidiennes',
                'structure_id' => $structureId,
                'producer_id' => $producerId,
                'frequency' => ProductionFrequency::BusinessDays->value,
                'schedule_config' => null,
                'deadline_time' => '10:00:00',
                'timezone' => 'Africa/Bamako',
                'confidentiality' => 'restreint',
                'deliverable_type' => 'files',
                'allowed_formats' => ['pdf', 'xlsx', 'docx', 'csv'],
                'min_files' => 1,
                'max_files' => 10,
                'validation_required' => true,
                'validation_mode' => 'sequential',
                'auto_transmit' => false,
                'archive_enabled' => true,
                'is_active' => true,
            ]
        );

        $obligation->actors()->delete();
        $obligation->reminderRules()->delete();

        if ($producerId) {
            ProductionObligationActor::query()->create([
                'production_obligation_id' => $obligation->id,
                'role' => 'validator',
                'user_id' => $producerId,
                'step_order' => 1,
            ]);
        }

        foreach (array_filter([$dgRole, $dgaRole]) as $index => $roleName) {
            ProductionObligationActor::query()->create([
                'production_obligation_id' => $obligation->id,
                'role' => 'recipient',
                'role_name' => $roleName,
                'step_order' => $index + 1,
            ]);
        }

        foreach ([
            ['offset_type' => 'before', 'offset_minutes' => 90, 'target' => 'producer'],
            ['offset_type' => 'before', 'offset_minutes' => 30, 'target' => 'producer'],
            ['offset_type' => 'at_deadline', 'offset_minutes' => 0, 'target' => 'producer', 'escalate' => false],
            ['offset_type' => 'after', 'offset_minutes' => 30, 'target' => 'hierarchy', 'escalate' => true],
        ] as $rule) {
            ProductionReminderRule::query()->create([
                'production_obligation_id' => $obligation->id,
                'offset_type' => $rule['offset_type'],
                'offset_minutes' => $rule['offset_minutes'],
                'target' => $rule['target'],
                'escalate' => (bool) ($rule['escalate'] ?? false),
                'stop_when' => 'transmitted',
                'is_active' => true,
            ]);
        }
    }
}
