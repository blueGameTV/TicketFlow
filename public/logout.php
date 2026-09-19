<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Méthode non autorisée.');
}

$token = $_POST['csrf_token'] ?? null;
if (!$csrf->validate(is_string($token) ? $token : null)) {
    http_response_code(403);
    exit('Jeton CSRF invalide.');
}

$auth->logout();
header('Location: login.php');
exit;
