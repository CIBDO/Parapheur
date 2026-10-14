<?php

namespace App\Http\Controllers\Api\Mail;

use App\Http\Controllers\Controller;
use App\Services\Organization\OrganizationalContextResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MailWorkContextController extends Controller
{
    public function __construct(
        private readonly OrganizationalContextResolver $contexts,
    ) {}

    public function show(Request $request): JsonResponse
    {
        return response()->json($this->contexts->present($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'assignment_id' => ['required', 'integer', 'exists:organizational_role_assignments,id'],
        ]);

        $this->contexts->switch($request->user(), (int) $validated['assignment_id']);

        return response()->json($this->contexts->present($request->user()->fresh()));
    }
}
