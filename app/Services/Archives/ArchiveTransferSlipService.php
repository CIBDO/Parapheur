<?php

namespace App\Services\Archives;

use App\Models\ArchiveTransfer;
use Barryvdh\DomPDF\Facade\Pdf;
use Symfony\Component\HttpFoundation\Response;

class ArchiveTransferSlipService
{
    public function download(ArchiveTransfer $transfer): Response
    {
        $transfer->loadMissing([
            'items.document',
            'structure',
            'producerUnit',
            'creator',
        ]);

        $pdf = Pdf::loadView('archives.transfer-slip', [
            'transfer' => $transfer,
        ])->setPaper('a4');

        $filename = 'bordereau-versement-'.preg_replace('/[^A-Za-z0-9_-]+/', '-', $transfer->reference).'.pdf';

        return $pdf->download($filename);
    }
}
