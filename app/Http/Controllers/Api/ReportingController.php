<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ParapheurService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportingController extends Controller
{
    public function __construct(private readonly ParapheurService $parapheur) {}

    public function export(Request $request): StreamedResponse|Response
    {
        abort_unless(
            $request->user()->can('reporting.view') || $request->user()->can('admin.access'),
            403
        );

        $format = $request->string('format')->toString() ?: 'csv';
        $scope = $request->string('scope')->toString() ?: 'dg';

        $payload = $scope === 'direction'
            ? $this->directionPayload($request)
            : $this->parapheur->dashboardDg();

        if ($format === 'pdf') {
            return $this->pdfResponse($payload, $scope);
        }

        return $this->csvResponse($payload, $scope);
    }

    private function directionPayload(Request $request): array
    {
        $structureId = $request->user()->structure_id;

        return [
            'prepared' => DB::table('documents')->where('structure_id', $structureId)->count(),
            'in_validation' => DB::table('documents')->where('structure_id', $structureId)->whereIn('status', ['en_circuit', 'a_viser', 'a_valider'])->count(),
            'sent_dg' => DB::table('documents')->where('structure_id', $structureId)->whereIn('status', ['transmis', 'a_consulter', 'a_valider'])->count(),
            'returned' => DB::table('documents')->where('structure_id', $structureId)->where('status', 'a_corriger')->count(),
            'validated' => DB::table('documents')->where('structure_id', $structureId)->where('status', 'valide')->count(),
            'overdue' => DB::table('documents')->where('structure_id', $structureId)->whereNotNull('due_date')->whereDate('due_date', '<', now())->whereNotIn('status', ['valide', 'archive', 'traite'])->count(),
        ];
    }

    private function csvResponse(array $payload, string $scope): StreamedResponse
    {
        $filename = 'reporting_'.$scope.'_'.now()->format('Ymd_His').'.csv';

        return response()->streamDownload(function () use ($payload) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Indicateur', 'Valeur'], ';');

            foreach ($payload as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    foreach ((array) $value as $subKey => $subValue) {
                        fputcsv($out, [$key.'.'.$subKey, $subValue], ';');
                    }
                } else {
                    fputcsv($out, [$key, $value], ';');
                }
            }

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function pdfResponse(array $payload, string $scope): Response
    {
        $title = $scope === 'direction' ? 'Reporting Direction' : 'Reporting DG';
        $rows = '';
        foreach ($payload as $key => $value) {
            if (is_array($value) || is_object($value)) {
                foreach ((array) $value as $subKey => $subValue) {
                    $rows .= '<tr><td>'.e($key.'.'.$subKey).'</td><td>'.e((string) $subValue).'</td></tr>';
                }
            } else {
                $rows .= '<tr><td>'.e($key).'</td><td>'.e((string) $value).'</td></tr>';
            }
        }

        $html = <<<HTML
<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <title>{$title}</title>
  <style>
    body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #14261A; }
    h1 { color: #0B6B3A; }
    table { width: 100%; border-collapse: collapse; margin-top: 16px; }
    th, td { border: 1px solid #ccc; padding: 8px; text-align: left; }
    th { background: #E8F5EE; }
  </style>
</head>
<body>
  <h1>{$title}</h1>
  <p>Export généré le {$this->nowLabel()}</p>
  <table>
    <thead><tr><th>Indicateur</th><th>Valeur</th></tr></thead>
    <tbody>{$rows}</tbody>
  </table>
  <p style="margin-top:24px;font-size:10px;color:#666">E-Tresor — indicateurs CDC §32–33 (extrait Temps 1)</p>
</body>
</html>
HTML;

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="reporting_'.$scope.'_'.now()->format('Ymd_His').'.html"',
        ]);
    }

    private function nowLabel(): string
    {
        return now()->format('d/m/Y H:i');
    }
}