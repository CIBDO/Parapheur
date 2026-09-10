<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Instruction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InstructionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Instruction::query()->with(['assignee', 'issuer', 'document', 'structure']);

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->boolean('mine')) {
            $query->where('assignee_id', $request->user()->id);
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function updateStatus(Request $request, Instruction $instruction): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['a_faire', 'en_cours', 'executee', 'cloturee'])],
            'body' => ['nullable', 'string'],
        ]);

        $instruction->status = $data['status'];
        if ($data['status'] === 'executee') {
            $instruction->completed_at = now();
        }
        if ($data['status'] === 'cloturee') {
            $instruction->closed_at = now();
        }
        $instruction->save();

        if ($instruction->meeting_decision_id && $data['status'] === 'executee') {
            $instruction->meetingDecision?->update([
                'status' => 'executee',
                'executed_at' => now(),
                'execution_declared_by' => $request->user()->id,
            ]);
        }
        if ($instruction->meeting_decision_id && $data['status'] === 'cloturee') {
            $instruction->meetingDecision?->update([
                'status' => 'cloturee',
                'execution_validated_by' => $request->user()->id,
                'execution_validated_at' => now(),
            ]);
        }

        if (! empty($data['body'])) {
            $instruction->updates()->create([
                'user_id' => $request->user()->id,
                'status' => $data['status'],
                'body' => $data['body'],
            ]);
        }

        return response()->json($instruction->fresh(['updates.user', 'assignee']));
    }
}
