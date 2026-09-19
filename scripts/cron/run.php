<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

$start = microtime(true);
$sla = $slaService->checkBreaches();
$automation = $automationService->run();
$cleanup = $notificationService->purgeReadOlderThanDays(1);
$mail = $mailQueueService->process(50);

$cleanupStmt = $pdo->prepare('DELETE FROM login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)');
$cleanupStmt->execute();
$loginAttemptsDeleted = $cleanupStmt->rowCount();

$cleanupStmt = $pdo->prepare('DELETE FROM user_sessions WHERE (expires_at < DATE_SUB(NOW(), INTERVAL 7 DAY)) OR (revoked_at IS NOT NULL AND revoked_at < DATE_SUB(NOW(), INTERVAL 7 DAY))');
$cleanupStmt->execute();
$userSessionsDeleted = $cleanupStmt->rowCount();

$cleanupStmt = $pdo->prepare('DELETE FROM password_reset_tokens WHERE expires_at < DATE_SUB(NOW(), INTERVAL 1 DAY) OR (used_at IS NOT NULL AND used_at < DATE_SUB(NOW(), INTERVAL 1 DAY))');
$cleanupStmt->execute();
$passwordResetTokensDeleted = $cleanupStmt->rowCount();

$result = [
    'timestamp' => date(DATE_ATOM),
    'sla' => $sla,
    'automation' => $automation,
    'notifications_purged' => $cleanup,
    'mail' => $mail,
    'security_cleanup' => [
        'login_attempts_deleted' => $loginAttemptsDeleted,
        'user_sessions_deleted' => $userSessionsDeleted,
        'password_reset_tokens_deleted' => $passwordResetTokensDeleted,
    ],
    'duration_ms' => (int) round((microtime(true) - $start) * 1000),
];

echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
