<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'name',
        'first_name',
        'last_name',
        'title',
        'phone',
        'email',
        'password',
        'is_active',
        'structure_id',
        'position_title',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function structure(): BelongsTo
    {
        return $this->belongsTo(Structure::class);
    }

    public function authoredDocuments(): HasMany
    {
        return $this->hasMany(Document::class, 'author_id');
    }

    public function transmissionsReceived(): HasMany
    {
        return $this->hasMany(DocumentTransmission::class, 'to_user_id');
    }

    public function meetingParticipations(): HasMany
    {
        return $this->hasMany(MeetingParticipant::class);
    }

    public function abilityRules(): array
    {
        if ($this->can('admin.access')) {
            return [
                ['action' => 'manage', 'subject' => 'all'],
                ['action' => 'create', 'subject' => 'Document'],
                ['action' => 'manage', 'subject' => 'Structure'],
                ['action' => 'manage', 'subject' => 'User'],
                ['action' => 'manage', 'subject' => 'Role'],
                ['action' => 'manage', 'subject' => 'DocumentType'],
                ['action' => 'manage', 'subject' => 'MeetingType'],
                ['action' => 'manage', 'subject' => 'MeetingTemplate'],
                ['action' => 'read', 'subject' => 'AuditLog'],
                ['action' => 'read', 'subject' => 'Notification'],
                ['action' => 'read', 'subject' => 'Parapheur'],
            ];
        }

        $rules = [
            ['action' => 'read', 'subject' => 'Parapheur'],
            ['action' => 'read', 'subject' => 'Auth'],
            ['action' => 'read', 'subject' => 'Document'],
            ['action' => 'read', 'subject' => 'Notification'],
        ];

        // Tous les profils métier peuvent créer (permission documents.create)
        if ($this->can('documents.create')) {
            $rules[] = ['action' => 'create', 'subject' => 'Document'];
        }

        if ($this->can('documents.act')) {
            $rules[] = ['action' => 'update', 'subject' => 'Document'];
            $rules[] = ['action' => 'manage', 'subject' => 'Document'];
            $rules[] = ['action' => 'act', 'subject' => 'Document'];
        }

        if ($this->can('documents.vise')) {
            $rules[] = ['action' => 'vise', 'subject' => 'Document'];
        }

        if ($this->can('documents.validate')) {
            $rules[] = ['action' => 'validate', 'subject' => 'Document'];
        }

        if ($this->can('dashboard.dg')) {
            $rules[] = ['action' => 'read', 'subject' => 'DashboardDg'];
        }

        if ($this->can('dashboard.direction')) {
            $rules[] = ['action' => 'read', 'subject' => 'DashboardDirection'];
        }

        if ($this->can('instructions.manage')) {
            $rules[] = ['action' => 'manage', 'subject' => 'Instruction'];
            $rules[] = ['action' => 'read', 'subject' => 'Instruction'];
        }

        if ($this->can('meetings.view') || $this->can('meetings.manage') || $this->can('meetings.create')) {
            $rules[] = ['action' => 'read', 'subject' => 'Meeting'];
        }

        if ($this->can('meetings.create') || $this->can('meetings.manage')) {
            $rules[] = ['action' => 'create', 'subject' => 'Meeting'];
        }

        if ($this->can('meetings.manage')) {
            $rules[] = ['action' => 'manage', 'subject' => 'Meeting'];
        }

        if ($this->can('reporting.view')) {
            $rules[] = ['action' => 'read', 'subject' => 'Reporting'];
        }

        if ($this->can('delegations.manage')) {
            $rules[] = ['action' => 'manage', 'subject' => 'Delegation'];
            $rules[] = ['action' => 'read', 'subject' => 'DashboardDg'];
        }

        return $rules;
    }
}
