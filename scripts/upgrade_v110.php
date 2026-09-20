<?php

declare(strict_types=1);

use App\Database\Database;

require_once __DIR__ . '/../src/Database/Database.php';

$root = dirname(__DIR__);
$configFile = $root . '/config/config.php';
$migrationFile = $root . '/database/migrations/v110_service_alerts_maintenance.sql';

if (!is_file($configFile)) {
    fwrite(STDERR, "[ERREUR] config/config.php introuvable.\n");
    exit(1);
}
if (!is_file($migrationFile)) {
    fwrite(STDERR, "[ERREUR] Migration v1.1.0 introuvable.\n");
    exit(1);
}

$config = require $configFile;
$pdo = (new Database($config['database'] ?? []))->pdo();

try {
    $pdo->exec((string) file_get_contents($migrationFile));
    foreach (['service_alerts', 'maintenance_windows', 'user_release_views'] as $table) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :table');
        $stmt->execute(['table' => $table]);
        if ((int) $stmt->fetchColumn() !== 1) throw new RuntimeException('Table manquante après migration : ' . $table);
    }

    $columns = $pdo->query("SELECT COLUMN_NAME, CHARACTER_MAXIMUM_LENGTH FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'service_alerts' AND column_name IN ('title','message')")->fetchAll();
    $lengths = [];
    foreach ($columns as $row) $lengths[(string)$row['COLUMN_NAME']] = (int)$row['CHARACTER_MAXIMUM_LENGTH'];
    if (($lengths['title'] ?? 0) !== 80 || ($lengths['message'] ?? 0) !== 2000) throw new RuntimeException('Longueurs des alertes incorrectes après migration.');

    echo "[OK] Migration TicketFlow v1.1.0 appliquée.\n";
} catch (Throwable $e) {
    fwrite(STDERR, '[ERREUR] Migration v1.1.0 impossible : ' . $e->getMessage() . "\n");
    exit(1);
}
