<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Méthode non autorisée.'], JSON_UNESCAPED_UNICODE);
    exit;
}

if (!$auth->check()) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Votre session a expiré. Reconnectez-vous.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$user = $auth->user();
if (!$user || empty($user['must_change_password'])) {
    http_response_code(409);
    echo json_encode(['ok' => false, 'message' => 'Le changement forcé du mot de passe n’est plus requis.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$token = $_POST['csrf_token'] ?? null;
if (!$csrf->validate(is_string($token) ? $token : null)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'La session du formulaire a expiré. Rechargez la page.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$new = (string)($_POST['new_password'] ?? '');
$confirm = (string)($_POST['confirm_password'] ?? '');
$errors = [];

if (mb_strlen($new) < 12) {
    $errors[] = 'Le mot de passe doit contenir au moins 12 caractères.';
}
if (!preg_match('/[A-Z]/', $new)) {
    $errors[] = 'Ajoutez au moins une lettre majuscule.';
}
if (!preg_match('/[a-z]/', $new)) {
    $errors[] = 'Ajoutez au moins une lettre minuscule.';
}
if (!preg_match('/\d/', $new)) {
    $errors[] = 'Ajoutez au moins un chiffre.';
}
if ($new !== $confirm) {
    $errors[] = 'Les deux mots de passe ne correspondent pas.';
}

$stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => (int)$user['id']]);
$currentHash = (string)$stmt->fetchColumn();
if ($new !== '' && $currentHash !== '' && password_verify($new, $currentHash)) {
    $errors[] = 'Le nouveau mot de passe doit être différent de l’ancien.';
}

if ($errors) {
    http_response_code(422);
    echo json_encode(['ok' => false, 'message' => implode(' ', $errors), 'errors' => $errors], JSON_UNESCAPED_UNICODE);
    exit;
}

$update = $pdo->prepare('UPDATE users SET password_hash = :hash, must_change_password = 0, password_changed_at = NOW(), locked_until = NULL WHERE id = :id');
$update->execute([
    'hash' => password_hash($new, PASSWORD_DEFAULT),
    'id' => (int)$user['id'],
]);

$auditService->log((int)$user['id'], 'forced_password_changed', 'user', (int)$user['id']);
$auth->refreshUser();

echo json_encode(['ok' => true, 'message' => 'Mot de passe modifié avec succès.'], JSON_UNESCAPED_UNICODE);
