<?php

namespace App\Console\Commands;

use App\Services\WorkspaceQuotaService;
use Illuminate\Console\Command;

class PurgeWorkspaceTrashCommand extends Command
{
    protected $signature = 'parapheur:purge-workspace-trash';

    protected $description = 'Purge définitive de la corbeille Mon espace selon la rétention de la politique de stockage';

    public function handle(WorkspaceQuotaService $quotas): int
    {
        $this->info('Purge de la corbeille Mon espace…');

        $result = $quotas->purgeExpiredTrash();

        $this->info(sprintf(
            'Terminé : %d dossier(s), %d document(s) purgés.',
            $result['folders'],
            $result['documents'],
        ));

        return self::SUCCESS;
    }
}
