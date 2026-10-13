<?php

namespace Database\Seeders;

use App\Models\PortalApp;
use Illuminate\Database\Seeder;

class PortalAppSeeder extends Seeder
{
    public function run(): void
    {
        $apps = [
            [
                'code' => 'SIGRAC',
                'name' => 'SIGRAC',
                'url' => 'https://sigrac.example.local',
                'icon' => 'tabler-building-bank',
                'color' => 'primary',
                'description' => 'Système de gestion des recettes et de la comptabilité publique',
                'category' => 'Métiers',
                'sort_order' => 1,
            ],
            [
                'code' => 'MESSAGERIE',
                'name' => 'Messagerie institutionnelle',
                'url' => 'https://mail.example.local',
                'icon' => 'tabler-mail',
                'color' => 'info',
                'description' => 'Boîte mail professionnelle de l’institution',
                'category' => 'Communication',
                'sort_order' => 2,
            ],
            [
                'code' => 'RH',
                'name' => 'Portail RH',
                'url' => 'https://rh.example.local',
                'icon' => 'tabler-users',
                'color' => 'success',
                'description' => 'Congés, notes de service et dossiers agents',
                'category' => 'RH',
                'sort_order' => 3,
            ],
            [
                'code' => 'INTRANET',
                'name' => 'Intranet',
                'url' => 'https://intranet.example.local',
                'icon' => 'tabler-world',
                'color' => 'warning',
                'description' => 'Espace d’information et de documentation interne',
                'category' => 'Communication',
                'sort_order' => 4,
            ],
            [
                'code' => 'ANNUAIRE',
                'name' => 'Annuaire',
                'url' => 'https://annuaire.example.local',
                'icon' => 'tabler-address-book',
                'color' => 'secondary',
                'description' => 'Coordonnées des structures et agents',
                'category' => 'Outils',
                'sort_order' => 5,
            ],
        ];

        foreach ($apps as $app) {
            PortalApp::query()->updateOrCreate(
                ['code' => $app['code']],
                array_merge($app, [
                    'is_active' => true,
                    'open_in_new_tab' => true,
                ]),
            );
        }
    }
}
