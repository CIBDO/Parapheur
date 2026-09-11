<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Services\DocumentAccessService;
use App\Services\OnlyOffice\OnlyOfficeJwt;
use App\Services\OnlyOffice\OnlyOfficeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

class OnlyOfficeController extends Controller
{
    public function __construct(
        private readonly OnlyOfficeService $onlyOffice,
        private readonly DocumentAccessService $access,
        private readonly OnlyOfficeJwt $jwt,
    ) {}

    public function config(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);
        abort_unless($this->onlyOffice->isEnabled(), 503, 'ONLYOFFICE désactivé.');

        $version = null;
        if ($request->filled('version_id')) {
            $version = DocumentVersion::query()
                ->where('document_id', $document->id)
                ->findOrFail($request->integer('version_id'));
        }

        try {
            $payload = $this->onlyOffice->editorConfig($document, $request->user(), $version);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($payload);
    }

    public function history(Request $request, Document $document): JsonResponse
    {
        $this->access->authorize($request->user(), $document);
        abort_unless($this->onlyOffice->isEnabled(), 503, 'ONLYOFFICE désactivé.');

        return response()->json($this->onlyOffice->historyPayload($document, $request->user()));
    }

    public function historyData(Request $request, Document $document, int $versionNumber): JsonResponse
    {
        $this->access->authorize($request->user(), $document);
        abort_unless($this->onlyOffice->isEnabled(), 503, 'ONLYOFFICE désactivé.');

        $version = DocumentVersion::query()
            ->where('document_id', $document->id)
            ->where('version_number', $versionNumber)
            ->firstOrFail();

        return response()->json(
            $this->onlyOffice->historyData($document, $version, $request->user())
        );
    }

    public function compare(Request $request, Document $document, int $versionNumber): JsonResponse
    {
        $this->access->authorize($request->user(), $document);
        abort_unless($this->onlyOffice->isEnabled(), 503, 'ONLYOFFICE désactivé.');

        $version = DocumentVersion::query()
            ->where('document_id', $document->id)
            ->where('version_number', $versionNumber)
            ->firstOrFail();

        return response()->json(
            $this->onlyOffice->compareFile($document, $version, $request->user())
        );
    }

    public function restore(Request $request, Document $document, int $versionNumber): JsonResponse
    {
        $this->access->authorize($request->user(), $document);
        abort_unless($this->onlyOffice->isEnabled(), 503, 'ONLYOFFICE désactivé.');
        abort_unless(
            $request->user()->can('documents.act') || $request->user()->can('admin.access'),
            403
        );

        $version = DocumentVersion::query()
            ->where('document_id', $document->id)
            ->where('version_number', $versionNumber)
            ->firstOrFail();

        try {
            $newVersion = $this->onlyOffice->restoreAsNewVersion($document, $version, $request->user());
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($newVersion, 201);
    }

    /**
     * Callback Document Server — hors Sanctum, authentifié par JWT.
     */
    public function callback(Request $request, Document $document): JsonResponse
    {
        $headerName = (string) config('onlyoffice.jwt_header', 'Authorization');
        $token = $this->jwt->tokenFromRequestHeader($request->header($headerName));
        if (! $token && strcasecmp($headerName, 'Authorization') !== 0) {
            $token = $this->jwt->tokenFromRequestHeader($request->header('Authorization'));
        }
        if (! $token) {
            $token = $this->jwt->tokenFromRequestHeader($request->header('AuthorizationJwt'));
        }
        if (! $token && $request->filled('token')) {
            $token = (string) $request->input('token');
        }

        $body = $request->all();

        try {
            $result = $this->onlyOffice->handleCallback($document, $body, $token);
        } catch (InvalidArgumentException $e) {
            return response()->json(['error' => 1, 'message' => $e->getMessage()], 403);
        } catch (Throwable $e) {
            report($e);

            return response()->json(['error' => 1, 'message' => 'Erreur callback ONLYOFFICE.'], 500);
        }

        return response()->json($result);
    }
}
