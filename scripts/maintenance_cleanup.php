<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$result = [];

$stmt = $pdo->prepare('DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
$stmt->execute();
$result['login_attempts_deleted'] = $stmt->rowCount();

$stmt = $pdo->prepare('DELETE FROM user_sessions WHERE (expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)) OR (revoked_at IS NOT NULL AND revoked_at < DATE_SUB(NOW(), INTERVAL 7 DAY))');
$stmt->execute();
$result['user_sessions_deleted'] = $stmt->rowCount();

$stmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY) OR (used_at IS NOT NULL AND used_at < DATE_SUB(NOW(), INTERVAL 1 DAY))');
$stmt->execute();
$result['password_reset_tokens_deleted'] = $stmt->rowCount();

$result['timestamp'] = date(DATE_ATOM);

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
