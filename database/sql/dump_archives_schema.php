<?php

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$tables = [
    'archive_fonds',
    'archive_series',
    'archive_classification_nodes',
    'archive_transfers',
    'archive_transfer_items',
    'archive_records',
    'archive_holds',
    'archive_storage_locations',
    'archive_boxes',
    'archive_box_contents',
    'archive_movements',
    'archive_loans',
    'archive_inventories',
    'archive_inventory_items',
    'archive_retention_rules',
    'archive_retention_events',
    'archive_communication_requests',
    'archive_access_grants',
    'archive_disposition_requests',
    'archive_disposition_items',
    'archive_disposition_approvals',
    'archive_packages',
    'archive_package_items',
    'archive_integrity_checks',
    'archive_preservation_events',
    'archive_imports',
    'archive_preventive_events',
    'archive_climate_readings',
];

$out = "-- Tables archives créées depuis 2026-11-18 (migrations 2026_11_18_*)\n";
$out .= "-- Dump depuis le schéma local. CREATE TABLE IF NOT EXISTS pour exécution safe en prod.\n";
$out .= "-- Aussi : INSERT dans `migrations` après exécution (voir bas de fichier).\n\n";
$out .= "SET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n";

foreach ($tables as $t) {
    try {
        $row = Illuminate\Support\Facades\DB::selectOne('SHOW CREATE TABLE `'.$t.'`');
        $create = $row->{'Create Table'} ?? null;
        if (! $create) {
            $out .= "-- SKIP {$t}\n\n";
            continue;
        }
        $create = preg_replace('/^CREATE TABLE `/', 'CREATE TABLE IF NOT EXISTS `', $create);
        $out .= "-- {$t}\n{$create};\n\n";
        echo "OK {$t}\n";
    } catch (Throwable $e) {
        $out .= '-- ERROR '.$t.': '.$e->getMessage()."\n\n";
        echo "ERR {$t}: {$e->getMessage()}\n";
    }
}

$out .= "SET FOREIGN_KEY_CHECKS=1;\n\n";
$out .= "-- Enregistrer les migrations comme exécutées (si vous n'utilisez pas artisan migrate)\n";
$out .= "SET @batch := (SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations);\n";
$out .= "INSERT IGNORE INTO `migrations` (`migration`, `batch`) VALUES\n";
$out .= "('2026_11_18_100000_create_archives_module_tables', @batch),\n";
$out .= "('2026_11_18_200000_create_archives_physical_tables', @batch),\n";
$out .= "('2026_11_18_300000_create_archives_governance_tables', @batch),\n";
$out .= "('2026_11_18_400000_create_archives_preservation_tables', @batch),\n";
$out .= "('2026_11_18_500000_archives_production_completeness', @batch);\n";

file_put_contents(__DIR__.'/archives_tables_since_2026_11_18.sql', $out);
echo 'Wrote '.strlen($out)." bytes\n";
