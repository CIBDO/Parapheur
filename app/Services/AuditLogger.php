<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function log(string $action, ?Model $auditable = null, array $properties = []): AuditLog
    {
        return AuditLog::query()->create([
            'user_id' => Auth::id(),
            'action' => $action,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'properties' => $properties ?: null,
            'created_at' => now(),
        ]);
    }
}
