<?php

use App\Http\Controllers\Api\Ged\GedCategoryController;
use App\Http\Controllers\Api\Ged\GedClassificationController;
use App\Http\Controllers\Api\Ged\GedDashboardController;
use App\Http\Controllers\Api\Ged\GedDocumentController;
use App\Http\Controllers\Api\Ged\GedEngagementController;
use App\Http\Controllers\Api\Ged\GedLifecycleController;
use App\Http\Controllers\Api\Ged\GedTagController;
use App\Http\Controllers\Api\Library\BibliographicReferenceController;
use App\Http\Controllers\Api\Library\ReferenceCollectionController;
use App\Http\Controllers\Api\Mail\CirculationSheetController;
use App\Http\Controllers\Api\Mail\CorrespondenceAssignmentController;
use App\Http\Controllers\Api\Mail\CorrespondenceController;
use App\Http\Controllers\Api\Mail\CorrespondentController;
use App\Http\Controllers\Api\Mail\DocumentTemplateController;
use App\Http\Controllers\Api\Mail\MailAdminController;
use App\Http\Controllers\Api\Mail\MailDashboardController;
use App\Http\Controllers\Api\Mail\MailMetaController;
use App\Http\Controllers\Api\Mail\MailRegisterController;
use App\Http\Controllers\Api\Mail\TransmissionSlipController;
use App\Http\Controllers\Api\Workspace\WorkspaceBridgeController;
use App\Http\Controllers\Api\Workspace\WorkspaceController;
use App\Http\Controllers\Api\Workspace\WorkspaceDocumentController;
use App\Http\Controllers\Api\Workspace\WorkspaceFolderController;
use App\Http\Controllers\Api\Workspace\WorkspaceMemberController;
use App\Http\Controllers\Api\Workspace\WorkspaceQuotaAdminController;
use App\Http\Controllers\Api\Workspace\WorkspaceShareController;
use App\Http\Controllers\Api\Ticketing\ApplicationController;
use App\Http\Controllers\Api\Ticketing\AssetController;
use App\Http\Controllers\Api\Ticketing\KnowledgeController;
use App\Http\Controllers\Api\Ticketing\KnownErrorController;
use App\Http\Controllers\Api\Ticketing\ProblemController;
use App\Http\Controllers\Api\Ticketing\ServiceCatalogController;
use App\Http\Controllers\Api\Ticketing\TicketActionController;
use App\Http\Controllers\Api\Ticketing\TicketAttachmentController;
use App\Http\Controllers\Api\Ticketing\TicketCommentController;
use App\Http\Controllers\Api\Ticketing\TicketController;
use App\Http\Controllers\Api\Ticketing\TicketNotificationPreferenceController;
use App\Http\Controllers\Api\Ticketing\TicketRelationController;
use App\Http\Controllers\Api\Ticketing\TicketWorklogController;
use App\Http\Controllers\Api\Ticketing\TicketingAdminController;
use App\Http\Controllers\Api\Ticketing\TicketingAiController;
use App\Http\Controllers\Api\Ticketing\TicketingDashboardController;
use App\Http\Controllers\Api\Ticketing\TicketingMetaController;
use App\Http\Controllers\Api\Ticketing\TicketingReportController;
use App\Http\Controllers\Api\UnifiedSearchController;
use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AppointmentTypeController;
use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DelegationController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentTypeController;
use App\Http\Controllers\Api\InstructionController;
use App\Http\Controllers\Api\MeetingAgendaController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\MeetingDecisionController;
use App\Http\Controllers\Api\MeetingDocumentController;
use App\Http\Controllers\Api\MeetingMinutesController;
use App\Http\Controllers\Api\MeetingNoteController;
use App\Http\Controllers\Api\MeetingParticipantController;
use App\Http\Controllers\Api\MeetingTemplateController;
use App\Http\Controllers\Api\MeetingTypeController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\OnlyOfficeController;
use App\Http\Controllers\Api\ReportingController;
use App\Http\Controllers\Api\RolePermissionController;
use App\Http\Controllers\Api\StructureController;
use App\Http\Controllers\Api\UserController;
use App\Http\Controllers\Api\WorkflowController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login'])
    ->middleware('throttle:login');
Route::post('/auth/forgot-password', [AuthController::class, 'forgotPassword'])
    ->middleware('throttle:forgot-password');
Route::post('/auth/reset-password', [AuthController::class, 'resetPassword'])
    ->middleware('throttle:forgot-password');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
});

