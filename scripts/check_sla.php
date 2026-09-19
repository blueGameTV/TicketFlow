<?php

declare(strict_types=1);

use App\Database\Database;
use App\Services\NotificationService;
use App\Services\SlaService;

require_once __DIR__ . '/../src/Database/Database.php';
require_once __DIR__ . '/../src/Services/NotificationService.php';
require_once __DIR__ . '/../src/Services/SlaService.php';

$configFile = __DIR__ . '/../config/config.php';
if (!is_file($configFile)) {
    fwrite(STDERR, "Configuration manquante : config/config.php\n");
    exit(1);
}

$config = require $configFile;
$db = new Database($config['database'] ?? []);
$pdo = $db->pdo();
$notifications = new NotificationService($pdo);
$sla = new SlaService($pdo, $notifications);

$result = $sla->checkBreaches();

echo 'Tickets contrôlés : ' . $result['checked'] . PHP_EOL;
echo 'Dépassements prise en charge notifiés : ' . $result['response_breaches'] . PHP_EOL;
echo 'Dépassements résolution notifiés : ' . $result['resolution_breaches'] . PHP_EOL;
