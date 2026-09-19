<?php

namespace App\Http\Controllers\Api\Ticketing;

use App\Http\Controllers\Controller;
use App\Services\Ticketing\TicketReportingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketingReportController extends Controller
{
    public function __construct(
        private readonly TicketReportingService $reporting,
    ) {}

    public function volume(Request $request): JsonResponse|StreamedResponse
    {
        $this->authorizeReports($request);
        [$from, $to] = $this->period($request);
        $data = $this->reporting->reportVolume($from, $to);

        return $this->respond($request, $data, ['day', 'total'], 'volume');
    }

    public function sla(Request $request): JsonResponse|StreamedResponse
    {
        $this->authorizeReports($request);
        [$from, $to] = $this->period($request);
        $data = $this->reporting->reportSla($from, $to);

        if ($this->wantsCsv($request)) {
            return $this->csvFromAssoc($data, 'sla');
        }

        return response()->json($data);
    }

    public function satisfaction(Request $request): JsonResponse|StreamedResponse
    {
        $this->authorizeReports($request);
        [$from, $to] = $this->period($request);
        $data = $this->reporting->reportSatisfaction($from, $to);

        if ($this->wantsCsv($request)) {
            $rows = collect($data['distribution'] ?? [])->map(fn ($total, $score) => [
                'score' => $score,
                'total' => $total,
            ])->values()->all();

            return $this->csv($rows, ['score', 'total'], 'satisfaction');
        }

        return response()->json($data);
    }

    public function byCategory(Request $request): JsonResponse|StreamedResponse
    {
        $this->authorizeReports($request);
        [$from, $to] = $this->period($request);
        $data = $this->reporting->reportByCategory($from, $to);

        return $this->respond($request, $data, ['ticket_category_id', 'total'], 'by_category');
    }

    private function authorizeReports(Request $request): void
    {
        abort_unless(
            $request->user()->can('ticket.view_reports')
            || $request->user()->can('ticket.admin')
            || $request->user()->can('admin.access'),
            403
        );
    }

    /**
     * @return array{0: ?string, 1: ?string}
     */
    private function period(Request $request): array
    {
        $validated = $request->validate([
            'from' => 'nullable|date',
            'to' => 'nullable|date',
            'export' => 'nullable|string',
        ]);

        return [$validated['from'] ?? null, $validated['to'] ?? null];
    }

    private function wantsCsv(Request $request): bool
    {
        if (strtolower((string) $request->input('export')) === 'csv') {
            return true;
        }

        $accept = (string) $request->header('Accept', '');

        return str_contains($accept, 'text/csv');
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $headers
     */
    private function respond(Request $request, array $rows, array $headers, string $name): JsonResponse|StreamedResponse
    {
        if ($this->wantsCsv($request)) {
            return $this->csv($rows, $headers, $name);
        }

        return response()->json($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  list<string>  $headers
     */
    private function csv(array $rows, array $headers, string $name): StreamedResponse
    {
        return response()->streamDownload(function () use ($rows, $headers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers, ';');
            foreach ($rows as $row) {
                fputcsv($out, array_map(fn ($h) => $row[$h] ?? '', $headers), ';');
            }
            fclose($out);
        }, "ticketing_{$name}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function csvFromAssoc(array $data, string $name): StreamedResponse
    {
        $rows = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $rows[] = ['metric' => $key, 'value' => $value];
        }

        return $this->csv($rows, ['metric', 'value'], $name);
    }
}
