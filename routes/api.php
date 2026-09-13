<?php

use App\Http\Controllers\Api\Ged\GedCategoryController;
use App\Http\Controllers\Api\Ged\GedClassificationController;
use App\Http\Controllers\Api\Ged\GedDashboardController;
use App\Http\Controllers\Api\Ged\GedDocumentController;
use App\Http\Controllers\Api\Ged\GedEngagementController;
use App\Http\Controllers\Api\Ged\GedLifecycleController;
use App\Http\Controllers\Api\Ged\GedTagController;
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
