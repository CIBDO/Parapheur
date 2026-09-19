<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeArticle;
use App\Models\Ticket;
use App\Services\Ticketing\KnowledgeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KnowledgeController extends Controller
{
    public function __construct(
        private readonly KnowledgeService $knowledge,
    ) {}

    public function index(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('knowledge.view')
            || $request->user()->can('ticket.view')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        return response()->json($this->knowledge->list($request->only([
            'q', 'status', 'knowledge_category_id', 'per_page',
        ])));
    }

    public function store(Request $request): JsonResponse
    {
        abort_unless(
            $request->user()->can('knowledge.create')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        $validated = $request->validate([
            'title' => 'required|string|max:500',
            'slug' => 'nullable|string|max:255',
            'summary' => 'nullable|string',
            'body' => 'nullable|string',
            'knowledge_category_id' => 'nullable|exists:knowledge_categories,id',
            'status' => 'nullable|string|max:50',
        ]);

        $article = $this->knowledge->create($request->user(), $validated);

        return response()->json($article, 201);
    }

    public function show(Request $request, KnowledgeArticle $article): JsonResponse
    {
        abort_unless(
            $request->user()->can('knowledge.view')
            || $request->user()->can('ticket.view')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        $article->load(['category', 'author:id,name', 'tickets:id,number,title']);

        return response()->json($article);
    }

    public function update(Request $request, KnowledgeArticle $article): JsonResponse
    {
        abort_unless(
            $request->user()->can('knowledge.update')
            || $request->user()->can('knowledge.review')
            || $request->user()->can('knowledge.create')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        $validated = $request->validate([
            'title' => 'sometimes|string|max:500',
            'slug' => 'nullable|string|max:255',
            'summary' => 'nullable|string',
            'body' => 'nullable|string',
            'knowledge_category_id' => 'nullable|exists:knowledge_categories,id',
            'status' => 'nullable|string|max:50',
        ]);

        return response()->json($this->knowledge->update($article, $validated));
    }

    public function publish(Request $request, KnowledgeArticle $article): JsonResponse
    {
        abort_unless(
            $request->user()->can('knowledge.publish')
            || $request->user()->can('knowledge.review')
            || $request->user()->can('knowledge.update')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        return response()->json($this->knowledge->publish($article));
    }

    public function linkTicket(Request $request, KnowledgeArticle $article): JsonResponse
    {
        abort_unless(
            $request->user()->can('knowledge.update')
            || $request->user()->can('knowledge.create')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );

        $validated = $request->validate([
            'ticket_id' => 'required|exists:tickets,id',
        ]);

        $ticket = Ticket::query()->findOrFail($validated['ticket_id']);

        return response()->json($this->knowledge->linkTicket($article, $ticket));
    }
}
