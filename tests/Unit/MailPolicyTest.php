<?php

namespace Tests\Unit;

use App\Notifications\DocumentWorkflowNotification;
use App\Notifications\MailCorrespondenceNotification;
use App\Notifications\TicketNotification;
use App\Services\Tasks\TaskNotificationService;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class MailPolicyTest extends TestCase
{
    public function test_task_mail_events_exclude_mention(): void
    {
        $this->assertTrue(TaskNotificationService::sendsMail('returned'));
        $this->assertTrue(TaskNotificationService::sendsMail('validation_requested'));
        $this->assertFalse(TaskNotificationService::sendsMail('mention'));
        $this->assertFalse(TaskNotificationService::sendsMail('comment'));
        $this->assertFalse(TaskNotificationService::sendsMail('taken_charge'));
    }

    public function test_ticket_mail_events_keep_action_only(): void
    {
        $this->assertTrue(TicketNotification::sendsMail('assigned'));
        $this->assertTrue(TicketNotification::sendsMail('sla_breach'));
        $this->assertFalse(TicketNotification::sendsMail('taken_charge'));
        $this->assertFalse(TicketNotification::sendsMail('comment_agent'));
        $this->assertFalse(TicketNotification::sendsMail('priority_changed'));
        $this->assertFalse(TicketNotification::sendsMail('closed'));
    }

    public function test_mail_correspondence_events_keep_action_only(): void
    {
        $this->assertTrue(MailCorrespondenceNotification::sendsMail('assigned'));
        $this->assertTrue(MailCorrespondenceNotification::sendsMail('dispatched'));
        $this->assertFalse(MailCorrespondenceNotification::sendsMail('taken_charge'));
        $this->assertFalse(MailCorrespondenceNotification::sendsMail('reminder'));
    }

    public function test_document_workflow_events_keep_action_only(): void
    {
        $this->assertTrue(DocumentWorkflowNotification::sendsMail('transmitted'));
        $this->assertTrue(DocumentWorkflowNotification::sendsMail('validated'));
        $this->assertFalse(DocumentWorkflowNotification::sendsMail('commented'));
        $this->assertFalse(DocumentWorkflowNotification::sendsMail('vised'));
    }

    public function test_scheduled_reminders_keep_productions_meetings_appointments_only(): void
    {
        Artisan::call('schedule:list');
        $output = Artisan::output();

        $this->assertStringContainsString('parapheur:remind-productions', $output);
        $this->assertStringContainsString('parapheur:remind-meetings', $output);
        $this->assertStringContainsString('parapheur:remind-appointments', $output);

        $this->assertStringNotContainsString('parapheur:remind-overdue-instructions', $output);
        $this->assertStringNotContainsString('parapheur:remind-task-deadlines', $output);
        $this->assertStringNotContainsString('parapheur:escalate-tasks', $output);
        $this->assertStringNotContainsString('parapheur:remind-mail', $output);
        $this->assertStringNotContainsString('parapheur:remind-announcements', $output);
    }

    public function test_legacy_production_reminder_notification_is_removed(): void
    {
        $this->assertFileDoesNotExist(app_path('Notifications/ProductionReminderNotification.php'));
    }
}
