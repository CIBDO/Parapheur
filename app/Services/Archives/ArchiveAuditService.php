<?php

namespace App\Services\Archives;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Model;

class ArchiveAuditService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $meta
     */
    public function log(string $action, ?Model $subject, User $actor, array $meta = []): void
    {
        $this->audit->log('archives.'.$action, $subject, array_merge($meta, [
            'actor_id' => $actor->id,
        ]));
    }
}
