<?php

namespace App\Http\Controllers\Api\Ged;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Document;
use App\Models\DocumentFolder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GedAuditController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()?->can('ged.view_audit') || $request->user()?->can('admin.access'),
            403
        );

        $query = AuditLog::query()
            ->with('user:id,name,email')
            ->where(function ($q) {
                $q->where('auditable_type', Document::class)
                    ->orWhere('auditable_type', DocumentFolder::class)
                    ->orWhere('action', 'like', 'document.%')
                    ->orWhere('action', 'like', 'document_folder.%')
                    ->orWhere('action', 'like', 'document_category.%')
                    ->orWhere('action', 'like', 'document_domain.%')
                    ->orWhere('action', 'like', 'classification_node.%')
                    ->orWhere('action', 'like', 'workspace.submitted_to_ged');
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        if ($request->filled('action')) {
            $query->where('action', 'like', '%'.$request->string('action')->toString().'%');
        }

        if ($request->filled('document_id')) {
            $query->where('auditable_type', Document::class)
                ->where('auditable_id', $request->integer('document_id'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        return response()->json($query->paginate($request->integer('per_page', 25)));
    }
}
