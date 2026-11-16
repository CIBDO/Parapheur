<?php

namespace App\Support\Rbac;

use App\Models\User;

/**
 * Transforme les permissions Spatie en règles CASL pour le frontend.
 * Les expansions explicites couvrent les cas non 1:1 (plusieurs subjects / any-of).
 */
class AbilityMapper
{
    /**
     * @return list<array{action: string, subject: string}>
     */
    public function forUser(User $user): array
    {
        if ($user->can('admin.access')) {
            return $this->adminRules();
        }

        $rules = $this->baseRules();

        foreach ($this->expansions() as $permission => $granted) {
            if (! $user->can($permission)) {
                continue;
            }
            foreach ($granted as $rule) {
                $rules[] = $rule;
            }
        }

        foreach ($this->anyOfExpansions() as $group) {
            foreach ($group['permissions'] as $permission) {
                if ($user->can($permission)) {
                    foreach ($group['rules'] as $rule) {
                        $rules[] = $rule;
                    }
                    break;
                }
            }
        }

        return $this->unique($rules);
    }

    /**
     * @return list<array{action: string, subject: string}>
     */
    private function adminRules(): array
    {
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
            ['action' => 'read', 'subject' => 'Task'],
            ['action' => 'read', 'subject' => 'TaskTeam'],
            ['action' => 'read', 'subject' => 'MyWork'],
        ];
    }

    /**
     * @return list<array{action: string, subject: string}>
     */
    private function baseRules(): array
    {
        return [
            ['action' => 'read', 'subject' => 'Parapheur'],
            ['action' => 'read', 'subject' => 'Auth'],
            ['action' => 'read', 'subject' => 'Document'],
            ['action' => 'read', 'subject' => 'Notification'],
        ];
    }

    /**
     * Permission unique → règles CASL.
     *
     * @return array<string, list<array{action: string, subject: string}>>
     */
    private function expansions(): array
    {
        return [
            'documents.create' => [
                ['action' => 'create', 'subject' => 'Document'],
            ],
            'documents.act' => [
                ['action' => 'update', 'subject' => 'Document'],
                ['action' => 'manage', 'subject' => 'Document'],
                ['action' => 'act', 'subject' => 'Document'],
            ],
            'documents.vise' => [
                ['action' => 'vise', 'subject' => 'Document'],
            ],
            'documents.validate' => [
                ['action' => 'validate', 'subject' => 'Document'],
            ],
            'dashboard.dg' => [
                ['action' => 'read', 'subject' => 'DashboardDg'],
            ],
            'dashboard.direction' => [
                ['action' => 'read', 'subject' => 'DashboardDirection'],
            ],
            'task.create' => [
                ['action' => 'create', 'subject' => 'Task'],
            ],
            'task.manage' => [
                ['action' => 'create', 'subject' => 'Task'],
                ['action' => 'update', 'subject' => 'Task'],
                ['action' => 'manage', 'subject' => 'Task'],
                ['action' => 'validate', 'subject' => 'Task'],
            ],
            'task.update' => [
                ['action' => 'update', 'subject' => 'Task'],
                ['action' => 'manage', 'subject' => 'Task'],
            ],
            'task.assign' => [
                ['action' => 'update', 'subject' => 'Task'],
                ['action' => 'manage', 'subject' => 'Task'],
            ],
            'task.take_charge' => [
                ['action' => 'update', 'subject' => 'Task'],
                ['action' => 'manage', 'subject' => 'Task'],
            ],
            'task.validate' => [
                ['action' => 'validate', 'subject' => 'Task'],
            ],
            'production.manage' => [
                ['action' => 'manage', 'subject' => 'Production'],
                ['action' => 'create', 'subject' => 'Production'],
                ['action' => 'update', 'subject' => 'Production'],
                ['action' => 'validate', 'subject' => 'Production'],
                ['action' => 'transmit', 'subject' => 'Production'],
                ['action' => 'pilot', 'subject' => 'Production'],
            ],
            'production.validate' => [
                ['action' => 'validate', 'subject' => 'Production'],
            ],
            'production.transmit' => [
                ['action' => 'transmit', 'subject' => 'Production'],
            ],
            'announcement.view' => [
                ['action' => 'read', 'subject' => 'Announcement'],
            ],
            'announcement.create' => [
                ['action' => 'create', 'subject' => 'Announcement'],
                ['action' => 'update', 'subject' => 'Announcement'],
            ],
            'announcement.update' => [
                ['action' => 'create', 'subject' => 'Announcement'],
                ['action' => 'update', 'subject' => 'Announcement'],
            ],
            'announcement.approve' => [
                ['action' => 'validate', 'subject' => 'Announcement'],
            ],
            'announcement.publish' => [
                ['action' => 'validate', 'subject' => 'Announcement'],
            ],
            'announcement.manage' => [
                ['action' => 'manage', 'subject' => 'Announcement'],
                ['action' => 'read', 'subject' => 'AnnouncementStats'],
            ],
            'announcement.manage_categories' => [
                ['action' => 'manage', 'subject' => 'Announcement'],
            ],
            'announcement.view_statistics' => [
                ['action' => 'read', 'subject' => 'AnnouncementStats'],
            ],
            'meetings.create' => [
                ['action' => 'create', 'subject' => 'Meeting'],
            ],
            'meetings.manage' => [
                ['action' => 'create', 'subject' => 'Meeting'],
                ['action' => 'manage', 'subject' => 'Meeting'],
            ],
            'appointments.create' => [
                ['action' => 'create', 'subject' => 'Appointment'],
            ],
            'appointments.manage_requests' => [
                ['action' => 'create', 'subject' => 'Appointment'],
                ['action' => 'manage', 'subject' => 'Appointment'],
            ],
            'appointments.manage_calendar' => [
                ['action' => 'manage', 'subject' => 'Appointment'],
            ],
            'appointments.validate' => [
                ['action' => 'validate', 'subject' => 'Appointment'],
            ],
            'reporting.view' => [
                ['action' => 'read', 'subject' => 'Reporting'],
            ],
            'delegations.manage' => [
                ['action' => 'manage', 'subject' => 'Delegation'],
                ['action' => 'read', 'subject' => 'DashboardDg'],
            ],
            'ged.create' => [
                ['action' => 'create', 'subject' => 'Ged'],
                ['action' => 'create', 'subject' => 'Document'],
            ],
            'ged.update' => [
                ['action' => 'update', 'subject' => 'Ged'],
                ['action' => 'manage', 'subject' => 'Ged'],
            ],
            'ged.classify' => [
                ['action' => 'update', 'subject' => 'Ged'],
                ['action' => 'manage', 'subject' => 'Ged'],
            ],
            'ged.archive' => [
                ['action' => 'update', 'subject' => 'Ged'],
                ['action' => 'manage', 'subject' => 'Ged'],
            ],
            'ged.validate' => [
                ['action' => 'validate', 'subject' => 'Ged'],
                ['action' => 'update', 'subject' => 'Ged'],
            ],
            'ged.publish' => [
                ['action' => 'validate', 'subject' => 'Ged'],
                ['action' => 'update', 'subject' => 'Ged'],
            ],
            'workspace.create_shared' => [
                ['action' => 'create', 'subject' => 'Workspace'],
                ['action' => 'manage', 'subject' => 'Workspace'],
            ],
            'workspace.manage_own' => [
                ['action' => 'create', 'subject' => 'Workspace'],
                ['action' => 'manage', 'subject' => 'Workspace'],
            ],
            'workspace.manage_quotas' => [
                ['action' => 'manage', 'subject' => 'WorkspaceAdmin'],
            ],
            'library.manage_own' => [
                ['action' => 'manage', 'subject' => 'Library'],
                ['action' => 'create', 'subject' => 'Library'],
            ],
            'library.moderate' => [
                ['action' => 'manage', 'subject' => 'Library'],
                ['action' => 'manage', 'subject' => 'WorkspaceAdmin'],
            ],
            'mail.create' => [
                ['action' => 'create', 'subject' => 'Courrier'],
            ],
            'mail.update' => [
                ['action' => 'update', 'subject' => 'Courrier'],
                ['action' => 'manage', 'subject' => 'Courrier'],
            ],
            'mail.assign' => [
                ['action' => 'update', 'subject' => 'Courrier'],
                ['action' => 'manage', 'subject' => 'Courrier'],
                ['action' => 'manage', 'subject' => 'CourrierAdmin'],
            ],
            'mail.process' => [
                ['action' => 'update', 'subject' => 'Courrier'],
                ['action' => 'manage', 'subject' => 'Courrier'],
            ],
            'mail.view_all' => [
                ['action' => 'manage', 'subject' => 'CourrierAdmin'],
            ],
            'mail.delete' => [
                ['action' => 'manage', 'subject' => 'CourrierAdmin'],
            ],
            'mail.admin' => [
                ['action' => 'manage', 'subject' => 'MailSettings'],
            ],
            'document_template.create' => [
                ['action' => 'manage', 'subject' => 'DocumentTemplate'],
            ],
            'document_template.update' => [
                ['action' => 'manage', 'subject' => 'DocumentTemplate'],
            ],
            'ticket.create' => [
                ['action' => 'create', 'subject' => 'Ticketing'],
            ],
            'ticket.update' => [
                ['action' => 'update', 'subject' => 'Ticketing'],
                ['action' => 'manage', 'subject' => 'Ticketing'],
            ],
            'ticket.assign' => [
                ['action' => 'update', 'subject' => 'Ticketing'],
                ['action' => 'manage', 'subject' => 'Ticketing'],
            ],
            'ticket.take_charge' => [
                ['action' => 'update', 'subject' => 'Ticketing'],
                ['action' => 'manage', 'subject' => 'Ticketing'],
            ],
            'ticket.resolve' => [
                ['action' => 'update', 'subject' => 'Ticketing'],
                ['action' => 'manage', 'subject' => 'Ticketing'],
            ],
            'ticket.admin' => [
                ['action' => 'manage', 'subject' => 'TicketingAdmin'],
            ],
            'ticket.manage_catalog' => [
                ['action' => 'manage', 'subject' => 'TicketingAdmin'],
            ],
            'ticket.manage_sla' => [
                ['action' => 'manage', 'subject' => 'TicketingAdmin'],
            ],
            'portal_app.manage' => [
                ['action' => 'manage', 'subject' => 'PortalApp'],
                ['action' => 'create', 'subject' => 'PortalApp'],
                ['action' => 'update', 'subject' => 'PortalApp'],
                ['action' => 'delete', 'subject' => 'PortalApp'],
            ],
        ];
    }

    /**
     * Au moins une permission du groupe → règles.
     *
     * @return list<array{permissions: list<string>, rules: list<array{action: string, subject: string}>}>
     */
    private function anyOfExpansions(): array
    {
        return [
            [
                'permissions' => ['instructions.manage', 'instruction.view', 'instruction.create'],
                'rules' => [
                    ['action' => 'manage', 'subject' => 'Instruction'],
                    ['action' => 'read', 'subject' => 'Instruction'],
                    ['action' => 'create', 'subject' => 'Instruction'],
                ],
            ],
            [
                'permissions' => ['task.view', 'task.view_all', 'task.view_team', 'task.manage'],
                'rules' => [
                    ['action' => 'read', 'subject' => 'Task'],
                    ['action' => 'read', 'subject' => 'MyWork'],
                ],
            ],
            [
                'permissions' => ['task.view_team', 'task.view_all', 'task.manage'],
                'rules' => [
                    ['action' => 'read', 'subject' => 'TaskTeam'],
                ],
            ],
            [
                'permissions' => ['production.view', 'production.view_all', 'production.manage'],
                'rules' => [
                    ['action' => 'read', 'subject' => 'Production'],
                    ['action' => 'read', 'subject' => 'MyWork'],
                ],
            ],
            [
                'permissions' => ['production.view_all', 'production.manage', 'dashboard.dg'],
                'rules' => [
                    ['action' => 'pilot', 'subject' => 'Production'],
                ],
            ],
            [
                'permissions' => ['meetings.view', 'meetings.manage', 'meetings.create'],
                'rules' => [
                    ['action' => 'read', 'subject' => 'Meeting'],
                ],
            ],
            [
                'permissions' => ['appointments.view', 'appointments.create', 'appointments.manage_requests', 'appointments.validate'],
                'rules' => [
                    ['action' => 'read', 'subject' => 'Appointment'],
                ],
            ],
            [
                'permissions' => ['ged.view', 'ged.search'],
                'rules' => [
                    ['action' => 'read', 'subject' => 'Ged'],
                    ['action' => 'read', 'subject' => 'Document'],
                ],
            ],
            [
                'permissions' => [
                    'ged.manage_classification', 'ged.manage_categories', 'ged.manage_types',
                    'ged.manage_domains', 'ged.manage_folders', 'ged.manage_workflows',
                ],
                'rules' => [
                    ['action' => 'manage', 'subject' => 'GedAdmin'],
                ],
            ],
            [
                'permissions' => ['workspace.access'],
                'rules' => [
                    ['action' => 'read', 'subject' => 'Workspace'],
                ],
            ],
            [
                'permissions' => ['library.access'],
                'rules' => [
                    ['action' => 'read', 'subject' => 'Library'],
                ],
            ],
            [
                'permissions' => ['mail.view', 'mail.view_all'],
                'rules' => [
                    ['action' => 'read', 'subject' => 'Courrier'],
                ],
            ],
            [
                'permissions' => ['document_template.view', 'document_template.view_all'],
                'rules' => [
                    ['action' => 'read', 'subject' => 'DocumentTemplate'],
                ],
            ],
            [
                'permissions' => ['ticket.view', 'ticket.view_all', 'ticket.view_team'],
                'rules' => [
                    ['action' => 'read', 'subject' => 'Ticketing'],
                ],
            ],
            [
                'permissions' => ['portal_app.view', 'portal_app.manage'],
                'rules' => [
                    ['action' => 'read', 'subject' => 'PortalApp'],
                ],
            ],
        ];
    }

    /**
     * @param  list<array{action: string, subject: string}>  $rules
     * @return list<array{action: string, subject: string}>
     */
    private function unique(array $rules): array
    {
        $seen = [];
        $out = [];

        foreach ($rules as $rule) {
            $key = $rule['action'].'|'.$rule['subject'];
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $out[] = $rule;
        }

        return $out;
    }
}
