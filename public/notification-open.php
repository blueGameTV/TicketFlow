<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireLogin();
$user = $auth->user();
$id = (int) ($_GET['id'] ?? 0);

$notification = $id > 0 ? $notificationService->getForUser($id, (int) $user['id']) : null;
if (!$notification) {
    $_SESSION['flash_error'] = 'Notification introuvable.';
    header('Location: notifications.php');
    exit;
}

$notificationService->markRead($id, (int) $user['id']);
$link = trim((string) ($notification['link_url'] ?? ''));

// N'autoriser que les liens relatifs internes à TicketFlow.
if ($link === '' || str_starts_with($link, '//') || preg_match('#^[a-z][a-z0-9+.-]*:#i', $link)) {
    $link = 'notifications.php';
}

header('Location: ' . $link);
exit;
