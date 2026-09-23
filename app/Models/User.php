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
        'must_change_password',
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
            'must_change_password' => 'boolean',
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
                ['action' => 'manage', 'subject' => 'AppointmentType'],
                ['action' => 'manage', 'subject' => 'Ged'],
                ['action' => 'manage', 'subject' => 'GedAdmin'],
                ['action' => 'manage', 'subject' => 'Workspace'],
                ['action' => 'manage', 'subject' => 'WorkspaceAdmin'],
                ['action' => 'manage', 'subject' => 'Library'],
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

        if ($this->can('instructions.manage') || $this->can('instruction.view') || $this->can('instruction.create')) {
            $rules[] = ['action' => 'manage', 'subject' => 'Instruction'];
            $rules[] = ['action' => 'read', 'subject' => 'Instruction'];
            $rules[] = ['action' => 'create', 'subject' => 'Instruction'];
        }

        if ($this->can('task.view') || $this->can('task.view_all') || $this->can('task.view_team') || $this->can('task.manage')) {
            $rules[] = ['action' => 'read', 'subject' => 'Task'];
            $rules[] = ['action' => 'read', 'subject' => 'MyWork'];
        }

        if ($this->can('task.create') || $this->can('task.manage')) {
            $rules[] = ['action' => 'create', 'subject' => 'Task'];
        }

        if ($this->can('task.update') || $this->can('task.assign') || $this->can('task.take_charge') || $this->can('task.manage')) {
            $rules[] = ['action' => 'update', 'subject' => 'Task'];
            $rules[] = ['action' => 'manage', 'subject' => 'Task'];
        }

        if ($this->can('task.validate') || $this->can('task.manage')) {
            $rules[] = ['action' => 'validate', 'subject' => 'Task'];
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

        if ($this->can('appointments.view') || $this->can('appointments.create') || $this->can('appointments.manage_requests') || $this->can('appointments.validate')) {
            $rules[] = ['action' => 'read', 'subject' => 'Appointment'];
        }

        if ($this->can('appointments.create') || $this->can('appointments.manage_requests')) {
            $rules[] = ['action' => 'create', 'subject' => 'Appointment'];
        }

        if ($this->can('appointments.manage_requests') || $this->can('appointments.manage_calendar')) {
            $rules[] = ['action' => 'manage', 'subject' => 'Appointment'];
        }

        if ($this->can('appointments.validate')) {
            $rules[] = ['action' => 'validate', 'subject' => 'Appointment'];
        }

        if ($this->can('reporting.view')) {
            $rules[] = ['action' => 'read', 'subject' => 'Reporting'];
        }

        if ($this->can('delegations.manage')) {
            $rules[] = ['action' => 'manage', 'subject' => 'Delegation'];
            $rules[] = ['action' => 'read', 'subject' => 'DashboardDg'];
        }

        if ($this->can('ged.view') || $this->can('ged.search')) {
            $rules[] = ['action' => 'read', 'subject' => 'Ged'];
            $rules[] = ['action' => 'read', 'subject' => 'Document'];
        }

        if ($this->can('ged.create')) {
            $rules[] = ['action' => 'create', 'subject' => 'Ged'];
            $rules[] = ['action' => 'create', 'subject' => 'Document'];
        }

        if ($this->can('ged.update') || $this->can('ged.classify') || $this->can('ged.archive')) {
            $rules[] = ['action' => 'update', 'subject' => 'Ged'];
            $rules[] = ['action' => 'manage', 'subject' => 'Ged'];
        }

        if ($this->can('ged.manage_classification') || $this->can('ged.manage_categories') || $this->can('ged.manage_types')) {
            $rules[] = ['action' => 'manage', 'subject' => 'GedAdmin'];
        }

        if ($this->can('workspace.access')) {
            $rules[] = ['action' => 'read', 'subject' => 'Workspace'];
        }

        if ($this->can('workspace.create_shared') || $this->can('workspace.manage_own')) {
            $rules[] = ['action' => 'create', 'subject' => 'Workspace'];
            $rules[] = ['action' => 'manage', 'subject' => 'Workspace'];
        }

        if ($this->can('workspace.manage_quotas')) {
            $rules[] = ['action' => 'manage', 'subject' => 'WorkspaceAdmin'];
        }

        if ($this->can('library.access')) {
            $rules[] = ['action' => 'read', 'subject' => 'Library'];
        }

        if ($this->can('library.manage_own')) {
            $rules[] = ['action' => 'manage', 'subject' => 'Library'];
            $rules[] = ['action' => 'create', 'subject' => 'Library'];
        }

        if ($this->can('library.moderate')) {
            $rules[] = ['action' => 'manage', 'subject' => 'Library'];
            $rules[] = ['action' => 'manage', 'subject' => 'WorkspaceAdmin'];
        }

        if ($this->can('mail.view') || $this->can('mail.view_all')) {
            $rules[] = ['action' => 'read', 'subject' => 'Courrier'];
        }

        if ($this->can('mail.create')) {
            $rules[] = ['action' => 'create', 'subject' => 'Courrier'];
        }

        if ($this->can('mail.update') || $this->can('mail.assign') || $this->can('mail.process')) {
            $rules[] = ['action' => 'update', 'subject' => 'Courrier'];
            $rules[] = ['action' => 'manage', 'subject' => 'Courrier'];
        }

        if ($this->can('mail.view_all') || $this->can('mail.assign') || $this->can('mail.delete')) {
            $rules[] = ['action' => 'manage', 'subject' => 'CourrierAdmin'];
        }

        if ($this->can('document_template.view') || $this->can('document_template.view_all')) {
            $rules[] = ['action' => 'read', 'subject' => 'DocumentTemplate'];
        }

        if ($this->can('document_template.create') || $this->can('document_template.update')) {
            $rules[] = ['action' => 'manage', 'subject' => 'DocumentTemplate'];
        }

        if ($this->can('ticket.view') || $this->can('ticket.view_all') || $this->can('ticket.view_team')) {
            $rules[] = ['action' => 'read', 'subject' => 'Ticketing'];
        }

        if ($this->can('ticket.create')) {
            $rules[] = ['action' => 'create', 'subject' => 'Ticketing'];
        }

        if ($this->can('ticket.update') || $this->can('ticket.assign') || $this->can('ticket.take_charge') || $this->can('ticket.resolve')) {
            $rules[] = ['action' => 'update', 'subject' => 'Ticketing'];
            $rules[] = ['action' => 'manage', 'subject' => 'Ticketing'];
        }

        if ($this->can('ticket.admin') || $this->can('ticket.manage_catalog') || $this->can('ticket.manage_sla')) {
            $rules[] = ['action' => 'manage', 'subject' => 'TicketingAdmin'];
        }

        return $rules;
    }
}
