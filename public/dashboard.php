<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireLogin();
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'Dashboard';

if (!in_array($user['role'], ['Administrateur','IT','Manager','Collaborateur'], true)) {
    http_response_code(403);
    exit('Rôle utilisateur non reconnu.');
}

require __DIR__ . '/../templates/shared/header.php';
require __DIR__ . '/../templates/dashboard.php';
require __DIR__ . '/../templates/shared/footer.php';