Route::middleware(['auth:sanctum', 'password.changed'])->group(function () {
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read', [NotificationController::class, 'markRead']);
    Route::post('/notifications/unread', [NotificationController::class, 'markUnread']);
    Route::delete('/notifications/{id}', [NotificationController::class, 'destroy']);

    Route::get('/meta/document-types', [MetaController::class, 'documentTypes']);
    Route::get('/meta/structures', [MetaController::class, 'structures']);
    Route::get('/meta/users', [MetaController::class, 'users']);
    Route::get('/meta/workflows', [MetaController::class, 'workflows']);

    Route::middleware('permission:admin.access')->group(function () {
        Route::get('/structures', [StructureController::class, 'index']);
        Route::post('/structures', [StructureController::class, 'store']);
        Route::put('/structures/{structure}', [StructureController::class, 'update']);
        Route::delete('/structures/{structure}', [StructureController::class, 'destroy']);
        Route::get('/structure-types', [StructureController::class, 'types']);
        Route::post('/structure-types', [StructureController::class, 'storeType']);
        Route::put('/structure-types/{structureType}', [StructureController::class, 'updateType']);
        Route::delete('/structure-types/{structureType}', [StructureController::class, 'destroyType']);

        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/roles', [UserController::class, 'roles']);
        Route::get('/users/{user}', [UserController::class, 'show']);
        Route::put('/users/{user}', [UserController::class, 'update']);
        Route::delete('/users/{user}', [UserController::class, 'destroy']);

        Route::get('/roles', [RolePermissionController::class, 'roles']);
        Route::post('/roles', [RolePermissionController::class, 'storeRole']);
        Route::put('/roles/{role}', [RolePermissionController::class, 'updateRole']);
        Route::delete('/roles/{role}', [RolePermissionController::class, 'destroyRole']);
        Route::get('/permissions', [RolePermissionController::class, 'permissions']);
        Route::post('/permissions', [RolePermissionController::class, 'storePermission']);
        Route::delete('/permissions/{permission}', [RolePermissionController::class, 'destroyPermission']);

        Route::get('/document-types', [DocumentTypeController::class, 'index']);
        Route::post('/document-types', [DocumentTypeController::class, 'store']);
        Route::put('/document-types/{documentType}', [DocumentTypeController::class, 'update']);
        Route::delete('/document-types/{documentType}', [DocumentTypeController::class, 'destroy']);

        Route::get('/meeting-types', [MeetingTypeController::class, 'index']);
        Route::post('/meeting-types', [MeetingTypeController::class, 'store']);
        Route::put('/meeting-types/{meetingType}', [MeetingTypeController::class, 'update']);
        Route::delete('/meeting-types/{meetingType}', [MeetingTypeController::class, 'destroy']);

        Route::get('/meeting-templates', [MeetingTemplateController::class, 'index']);
        Route::post('/meeting-templates', [MeetingTemplateController::class, 'store']);
        Route::put('/meeting-templates/{meetingTemplate}', [MeetingTemplateController::class, 'update']);
        Route::delete('/meeting-templates/{meetingTemplate}', [MeetingTemplateController::class, 'destroy']);

        Route::get('/appointment-types', [AppointmentTypeController::class, 'index']);
        Route::post('/appointment-types', [AppointmentTypeController::class, 'store']);
        Route::put('/appointment-types/{appointmentType}', [AppointmentTypeController::class, 'update']);
        Route::delete('/appointment-types/{appointmentType}', [AppointmentTypeController::class, 'destroy']);

        Route::get('/audit-logs', [AuditLogController::class, 'index']);

        Route::get('/workflows', [WorkflowController::class, 'index']);
        Route::post('/workflows', [WorkflowController::class, 'store']);
        Route::put('/workflows/{workflow}', [WorkflowController::class, 'update']);
        Route::delete('/workflows/{workflow}', [WorkflowController::class, 'destroy']);
    });

    Route::get('/dashboard/dg', [DashboardController::class, 'dg']);
    Route::get('/dashboard/direction', [DashboardController::class, 'direction']);

    Route::get('/parapheur/counts', [DocumentController::class, 'counts']);
    Route::post('/parapheur/documents', [DocumentController::class, 'store']);
    Route::get('/parapheur/documents', [DocumentController::class, 'index']);
    Route::get('/parapheur/documents/{document}', [DocumentController::class, 'show']);

    // ——— GED (référentiel documentaire transversal) ———
    Route::get('/ged/dashboard', GedDashboardController::class);
    Route::get('/ged/documents', [GedDocumentController::class, 'index']);
    Route::post('/ged/documents', [GedDocumentController::class, 'store']);
    Route::get('/ged/documents/{document}', [GedDocumentController::class, 'show']);
    Route::put('/ged/documents/{document}', [GedDocumentController::class, 'update']);
    Route::delete('/ged/documents/{document}', [GedDocumentController::class, 'destroy']);
    Route::post('/ged/documents/{document}/classify', [GedDocumentController::class, 'classify']);
    Route::post('/ged/documents/{document}/archive', [GedDocumentController::class, 'archive']);
    Route::post('/ged/documents/{document}/versions', [GedDocumentController::class, 'addVersion']);
    Route::post('/ged/documents/{document}/attachments', [GedDocumentController::class, 'addAttachment']);
    Route::post('/ged/documents/{document}/links', [GedDocumentController::class, 'link']);
    Route::delete('/ged/documents/{document}/links/{link}', [GedDocumentController::class, 'unlink']);
    Route::post('/ged/documents/{document}/share', [GedDocumentController::class, 'share']);
    Route::delete('/ged/documents/{document}/share/{rule}', [GedDocumentController::class, 'revokeShare']);

    Route::get('/ged/classification-nodes', [GedClassificationController::class, 'index']);
    Route::post('/ged/classification-nodes', [GedClassificationController::class, 'store']);
    Route::put('/ged/classification-nodes/{classificationNode}', [GedClassificationController::class, 'update']);
    Route::delete('/ged/classification-nodes/{classificationNode}', [GedClassificationController::class, 'destroy']);

    Route::get('/ged/categories', [GedCategoryController::class, 'index']);
    Route::post('/ged/categories', [GedCategoryController::class, 'store']);
    Route::put('/ged/categories/{documentCategory}', [GedCategoryController::class, 'update']);
    Route::delete('/ged/categories/{documentCategory}', [GedCategoryController::class, 'destroy']);

    Route::get('/ged/tags', [GedTagController::class, 'index']);
    Route::get('/ged/favorites', [GedEngagementController::class, 'favorites']);
    Route::get('/ged/recent', [GedEngagementController::class, 'recent']);
    Route::post('/ged/documents/{document}/favorite', [GedEngagementController::class, 'toggleFavorite']);
    Route::post('/ged/documents/{document}/legal-hold', [GedLifecycleController::class, 'legalHold']);
    Route::delete('/ged/documents/{document}/legal-hold', [GedLifecycleController::class, 'releaseLegalHold']);
    Route::post('/ged/documents/{document}/reindex', [GedLifecycleController::class, 'reindex']);
    Route::get('/ged/documents/{document}/export', [GedLifecycleController::class, 'export']);
    Route::get('/ged/indicators', [GedLifecycleController::class, 'indicators']);
    Route::get('/ged/retention-rules', [GedLifecycleController::class, 'retentionRules']);
    Route::post('/ged/retention-rules', [GedLifecycleController::class, 'storeRetentionRule']);
    Route::get('/ged/classification-rules', [GedLifecycleController::class, 'classificationRules']);
    Route::post('/ged/classification-rules', [GedLifecycleController::class, 'storeClassificationRule']);

    Route::get('/meta/document-categories', [GedCategoryController::class, 'index']);
    Route::get('/meta/classification-nodes', [GedClassificationController::class, 'index']);
    Route::get('/meta/document-tags', [GedTagController::class, 'index']);

    Route::post('/parapheur/documents/{document}/transmit', [DocumentController::class, 'transmit']);
    Route::post('/parapheur/documents/{document}/reassign', [DocumentController::class, 'reassign']);
    Route::post('/parapheur/documents/{document}/acknowledge', [DocumentController::class, 'acknowledge']);
    Route::post('/parapheur/documents/{document}/hold', [DocumentController::class, 'hold']);
    Route::post('/parapheur/documents/{document}/classify', [DocumentController::class, 'classify']);
    Route::post('/parapheur/documents/{document}/comments', [DocumentController::class, 'comment']);
    Route::post('/parapheur/documents/{document}/return', [DocumentController::class, 'returnCorrection']);
    Route::post('/parapheur/documents/{document}/complement', [DocumentController::class, 'requestComplement']);
    Route::post('/parapheur/documents/{document}/vise', [DocumentController::class, 'vise']);
    Route::post('/parapheur/documents/{document}/validate', [DocumentController::class, 'validateAction']);
    Route::post('/parapheur/documents/{document}/reject', [DocumentController::class, 'reject']);
    Route::post('/parapheur/documents/{document}/archive', [DocumentController::class, 'archive']);
    Route::get('/parapheur/documents/{document}/archive-pack', [DocumentController::class, 'downloadArchivePack']);
    Route::post('/parapheur/documents/{document}/versions', [DocumentController::class, 'addVersion']);
    Route::post('/parapheur/documents/{document}/attachments', [DocumentController::class, 'addAttachment']);
    Route::post('/parapheur/documents/{document}/instructions', [DocumentController::class, 'createInstruction']);

    Route::get('/parapheur/documents/{document}/onlyoffice/config', [OnlyOfficeController::class, 'config']);
    Route::get('/parapheur/documents/{document}/onlyoffice/history', [OnlyOfficeController::class, 'history']);
    Route::get('/parapheur/documents/{document}/onlyoffice/history/{versionNumber}', [OnlyOfficeController::class, 'historyData'])
        ->whereNumber('versionNumber');
    Route::get('/parapheur/documents/{document}/onlyoffice/compare/{versionNumber}', [OnlyOfficeController::class, 'compare'])
        ->whereNumber('versionNumber');
    Route::post('/parapheur/documents/{document}/onlyoffice/restore/{versionNumber}', [OnlyOfficeController::class, 'restore'])
        ->whereNumber('versionNumber');

    Route::get('/instructions', [InstructionController::class, 'index']);
    Route::patch('/instructions/{instruction}/status', [InstructionController::class, 'updateStatus']);

    Route::get('/delegations', [DelegationController::class, 'index']);
    Route::post('/delegations', [DelegationController::class, 'store']);
    Route::patch('/delegations/{delegation}', [DelegationController::class, 'update']);

    Route::get('/appointments/dashboard', [AppointmentController::class, 'dashboard']);
    Route::get('/appointments/calendar', [AppointmentController::class, 'calendar']);
    Route::get('/appointments/types', [AppointmentController::class, 'types']);
    Route::post('/appointments/check-conflicts', [AppointmentController::class, 'checkConflicts']);
    Route::get('/appointments/unavailabilities', [AppointmentController::class, 'unavailabilities']);
    Route::post('/appointments/unavailabilities', [AppointmentController::class, 'storeUnavailability']);
    Route::delete('/appointments/unavailabilities/{unavailability}', [AppointmentController::class, 'destroyUnavailability']);

    Route::get('/appointments', [AppointmentController::class, 'index']);
    Route::post('/appointments', [AppointmentController::class, 'store']);
    Route::get('/appointments/{appointment}', [AppointmentController::class, 'show']);
    Route::put('/appointments/{appointment}', [AppointmentController::class, 'update']);
    Route::delete('/appointments/{appointment}', [AppointmentController::class, 'destroy']);
    Route::post('/appointments/{appointment}/propose-slot', [AppointmentController::class, 'proposeSlot']);
    Route::post('/appointments/{appointment}/validate', [AppointmentController::class, 'validateAppointment']);
    Route::post('/appointments/{appointment}/reject', [AppointmentController::class, 'reject']);
    Route::post('/appointments/{appointment}/confirm', [AppointmentController::class, 'confirm']);
    Route::post('/appointments/{appointment}/reschedule', [AppointmentController::class, 'reschedule']);
    Route::post('/appointments/{appointment}/cancel', [AppointmentController::class, 'cancel']);
    Route::post('/appointments/{appointment}/hold', [AppointmentController::class, 'hold']);
    Route::post('/appointments/{appointment}/redirect', [AppointmentController::class, 'redirect']);
    Route::post('/appointments/{appointment}/start', [AppointmentController::class, 'start']);
    Route::post('/appointments/{appointment}/finish', [AppointmentController::class, 'finish']);
    Route::post('/appointments/{appointment}/close', [AppointmentController::class, 'close']);
    Route::post('/appointments/{appointment}/archive', [AppointmentController::class, 'archive']);
    Route::post('/appointments/{appointment}/participants', [AppointmentController::class, 'storeParticipant']);
    Route::delete('/appointments/{appointment}/participants/{participant}', [AppointmentController::class, 'destroyParticipant']);
    Route::post('/appointments/{appointment}/documents', [AppointmentController::class, 'storeDocument']);
    Route::delete('/appointments/{appointment}/documents/{appointmentDocument}', [AppointmentController::class, 'destroyDocument']);
    Route::post('/appointments/{appointment}/notes', [AppointmentController::class, 'storeNote']);
    Route::delete('/appointments/{appointment}/notes/{note}', [AppointmentController::class, 'destroyNote']);
    Route::post('/appointments/{appointment}/followups', [AppointmentController::class, 'storeFollowup']);
    Route::post('/appointments/{appointment}/convert-to-meeting', [AppointmentController::class, 'convertToMeeting']);
    Route::get('/appointments/{appointment}/preparation', [AppointmentController::class, 'preparation']);
    Route::get('/appointments/{appointment}/audit', [AppointmentController::class, 'audit']);

    Route::get('/meetings/dashboard', [MeetingController::class, 'dashboard']);
    Route::get('/meetings/calendar', [MeetingController::class, 'calendar']);
    Route::get('/meetings/types', [MeetingController::class, 'types']);
    Route::get('/meetings/decisions', [MeetingDecisionController::class, 'index']);
    Route::get('/meetings/decisions/export', [MeetingController::class, 'exportDecisionsCsv']);

    Route::get('/meetings', [MeetingController::class, 'index']);
    Route::post('/meetings', [MeetingController::class, 'store']);
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show']);
    Route::put('/meetings/{meeting}', [MeetingController::class, 'update']);
    Route::delete('/meetings/{meeting}', [MeetingController::class, 'destroy']);
    Route::post('/meetings/{meeting}/transition', [MeetingController::class, 'transition']);
    Route::post('/meetings/{meeting}/postpone', [MeetingController::class, 'postpone']);
    Route::post('/meetings/{meeting}/cancel', [MeetingController::class, 'cancel']);
    Route::get('/meetings/{meeting}/audit', [MeetingController::class, 'audit']);
    Route::get('/meetings/{meeting}/export/{kind}', [MeetingController::class, 'export']);

    Route::post('/meetings/{meeting}/agenda', [MeetingAgendaController::class, 'store']);
    Route::put('/meetings/{meeting}/agenda/{agendaItem}', [MeetingAgendaController::class, 'update']);
    Route::delete('/meetings/{meeting}/agenda/{agendaItem}', [MeetingAgendaController::class, 'destroy']);
    Route::post('/meetings/{meeting}/agenda/reorder', [MeetingAgendaController::class, 'reorder']);
    Route::post('/meetings/{meeting}/agenda/{agendaItem}/current', [MeetingAgendaController::class, 'setCurrent']);

    Route::post('/meetings/{meeting}/participants', [MeetingParticipantController::class, 'store']);
    Route::delete('/meetings/{meeting}/participants/{participant}', [MeetingParticipantController::class, 'destroy']);
    Route::post('/meetings/{meeting}/confirm', [MeetingParticipantController::class, 'confirm']);
    Route::post('/meetings/{meeting}/participants/{participant}/attendance', [MeetingParticipantController::class, 'attendance']);

    Route::post('/meetings/{meeting}/documents', [MeetingDocumentController::class, 'store']);
    Route::delete('/meetings/{meeting}/documents/{meetingDocument}', [MeetingDocumentController::class, 'destroy']);

    Route::post('/meetings/{meeting}/notes', [MeetingNoteController::class, 'store']);
    Route::put('/meetings/{meeting}/notes/{note}', [MeetingNoteController::class, 'update']);
    Route::delete('/meetings/{meeting}/notes/{note}', [MeetingNoteController::class, 'destroy']);

    Route::post('/meetings/{meeting}/decisions', [MeetingDecisionController::class, 'store']);
    Route::put('/meetings/{meeting}/decisions/{decision}', [MeetingDecisionController::class, 'update']);
    Route::post('/meetings/{meeting}/decisions/{decision}/validate-execution', [MeetingDecisionController::class, 'validateExecution']);
    Route::post('/meetings/{meeting}/recommendations', [MeetingDecisionController::class, 'storeRecommendation']);
    Route::post('/meetings/{meeting}/recommendations/{recommendation}/convert', [MeetingDecisionController::class, 'convertRecommendation']);

    Route::post('/meetings/{meeting}/convocation', [MeetingMinutesController::class, 'generateConvocation']);
    Route::post('/meetings/{meeting}/convocation/submit', [MeetingMinutesController::class, 'submitConvocation']);
    Route::post('/meetings/{meeting}/send-invitations', [MeetingMinutesController::class, 'sendInvitations'])
        ->middleware('throttle:10,1');
    Route::post('/meetings/{meeting}/minutes', [MeetingMinutesController::class, 'generate']);
    Route::put('/meetings/{meeting}/minutes/{minute}', [MeetingMinutesController::class, 'update']);
    Route::post('/meetings/{meeting}/minutes/{minute}/submit', [MeetingMinutesController::class, 'submit']);
    Route::post('/meetings/{meeting}/minutes/{minute}/validate', [MeetingMinutesController::class, 'validateMinute']);
    Route::post('/meetings/{meeting}/minutes/{minute}/diffuse', [MeetingMinutesController::class, 'diffuse']);
    Route::get('/meetings/{meeting}/minutes/{minute}/preview', [MeetingMinutesController::class, 'preview']);

    Route::get('/reporting/export', [ReportingController::class, 'export']);

    Route::get('/search', UnifiedSearchController::class);

    // ——— Mon espace documentaire ———
    Route::middleware('permission:workspace.access|admin.access')->prefix('workspace')->group(function () {
        Route::get('/home', [WorkspaceController::class, 'home']);
        Route::get('/collaborative', [WorkspaceController::class, 'collaborative']);
        Route::post('/', [WorkspaceController::class, 'store'])->middleware('permission:workspace.create_shared|admin.access');
        Route::get('/shared-with-me', [WorkspaceController::class, 'sharedWithMe']);
        Route::get('/favorites', [WorkspaceController::class, 'favorites']);
        Route::post('/favorites/toggle', [WorkspaceController::class, 'toggleFavorite']);
        Route::get('/recent', [WorkspaceController::class, 'recent']);

        Route::middleware('permission:workspace.manage_quotas|admin.access')->prefix('admin')->group(function () {
            Route::get('/storage-policy', [WorkspaceQuotaAdminController::class, 'policy']);
            Route::put('/storage-policy', [WorkspaceQuotaAdminController::class, 'updatePolicy']);
            Route::get('/quota-overrides', [WorkspaceQuotaAdminController::class, 'overrides']);
            Route::post('/quota-overrides', [WorkspaceQuotaAdminController::class, 'storeOverride']);
            Route::delete('/quota-overrides/{override}', [WorkspaceQuotaAdminController::class, 'destroyOverride']);
            Route::post('/workspaces/{workspace}/recalculate-storage', [WorkspaceQuotaAdminController::class, 'recalculate']);
        });

        Route::get('/{workspace}', [WorkspaceController::class, 'show']);
        Route::get('/{workspace}/browse', [WorkspaceController::class, 'browse']);
        Route::get('/{workspace}/storage', [WorkspaceController::class, 'storage']);
        Route::get('/{workspace}/trash', [WorkspaceController::class, 'trash']);
        Route::post('/{workspace}/trash/{id}/restore', [WorkspaceController::class, 'restoreTrash']);

        Route::get('/{workspace}/folders', [WorkspaceFolderController::class, 'index']);
        Route::post('/{workspace}/folders', [WorkspaceFolderController::class, 'store']);
        Route::put('/{workspace}/folders/{folder}', [WorkspaceFolderController::class, 'update']);
        Route::post('/{workspace}/folders/{folder}/move', [WorkspaceFolderController::class, 'move']);
        Route::delete('/{workspace}/folders/{folder}', [WorkspaceFolderController::class, 'destroy']);

        Route::get('/{workspace}/documents', [WorkspaceDocumentController::class, 'index']);
        Route::post('/{workspace}/documents', [WorkspaceDocumentController::class, 'upload']);
        Route::post('/{workspace}/documents/{document}/move', [WorkspaceDocumentController::class, 'move']);
        Route::delete('/{workspace}/documents/{document}', [WorkspaceDocumentController::class, 'destroy']);

        Route::post('/{workspace}/shares', [WorkspaceShareController::class, 'store']);
        Route::delete('/{workspace}/shares/{share}', [WorkspaceShareController::class, 'destroy']);

        Route::get('/{workspace}/members', [WorkspaceMemberController::class, 'index']);
        Route::post('/{workspace}/members', [WorkspaceMemberController::class, 'store']);
        Route::put('/{workspace}/members/{member}', [WorkspaceMemberController::class, 'update']);
        Route::delete('/{workspace}/members/{member}', [WorkspaceMemberController::class, 'destroy']);
        Route::get('/{workspace}/activity', [WorkspaceMemberController::class, 'activity']);

        Route::post('/{workspace}/documents/{document}/submit-ged', [WorkspaceBridgeController::class, 'submitToGed']);
        Route::post('/{workspace}/documents/{document}/submit-parapheur', [WorkspaceBridgeController::class, 'submitToParapheur']);
        Route::post('/{workspace}/documents/{document}/working-copy', [WorkspaceBridgeController::class, 'workingCopy']);
        Route::post('/{workspace}/documents/{document}/attach-meeting', [WorkspaceBridgeController::class, 'attachMeeting']);
        Route::post('/{workspace}/documents/{document}/attach-appointment', [WorkspaceBridgeController::class, 'attachAppointment']);
        Route::post('/{workspace}/documents/{document}/attach-instruction', [WorkspaceBridgeController::class, 'attachInstruction']);
    });

    // ——— Bibliothèque de références ———
    Route::middleware('permission:library.access|admin.access')->prefix('library')->group(function () {
        Route::get('/reference-types', [BibliographicReferenceController::class, 'types']);
        Route::get('/collections', [BibliographicReferenceController::class, 'collections']);
        Route::post('/collections', [BibliographicReferenceController::class, 'storeCollection']);
        Route::get('/references', [BibliographicReferenceController::class, 'index']);
        Route::post('/references', [BibliographicReferenceController::class, 'store']);
        Route::get('/references/{reference}', [BibliographicReferenceController::class, 'show']);
        Route::post('/references/{reference}/propose', [BibliographicReferenceController::class, 'propose']);
        Route::post('/references/{reference}/moderate', [BibliographicReferenceController::class, 'moderate']);
        Route::post('/references/{reference}/note', [BibliographicReferenceController::class, 'upsertNote']);
    });

    // ——— Module Courrier ———
    Route::middleware('permission:mail.view|admin.access')->prefix('mail')->group(function () {
        // Dashboards
        Route::get('/dashboard/order-office', [MailDashboardController::class, 'orderOffice']);
        Route::get('/dashboard/dg', [MailDashboardController::class, 'dg']);
        Route::get('/dashboard/direction', [MailDashboardController::class, 'direction']);

        // Métadonnées
        Route::get('/meta/channels', [MailMetaController::class, 'channels']);
        Route::get('/meta/categories', [MailMetaController::class, 'categories']);
        Route::get('/meta/qualifications', [MailMetaController::class, 'qualifications']);
        Route::get('/meta/actions', [MailMetaController::class, 'actions']);
        Route::get('/meta/correspondents', [MailMetaController::class, 'correspondents']);

        // Registres d'enregistrement
        Route::post('/registers/incoming', [MailRegisterController::class, 'incoming'])
            ->middleware('permission:mail.create|admin.access');
        Route::post('/registers/outgoing', [MailRegisterController::class, 'outgoing'])
            ->middleware('permission:mail.create|admin.access');
        Route::post('/registers/internal', [MailRegisterController::class, 'internal'])
            ->middleware('permission:mail.create|admin.access');

        // Correspondances - CRUD
        Route::get('/correspondences', [CorrespondenceController::class, 'index']);
        Route::post('/correspondences', [CorrespondenceController::class, 'store'])
            ->middleware('permission:mail.create|admin.access');
        Route::get('/correspondences/{correspondence}', [CorrespondenceController::class, 'show']);
        Route::put('/correspondences/{correspondence}', [CorrespondenceController::class, 'update'])
            ->middleware('permission:mail.update|admin.access');
        Route::delete('/correspondences/{correspondence}', [CorrespondenceController::class, 'destroy'])
            ->middleware('permission:mail.delete|admin.access');
        Route::get('/correspondences/{correspondence}/history', [CorrespondenceController::class, 'history']);

        // Correspondances - Actions
        Route::post('/correspondences/{correspondence}/register', [CorrespondenceController::class, 'register'])
            ->middleware('permission:mail.update|admin.access');
        Route::post('/correspondences/{correspondence}/reply', [CorrespondenceController::class, 'reply'])
            ->middleware('permission:mail.create|admin.access');
        Route::post('/correspondences/{correspondence}/submit-to-parapheur', [CorrespondenceController::class, 'submitToParapheur'])
            ->middleware('permission:mail.update|documents.create|admin.access');
        Route::post('/correspondences/{correspondence}/dispatch', [CorrespondenceController::class, 'dispatch'])
            ->middleware('permission:mail.dispatch|admin.access');
        Route::post('/correspondences/{correspondence}/dispatches/{dispatch}/generate', [CorrespondenceController::class, 'generateDispatchSlip'])
            ->middleware('permission:mail.dispatch|admin.access');
        Route::post('/correspondences/{correspondence}/acknowledge', [CorrespondenceController::class, 'acknowledge'])
            ->middleware('permission:mail.update|admin.access');
        Route::post('/correspondences/{correspondence}/acknowledgements/{acknowledgement}/generate', [CorrespondenceController::class, 'generateAcknowledgementDocument'])
            ->middleware('permission:mail.update|admin.access');
        Route::post('/correspondences/{correspondence}/parties', [CorrespondenceController::class, 'syncParties'])
            ->middleware('permission:mail.update|admin.access');
        Route::post('/correspondences/{correspondence}/archive', [CorrespondenceController::class, 'archive'])
            ->middleware('permission:mail.update|admin.access');

        // Affectations
        Route::post('/correspondences/{correspondence}/assignments', [CorrespondenceAssignmentController::class, 'store'])
            ->middleware('permission:mail.assign|admin.access');
        Route::post('/correspondences/{correspondence}/assignments/take-charge', [CorrespondenceAssignmentController::class, 'takeCharge'])
            ->middleware('permission:mail.process|admin.access');
        Route::post('/correspondences/{correspondence}/assignments/reassign', [CorrespondenceAssignmentController::class, 'reassign'])
            ->middleware('permission:mail.assign|admin.access');
        Route::post('/correspondences/{correspondence}/assignments/return', [CorrespondenceAssignmentController::class, 'return'])
            ->middleware('permission:mail.process|admin.access');
        Route::post('/correspondences/{correspondence}/assignments/request-complement', [CorrespondenceAssignmentController::class, 'requestComplement'])
            ->middleware('permission:mail.process|admin.access');

        // Correspondants
        Route::get('/correspondents', [CorrespondentController::class, 'index']);
        Route::post('/correspondents', [CorrespondentController::class, 'store'])
            ->middleware('permission:mail.update|admin.access');
        Route::get('/correspondents/{correspondent}', [CorrespondentController::class, 'show']);
        Route::put('/correspondents/{correspondent}', [CorrespondentController::class, 'update'])
            ->middleware('permission:mail.update|admin.access');
        Route::delete('/correspondents/{correspondent}', [CorrespondentController::class, 'destroy'])
            ->middleware('permission:mail.delete|admin.access');

        // Bordereaux de transmission
        Route::get('/transmission-slips', [TransmissionSlipController::class, 'index']);
        Route::post('/transmission-slips', [TransmissionSlipController::class, 'store'])
            ->middleware('permission:mail.create|admin.access');
        Route::get('/transmission-slips/{transmissionSlip}', [TransmissionSlipController::class, 'show']);
        Route::put('/transmission-slips/{transmissionSlip}', [TransmissionSlipController::class, 'update'])
            ->middleware('permission:mail.update|admin.access');
        Route::delete('/transmission-slips/{transmissionSlip}', [TransmissionSlipController::class, 'destroy'])
            ->middleware('permission:mail.delete|admin.access');
        Route::post('/transmission-slips/{transmissionSlip}/items', [TransmissionSlipController::class, 'addItems'])
            ->middleware('permission:mail.update|admin.access');
        Route::delete('/transmission-slips/{transmissionSlip}/items/{itemId}', [TransmissionSlipController::class, 'removeItem'])
            ->middleware('permission:mail.update|admin.access');
        Route::post('/transmission-slips/{transmissionSlip}/generate', [TransmissionSlipController::class, 'generateDocument'])
            ->middleware('permission:mail.update|admin.access');
        Route::post('/transmission-slips/{transmissionSlip}/validate', [TransmissionSlipController::class, 'validate'])
            ->middleware('permission:mail.update|admin.access');
        Route::post('/transmission-slips/{transmissionSlip}/print', [TransmissionSlipController::class, 'print'])
            ->middleware('permission:mail.update|admin.access');
        Route::post('/transmission-slips/{transmissionSlip}/send', [TransmissionSlipController::class, 'send'])
            ->middleware('permission:mail.dispatch|admin.access');
        Route::post('/transmission-slips/{transmissionSlip}/acknowledge', [TransmissionSlipController::class, 'acknowledge'])
            ->middleware('permission:mail.update|admin.access');

        // Logs d'impression
        Route::get('/print-logs', [TransmissionSlipController::class, 'printLogs']);

        // Impression document correspondance
        Route::post('/correspondences/{correspondence}/print', [CorrespondenceController::class, 'printDocument'])
            ->middleware('permission:mail.view|admin.access');
        
        // Attacher version signée physiquement
        Route::post('/correspondences/{correspondence}/attach-signed-version', [CorrespondenceController::class, 'attachSignedVersion'])
            ->middleware('permission:mail.update|admin.access');
        Route::get('/correspondences/{correspondence}/reminders', [CorrespondenceController::class, 'reminders']);
        Route::post('/correspondences/{correspondence}/reminders', [CorrespondenceController::class, 'storeReminder'])
            ->middleware('permission:mail.update|admin.access');
        Route::post('/correspondences/{correspondence}/reminders/schedule', [CorrespondenceController::class, 'scheduleReminders'])
            ->middleware('permission:mail.update|admin.access');
        Route::post('/correspondences/{correspondence}/circulation-sheet', [CirculationSheetController::class, 'createForCorrespondence'])
            ->middleware('permission:mail.create|admin.access');

        // Administration des référentiels
        Route::middleware('permission:mail.admin|admin.access')->prefix('admin')->group(function () {
            Route::get('/channels', [MailAdminController::class, 'channelsIndex']);
            Route::post('/channels', [MailAdminController::class, 'channelsStore']);
            Route::put('/channels/{channel}', [MailAdminController::class, 'channelsUpdate']);
            Route::post('/channels/{channel}/toggle', [MailAdminController::class, 'channelsToggle']);
            Route::delete('/channels/{channel}', [MailAdminController::class, 'channelsDestroy']);

            Route::get('/categories', [MailAdminController::class, 'categoriesIndex']);
            Route::post('/categories', [MailAdminController::class, 'categoriesStore']);
            Route::put('/categories/{category}', [MailAdminController::class, 'categoriesUpdate']);
            Route::post('/categories/{category}/toggle', [MailAdminController::class, 'categoriesToggle']);
            Route::delete('/categories/{category}', [MailAdminController::class, 'categoriesDestroy']);

            Route::get('/qualifications', [MailAdminController::class, 'qualificationsIndex']);
            Route::post('/qualifications', [MailAdminController::class, 'qualificationsStore']);
            Route::put('/qualifications/{qualification}', [MailAdminController::class, 'qualificationsUpdate']);
            Route::post('/qualifications/{qualification}/toggle', [MailAdminController::class, 'qualificationsToggle']);
            Route::delete('/qualifications/{qualification}', [MailAdminController::class, 'qualificationsDestroy']);

            Route::get('/actions', [MailAdminController::class, 'actionsIndex']);
            Route::post('/actions', [MailAdminController::class, 'actionsStore']);
            Route::put('/actions/{action}', [MailAdminController::class, 'actionsUpdate']);
            Route::post('/actions/{action}/toggle', [MailAdminController::class, 'actionsToggle']);
            Route::delete('/actions/{action}', [MailAdminController::class, 'actionsDestroy']);
        });

        // Fiches de circulation
        Route::get('/circulation-sheets', [CirculationSheetController::class, 'index']);
        Route::post('/circulation-sheets', [CirculationSheetController::class, 'store'])
            ->middleware('permission:mail.create|admin.access');
        Route::get('/circulation-sheets/{circulationSheet}', [CirculationSheetController::class, 'show']);
        Route::post('/circulation-sheets/{circulationSheet}/generate', [CirculationSheetController::class, 'generateDocument'])
            ->middleware('permission:mail.update|admin.access');

        // Modèles documentaires
        Route::middleware('permission:document_template.view|admin.access')->group(function () {
            Route::get('/document-templates', [DocumentTemplateController::class, 'index']);
            Route::get('/document-templates/{documentTemplate}', [DocumentTemplateController::class, 'show']);
            
            Route::middleware('permission:document_template.create|admin.access')->group(function () {
                Route::post('/document-templates', [DocumentTemplateController::class, 'store']);
                Route::post('/document-templates/{documentTemplate}/versions', [DocumentTemplateController::class, 'createVersion']);
            });
            
            Route::middleware('permission:document_template.update|admin.access')->group(function () {
                Route::put('/document-templates/{documentTemplate}', [DocumentTemplateController::class, 'update']);
                Route::post('/document-templates/{documentTemplate}/publish', [DocumentTemplateController::class, 'publish']);
            });
            
            Route::delete('/document-templates/{documentTemplate}', [DocumentTemplateController::class, 'destroy'])
                ->middleware('permission:document_template.delete|admin.access');
            
            Route::post('/document-templates/{documentTemplate}/generate', [DocumentTemplateController::class, 'generate']);
        });

        // Recherche
        Route::get('/search', [CorrespondenceController::class, 'search']);
    });

    // ——— Module Ticketing / Centre de services ———
    Route::middleware('permission:ticket.view|admin.access')->prefix('ticketing')->group(function () {
        Route::get('/meta', [TicketingMetaController::class, 'index']);

        Route::get('/notification-preferences', [TicketNotificationPreferenceController::class, 'show']);
        Route::put('/notification-preferences', [TicketNotificationPreferenceController::class, 'update']);

        Route::get('/dashboard/requester', [TicketingDashboardController::class, 'requester']);
        Route::get('/dashboard/agent', [TicketingDashboardController::class, 'agent']);
        Route::get('/dashboard/team', [TicketingDashboardController::class, 'team']);
        Route::get('/dashboard/management', [TicketingDashboardController::class, 'management']);

        Route::get('/service-catalog', [ServiceCatalogController::class, 'index']);
        Route::get('/service-catalog/{serviceItem}', [ServiceCatalogController::class, 'show']);
        Route::get('/service-catalog/{serviceItem}/form', [ServiceCatalogController::class, 'form']);

        Route::get('/tickets', [TicketController::class, 'index']);
        Route::get('/tickets/search', [TicketController::class, 'search']);
        Route::get('/tickets/kanban', [TicketController::class, 'kanban']);
        Route::post('/tickets/duplicate-check', [TicketController::class, 'duplicateCheck'])
            ->middleware('permission:ticket.create|admin.access');
        Route::post('/tickets', [TicketController::class, 'store'])
            ->middleware('permission:ticket.create|admin.access');
        Route::get('/tickets/{ticket}', [TicketController::class, 'show']);
        Route::put('/tickets/{ticket}', [TicketController::class, 'update'])
            ->middleware('permission:ticket.update|admin.access');
        Route::get('/tickets/{ticket}/timeline', [TicketController::class, 'timeline']);

        Route::post('/tickets/{ticket}/assign', [TicketActionController::class, 'assign'])
            ->middleware('permission:ticket.assign|admin.access');
        Route::post('/tickets/{ticket}/take-charge', [TicketActionController::class, 'takeCharge'])
            ->middleware('permission:ticket.take_charge|admin.access');
        Route::post('/tickets/{ticket}/escalate', [TicketActionController::class, 'escalate'])
            ->middleware('permission:ticket.escalate|admin.access');
        Route::post('/tickets/{ticket}/resolve', [TicketActionController::class, 'resolve'])
            ->middleware('permission:ticket.resolve|admin.access');
        Route::post('/tickets/{ticket}/reopen', [TicketActionController::class, 'reopen'])
            ->middleware('permission:ticket.reopen|admin.access');
        Route::post('/tickets/{ticket}/close', [TicketActionController::class, 'close'])
            ->middleware('permission:ticket.close|admin.access');
        Route::post('/tickets/{ticket}/cancel', [TicketActionController::class, 'cancel'])
            ->middleware('permission:ticket.cancel|admin.access');
        Route::post('/tickets/{ticket}/wait', [TicketActionController::class, 'wait'])
            ->middleware('permission:ticket.update|admin.access');
        Route::patch('/tickets/{ticket}/status', [TicketActionController::class, 'changeStatus'])
            ->middleware('permission:ticket.update|admin.access');
        Route::post('/tickets/{ticket}/satisfaction', [TicketActionController::class, 'satisfaction'])
            ->middleware('permission:ticket.view|admin.access');

        Route::get('/tickets/{ticket}/comments', [TicketCommentController::class, 'index']);
        Route::post('/tickets/{ticket}/comments', [TicketCommentController::class, 'store'])
            ->middleware('permission:ticket.comment|ticket.internal_note|admin.access');

        Route::post('/tickets/{ticket}/attachments', [TicketAttachmentController::class, 'store'])
            ->middleware('permission:ticket.create|ticket.update|admin.access');
        Route::get('/tickets/{ticket}/attachments/{attachment}/download', [TicketAttachmentController::class, 'download']);
        Route::delete('/tickets/{ticket}/attachments/{attachment}', [TicketAttachmentController::class, 'destroy'])
            ->middleware('permission:ticket.update|admin.access');

        Route::get('/tickets/{ticket}/worklogs', [TicketWorklogController::class, 'index']);
        Route::post('/tickets/{ticket}/worklogs', [TicketWorklogController::class, 'store'])
            ->middleware('permission:ticket.update|admin.access');

        Route::get('/tickets/{ticket}/relations', [TicketRelationController::class, 'index']);
        Route::post('/tickets/{ticket}/relations', [TicketRelationController::class, 'store'])
            ->middleware('permission:ticket.update|admin.access');
        Route::delete('/tickets/{ticket}/relations/{relation}', [TicketRelationController::class, 'destroy'])
            ->middleware('permission:ticket.update|admin.access');

        Route::get('/reports/volume', [TicketingReportController::class, 'volume'])
            ->middleware('permission:ticket.view_reports|admin.access');
        Route::get('/reports/sla', [TicketingReportController::class, 'sla'])
            ->middleware('permission:ticket.view_reports|admin.access');
        Route::get('/reports/satisfaction', [TicketingReportController::class, 'satisfaction'])
            ->middleware('permission:ticket.view_reports|admin.access');
        Route::get('/reports/by-category', [TicketingReportController::class, 'byCategory'])
            ->middleware('permission:ticket.view_reports|admin.access');

        Route::middleware('permission:problem.view|admin.access')->group(function () {
            Route::get('/problems', [ProblemController::class, 'index']);
            Route::post('/problems', [ProblemController::class, 'store'])
                ->middleware('permission:problem.create|admin.access');
            Route::get('/problems/{problem}', [ProblemController::class, 'show']);
            Route::put('/problems/{problem}', [ProblemController::class, 'update'])
                ->middleware('permission:problem.update|admin.access');
            Route::post('/problems/{problem}/tickets', [ProblemController::class, 'linkTicket'])
                ->middleware('permission:problem.update|admin.access');
            Route::delete('/problems/{problem}/tickets/{ticket}', [ProblemController::class, 'unlinkTicket'])
                ->middleware('permission:problem.update|admin.access');
            Route::post('/problems/{problem}/known-errors', [KnownErrorController::class, 'storeForProblem'])
                ->middleware('permission:problem.update|admin.access');
        });

        Route::get('/known-errors', [KnownErrorController::class, 'index']);
        Route::post('/known-errors', [KnownErrorController::class, 'store'])
            ->middleware('permission:problem.create|problem.update|admin.access');
        Route::get('/known-errors/{knownError}', [KnownErrorController::class, 'show']);
        Route::put('/known-errors/{knownError}', [KnownErrorController::class, 'update'])
            ->middleware('permission:problem.update|admin.access');
        Route::delete('/known-errors/{knownError}', [KnownErrorController::class, 'destroy'])
            ->middleware('permission:problem.update|admin.access');

        Route::middleware('permission:knowledge.view|admin.access')->group(function () {
            Route::get('/knowledge', [KnowledgeController::class, 'index']);
            Route::post('/knowledge', [KnowledgeController::class, 'store'])
                ->middleware('permission:knowledge.create|admin.access');
            Route::get('/knowledge/{article}', [KnowledgeController::class, 'show']);
            Route::put('/knowledge/{article}', [KnowledgeController::class, 'update'])
                ->middleware('permission:knowledge.create|knowledge.review|admin.access');
            Route::post('/knowledge/{article}/publish', [KnowledgeController::class, 'publish'])
                ->middleware('permission:knowledge.publish|admin.access');
            Route::post('/knowledge/{article}/tickets', [KnowledgeController::class, 'linkTicket'])
                ->middleware('permission:knowledge.create|admin.access');
        });

        Route::get('/applications', [ApplicationController::class, 'index']);
        Route::post('/applications', [ApplicationController::class, 'store'])
            ->middleware('permission:ticket.admin|admin.access');
        Route::get('/applications/{application}', [ApplicationController::class, 'show']);
        Route::put('/applications/{application}', [ApplicationController::class, 'update'])
            ->middleware('permission:ticket.admin|admin.access');

        Route::get('/assets', [AssetController::class, 'index']);
        Route::post('/assets', [AssetController::class, 'store'])
            ->middleware('permission:ticket.admin|admin.access');
        Route::get('/assets/{asset}', [AssetController::class, 'show']);
        Route::put('/assets/{asset}', [AssetController::class, 'update'])
            ->middleware('permission:ticket.admin|admin.access');

        Route::post('/ai/suggest', [TicketingAiController::class, 'suggest'])
            ->middleware('permission:ticket.create|ticket.update|admin.access');

        Route::middleware('permission:ticket.admin|admin.access')->prefix('admin')->group(function () {
            Route::get('/types', [TicketingAdminController::class, 'typesIndex']);
            Route::post('/types', [TicketingAdminController::class, 'typesStore']);
            Route::put('/types/{type}', [TicketingAdminController::class, 'typesUpdate']);

            Route::get('/categories', [TicketingAdminController::class, 'categoriesIndex']);
            Route::post('/categories', [TicketingAdminController::class, 'categoriesStore']);
            Route::put('/categories/{category}', [TicketingAdminController::class, 'categoriesUpdate']);

            Route::get('/channels', [TicketingAdminController::class, 'channelsIndex']);
            Route::post('/channels', [TicketingAdminController::class, 'channelsStore']);
            Route::put('/channels/{channel}', [TicketingAdminController::class, 'channelsUpdate']);

            Route::get('/teams', [TicketingAdminController::class, 'teamsIndex']);
            Route::post('/teams', [TicketingAdminController::class, 'teamsStore']);
            Route::put('/teams/{team}', [TicketingAdminController::class, 'teamsUpdate']);
            Route::post('/teams/{team}/members', [TicketingAdminController::class, 'teamMembersStore']);
            Route::delete('/teams/{team}/members/{member}', [TicketingAdminController::class, 'teamMembersDestroy']);

            Route::get('/catalogs', [TicketingAdminController::class, 'catalogsIndex']);
            Route::post('/catalogs', [TicketingAdminController::class, 'catalogsStore']);
            Route::put('/catalogs/{catalog}', [TicketingAdminController::class, 'catalogsUpdate']);
            Route::post('/catalogs/{catalog}/items', [TicketingAdminController::class, 'itemsStore']);
            Route::put('/items/{item}', [TicketingAdminController::class, 'itemsUpdate']);
            Route::post('/items/{item}/fields', [TicketingAdminController::class, 'fieldsStore']);
            Route::put('/fields/{field}', [TicketingAdminController::class, 'fieldsUpdate']);

            Route::get('/sla-policies', [TicketingAdminController::class, 'slaPoliciesIndex']);
            Route::post('/sla-policies', [TicketingAdminController::class, 'slaPoliciesStore']);
            Route::put('/sla-policies/{policy}', [TicketingAdminController::class, 'slaPoliciesUpdate']);

            Route::get('/sla-calendars', [TicketingAdminController::class, 'slaCalendarsIndex']);
            Route::post('/sla-calendars', [TicketingAdminController::class, 'slaCalendarsStore']);
            Route::put('/sla-calendars/{calendar}', [TicketingAdminController::class, 'slaCalendarsUpdate']);
            Route::post('/sla-calendars/{calendar}/exceptions', [TicketingAdminController::class, 'slaCalendarExceptionsStore']);

            Route::get('/priority-matrix', [TicketingAdminController::class, 'priorityMatrixIndex']);
            Route::post('/priority-matrix', [TicketingAdminController::class, 'priorityMatrixStore']);

            Route::get('/settings', [TicketingAdminController::class, 'settingsIndex']);
            Route::put('/settings', [TicketingAdminController::class, 'settingsUpdate']);
        });
    });
});
// Callback ONLYOFFICE Document Server (JWT, hors Sanctum)
Route::post('/onlyoffice/callback/{document}', [OnlyOfficeController::class, 'callback'])
    ->name('onlyoffice.callback');

// Téléchargement via URL signée + contrôle d'accès métier (sans Bearer dans un nouvel onglet)
Route::middleware('signed')->group(function () {
    Route::get('/parapheur/documents/{document}/versions/{version}/download', [DocumentController::class, 'downloadVersion'])
        ->name('documents.version.download');
    Route::get('/parapheur/documents/{document}/versions/{version}/stream', [DocumentController::class, 'streamVersion'])
        ->name('documents.version.stream');
    Route::get('/parapheur/documents/{document}/attachments/{attachment}/download', [DocumentController::class, 'downloadAttachment'])
        ->name('documents.attachment.download');
});
