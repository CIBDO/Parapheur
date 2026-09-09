<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DelegationController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\DocumentTypeController;
use App\Http\Controllers\Api\InstructionController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\MetaController;
use App\Http\Controllers\Api\NotificationController;
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

        Route::get('/audit-logs', [AuditLogController::class, 'index']);

        Route::get('/workflows', [WorkflowController::class, 'index']);
        Route::post('/workflows', [WorkflowController::class, 'store']);
        Route::put('/workflows/{workflow}', [WorkflowController::class, 'update']);
        Route::delete('/workflows/{workflow}', [WorkflowController::class, 'destroy']);
    });

    Route::get('/dashboard/dg', [DashboardController::class, 'dg']);
    Route::get('/dashboard/direction', [DashboardController::class, 'direction']);

    Route::get('/parapheur/counts', [DocumentController::class, 'counts']);
    Route::get('/parapheur/documents', [DocumentController::class, 'index']);
    Route::post('/parapheur/documents', [DocumentController::class, 'store']);
    Route::get('/parapheur/documents/{document}', [DocumentController::class, 'show']);
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

    Route::get('/instructions', [InstructionController::class, 'index']);
    Route::patch('/instructions/{instruction}/status', [InstructionController::class, 'updateStatus']);

    Route::get('/delegations', [DelegationController::class, 'index']);
    Route::post('/delegations', [DelegationController::class, 'store']);
    Route::patch('/delegations/{delegation}', [DelegationController::class, 'update']);

    Route::get('/meetings', [MeetingController::class, 'index']);
    Route::post('/meetings', [MeetingController::class, 'store']);
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show']);
    Route::put('/meetings/{meeting}', [MeetingController::class, 'update']);
    Route::post('/meetings/{meeting}/decisions', [MeetingController::class, 'addDecision']);

    Route::get('/reporting/export', [ReportingController::class, 'export']);
});

// Téléchargement via URL signée + contrôle d'accès métier (sans Bearer dans un nouvel onglet)
Route::middleware('signed')->group(function () {
    Route::get('/parapheur/documents/{document}/versions/{version}/download', [DocumentController::class, 'downloadVersion'])
        ->name('documents.version.download');
    Route::get('/parapheur/documents/{document}/versions/{version}/stream', [DocumentController::class, 'streamVersion'])
        ->name('documents.version.stream');
    Route::get('/parapheur/documents/{document}/attachments/{attachment}/download', [DocumentController::class, 'downloadAttachment'])
        ->name('documents.attachment.download');
});
