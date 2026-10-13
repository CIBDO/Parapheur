<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Services\Announcements\AnnouncementAccess;
use App\Services\Announcements\AnnouncementService;
use App\Services\Announcements\AnnouncementStatisticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AnnouncementController extends Controller
{
    public function __construct(
        private readonly AnnouncementService $announcements,
        private readonly AnnouncementAccess $access,
        private readonly AnnouncementStatisticsService $statistics,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);
        $page = $this->announcements->feed($request->user(), $request->query());

        return response()->json($this->page($page, $request));
    }

    public function managed(Request $request): JsonResponse
    {
        $this->authorize('create', Announcement::class);
        $page = $this->announcements->managed($request->user(), $request->query());

        return response()->json($this->page($page, $request));
    }

    public function banner(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Announcement::class);
        $items = $this->announcements->banner($request->user())
            ->map(fn (Announcement $announcement) => $this->announcements->present($announcement, $request->user()))
            ->values();

        return response()->json($items);
    }

    public function statistics(Request $request): JsonResponse
    {
        return response()->json($this->statistics->summarize($request->user()));
    }

    public function preview(Request $request): JsonResponse
    {
        $this->authorize('create', Announcement::class);
        $data = $request->validate([
            'audiences' => ['required', 'array', 'min:1'],
            'audiences.*.mode' => ['required', 'in:include,exclude'],
            'audiences.*.target_type' => ['required', 'in:all,structure,role,user'],
            'audiences.*.structure_id' => ['nullable', 'integer', 'exists:structures,id'],
            'audiences.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'audiences.*.role_name' => ['nullable', 'string', 'max:100'],
            'audiences.*.include_descendants' => ['sometimes', 'boolean'],
        ]);

        return response()->json($this->announcements->previewRules($data['audiences']));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Announcement::class);
        $announcement = $this->announcements->create($request->user(), $this->validated($request));

        return response()->json($this->announcements->present($announcement, $request->user(), true), 201);
    }

    public function show(Request $request, int $announcement): JsonResponse
    {
        $model = $this->findVisible($request, $announcement);
        if ($model->status->value === 'published') {
            $this->announcements->markRead($request->user(), $model);
        }
        $model->load([
            'category', 'importance', 'structure', 'author', 'audiences', 'links',
            'attachments.document', 'approvals.actor', 'revisions', 'events.actor',
            'reads', 'acknowledgements', 'favorites',
        ]);

        return response()->json($this->announcements->present($model, $request->user(), true));
    }

    public function update(Request $request, int $announcement): JsonResponse
    {
        $model = $this->findVisible($request, $announcement);
        $updated = $this->announcements->update($request->user(), $model, $this->validated($request, false));

        return response()->json($this->announcements->present($updated, $request->user(), true));
    }

    public function submit(Request $request, int $announcement): JsonResponse
    {
        $model = $this->findVisible($request, $announcement);

        return response()->json($this->announcements->present(
            $this->announcements->submit($request->user(), $model),
            $request->user(),
            true
        ));
    }

    public function approve(Request $request, int $announcement): JsonResponse
    {
        $model = $this->findVisible($request, $announcement);
        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:2000']])['comment'] ?? null;

        return response()->json($this->announcements->present(
            $this->announcements->approve($request->user(), $model, $comment),
            $request->user(),
            true
        ));
    }

    public function reject(Request $request, int $announcement): JsonResponse
    {
        $model = $this->findVisible($request, $announcement);
        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:2000']])['comment'] ?? null;

        return response()->json($this->announcements->present(
            $this->announcements->reject($request->user(), $model, $comment),
            $request->user(),
            true
        ));
    }

    public function requestCorrection(Request $request, int $announcement): JsonResponse
    {
        $model = $this->findVisible($request, $announcement);
        $comment = $request->validate(['comment' => ['nullable', 'string', 'max:2000']])['comment'] ?? null;

        return response()->json($this->announcements->present(
            $this->announcements->requestCorrection($request->user(), $model, $comment),
            $request->user(),
            true
        ));
    }

    public function publish(Request $request, int $announcement): JsonResponse
    {
        $model = $this->findVisible($request, $announcement);

        return response()->json($this->announcements->present(
            $this->announcements->publish($request->user(), $model),
            $request->user(),
            true
        ));
    }

    public function schedule(Request $request, int $announcement): JsonResponse
    {
        $model = $this->findVisible($request, $announcement);
        $data = $request->validate(['publish_at' => ['required', 'date']]);
        $when = \Carbon\Carbon::parse($data['publish_at'], 'Africa/Bamako')->utc();

        return response()->json($this->announcements->present(
            $this->announcements->schedule($request->user(), $model, $when),
            $request->user(),
            true
        ));
    }

    public function unpublish(Request $request, int $announcement): JsonResponse
    {
        $model = $this->findVisible($request, $announcement);

        return response()->json($this->announcements->present(
            $this->announcements->unpublish($request->user(), $model),
            $request->user(),
            true
        ));
    }

    public function archive(Request $request, int $announcement): JsonResponse
    {
        $model = $this->findVisible($request, $announcement);

        return response()->json($this->announcements->present(
            $this->announcements->archive($request->user(), $model),
            $request->user(),
            true
        ));
    }

    public function acknowledge(Request $request, int $announcement): JsonResponse
    {
        $model = $this->findVisible($request, $announcement);
        $this->announcements->acknowledge($request->user(), $model);

        return response()->json(['acknowledged' => true]);
    }

    public function dismiss(Request $request, int $announcement): JsonResponse
    {
        $model = $this->findVisible($request, $announcement);
        $this->announcements->dismiss($request->user(), $model);

        return response()->json(['dismissed' => true]);
    }

    public function favorite(Request $request, int $announcement): JsonResponse
    {
        $model = $this->findVisible($request, $announcement);

        return response()->json([
            'favorite' => $this->announcements->toggleFavorite($request->user(), $model),
        ]);
    }

    public function receipts(Request $request, int $announcement): JsonResponse|StreamedResponse
    {
        $model = $this->findVisible($request, $announcement);
        $payload = $this->announcements->receipts($request->user(), $model);
        if ($request->query('format') === 'csv') {
            if (! $request->user()->can('announcement.export') && ! $request->user()->can('announcement.manage')) {
                abort(403);
            }

            return response()->streamDownload(function () use ($payload) {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, ['agent', 'consulte_le', 'confirme_le', 'version']);
                foreach ($payload['items'] as $item) {
                    fputcsv($handle, [
                        $item['name'],
                        $item['consulted_at'],
                        $item['acknowledged_at'],
                        $item['version'],
                    ]);
                }
                fclose($handle);
            }, 'accuses-'.$model->reference.'.csv', ['Content-Type' => 'text/csv']);
        }

        return response()->json($payload);
    }

    public function remind(Request $request, int $announcement): JsonResponse
    {
        $model = $this->findVisible($request, $announcement);
        if (! $this->access->canViewReceipts($request->user(), $model)) {
            abort(403);
        }

        $sent = app(\App\Services\Announcements\AnnouncementNotificationService::class)->remindAnnouncement($model);

        return response()->json(['sent' => $sent]);
    }

    private function findVisible(Request $request, int $id): Announcement
    {
        $model = Announcement::query()->find($id);
        if (! $model || ! $this->access->canView($request->user(), $model)) {
            abort(404);
        }

        return $model;
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, bool $creating = true): array
    {
        return $request->validate([
            'title' => [$creating ? 'required' : 'sometimes', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string'],
            'category_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:announcement_categories,id'],
            'importance_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:announcement_importances,id'],
            'structure_id' => [$creating ? 'required' : 'sometimes', 'integer', 'exists:structures,id'],
            'validator_id' => ['nullable', 'integer', 'exists:users,id'],
            'confidentiality' => ['sometimes', 'in:general,targeted,restricted,confidential'],
            'publish_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'acknowledgement_required' => ['sometimes', 'boolean'],
            'banner_enabled' => ['sometimes', 'boolean'],
            'banner_priority' => ['sometimes', 'integer', 'min:0', 'max:100'],
            'banner_starts_at' => ['nullable', 'date'],
            'banner_ends_at' => ['nullable', 'date'],
            'banner_scroll' => ['sometimes', 'boolean'],
            'banner_duration_seconds' => ['sometimes', 'integer', 'min:3', 'max:60'],
            'dismissible' => ['sometimes', 'boolean'],
            'audiences' => [$creating ? 'required' : 'sometimes', 'array', 'min:1'],
            'audiences.*.mode' => ['required_with:audiences', 'in:include,exclude'],
            'audiences.*.target_type' => ['required_with:audiences', 'in:all,structure,role,user'],
            'audiences.*.structure_id' => ['nullable', 'integer', 'exists:structures,id'],
            'audiences.*.user_id' => ['nullable', 'integer', 'exists:users,id'],
            'audiences.*.role_name' => ['nullable', 'string', 'max:100'],
            'audiences.*.include_descendants' => ['sometimes', 'boolean'],
            'links' => ['sometimes', 'array'],
            'links.*.label' => ['required_with:links', 'string', 'max:255'],
            'links.*.url' => ['required_with:links', 'string', 'max:255'],
            'document_ids' => ['sometimes', 'array'],
            'document_ids.*' => ['integer', 'exists:documents,id'],
        ]);
    }

    private function page($page, Request $request): array
    {
        return [
            'data' => collect($page->items())->map(fn (Announcement $announcement) => $this->announcements->present($announcement, $request->user()))->values(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'total' => $page->total(),
            ],
        ];
    }
}
