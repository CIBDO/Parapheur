<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\DelegationController;
use App\Http\Controllers\Api\DocumentController;
use App\Http\Controllers\Api\InstructionController;
use App\Http\Controllers\Api\MeetingController;
use App\Http\Controllers\Api\MetaController;
use Illuminate\Support\Facades\Route;

Route::post('/auth/login', [AuthController::class, 'login']);

Route::get('/parapheur/documents/{document}/versions/{version}/download', [DocumentController::class, 'downloadVersion'])
    ->name('documents.version.download')
    ->middleware('signed');
Route::get('/parapheur/documents/{document}/versions/{version}/stream', [DocumentController::class, 'streamVersion'])
    ->name('documents.version.stream')
    ->middleware('signed');

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::get('/meta/document-types', [MetaController::class, 'documentTypes']);
    Route::get('/meta/structures', [MetaController::class, 'structures']);
    Route::get('/meta/users', [MetaController::class, 'users']);

    Route::get('/dashboard/dg', [DashboardController::class, 'dg']);
    Route::get('/dashboard/direction', [DashboardController::class, 'direction']);

    Route::get('/parapheur/counts', [DocumentController::class, 'counts']);
    Route::get('/parapheur/documents', [DocumentController::class, 'index']);
    Route::post('/parapheur/documents', [DocumentController::class, 'store']);
    Route::get('/parapheur/documents/{document}', [DocumentController::class, 'show']);
    Route::post('/parapheur/documents/{document}/transmit', [DocumentController::class, 'transmit']);
    Route::post('/parapheur/documents/{document}/comments', [DocumentController::class, 'comment']);
    Route::post('/parapheur/documents/{document}/return', [DocumentController::class, 'returnCorrection']);
    Route::post('/parapheur/documents/{document}/vise', [DocumentController::class, 'vise']);
    Route::post('/parapheur/documents/{document}/validate', [DocumentController::class, 'validateAction']);
    Route::post('/parapheur/documents/{document}/reject', [DocumentController::class, 'reject']);
    Route::post('/parapheur/documents/{document}/archive', [DocumentController::class, 'archive']);
    Route::post('/parapheur/documents/{document}/versions', [DocumentController::class, 'addVersion']);
    Route::post('/parapheur/documents/{document}/instructions', [DocumentController::class, 'createInstruction']);

    Route::get('/instructions', [InstructionController::class, 'index']);
    Route::patch('/instructions/{instruction}/status', [InstructionController::class, 'updateStatus']);

    Route::get('/delegations', [DelegationController::class, 'index']);
    Route::post('/delegations', [DelegationController::class, 'store']);
    Route::patch('/delegations/{delegation}', [DelegationController::class, 'update']);

    Route::get('/meetings', [MeetingController::class, 'index']);
    Route::post('/meetings', [MeetingController::class, 'store']);
    Route::get('/meetings/{meeting}', [MeetingController::class, 'show']);
    Route::post('/meetings/{meeting}/decisions', [MeetingController::class, 'addDecision']);
});
