<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateOnlyOfficeJwtSecretCommand extends Command
{
    protected $signature = 'onlyoffice:generate-jwt-secret
                            {--show : Afficher uniquement (ne pas écrire de fichier)}';

    protected $description = 'Génère un secret JWT OnlyOffice (≥ 32 octets hex) pour rotation';

    public function handle(): int
    {
        $secret = bin2hex(random_bytes(32));

        $this->info('Nouveau secret JWT OnlyOffice :');
        $this->line($secret);
        $this->newLine();
        $this->comment('1. Mettre l’ancien secret dans ONLYOFFICE_JWT_SECRET_PREVIOUS (rotation douce).');
        $this->comment('2. Mettre ce secret dans ONLYOFFICE_JWT_SECRET et JWT_SECRET du conteneur Docker.');
        $this->comment('3. Redémarrer Document Server, puis retirer ONLYOFFICE_JWT_SECRET_PREVIOUS.');

        if ($this->option('show')) {
            return self::SUCCESS;
        }

        $this->newLine();
        $this->warn('Secret non écrit automatiquement dans .env (sécurité). Copiez-le manuellement.');

        return self::SUCCESS;
    }
}
