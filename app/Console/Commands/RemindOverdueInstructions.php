<?php

namespace App\Console\Commands;

use App\Models\Instruction;
use App\Notifications\InstructionReminderNotification;
use App\Services\AuditLogger;
use Illuminate\Console\Command;

class RemindOverdueInstructions extends Command
{
    protected $signature = 'parapheur:remind-overdue-instructions';

    protected $description = 'Relance les responsables des instructions en retard';

    public function handle(AuditLogger $audit): int
    {
        $query = Instruction::query()
            ->with(['assignee', 'issuer'])
            ->whereIn('status', ['a_faire', 'en_cours'])
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString())
            ->where(function ($q) {
                $q->whereNull('last_reminded_at')
                    ->orWhereDate('last_reminded_at', '<', now()->toDateString());
            });

        $count = 0;

        $query->each(function (Instruction $instruction) use (&$count, $audit) {
            if ($instruction->assignee) {
                $instruction->assignee->notify(new InstructionReminderNotification($instruction));
            }

            if ($instruction->issuer && (int) $instruction->issuer_id !== (int) $instruction->assignee_id) {
                $instruction->issuer->notify(new InstructionReminderNotification($instruction));
            }

            $instruction->forceFill(['last_reminded_at' => now()])->save();
            $audit->log('instruction.reminded', $instruction);
            $count++;
        });

        $this->info("Relances envoyées : {$count}");

        return self::SUCCESS;
    }
}
