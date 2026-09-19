<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireLogin();
$user = $auth->user();
$attachmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$attachmentId || $attachmentId <= 0) {
    http_response_code(400);
    exit('Pièce jointe invalide.');
}

try {
    $attachment = $attachmentService->getForDownload((int) $attachmentId, $user);
} catch (RuntimeException $e) {
    $status = str_contains($e->getMessage(), 'Accès refusé') ? 403 : 404;
    http_response_code($status);
    exit(htmlspecialchars($e->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
}

$path = (string) $attachment['path'];
$filename = (string) $attachment['original_name'];
$asciiFallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'piece-jointe';

header('Content-Type: application/octet-stream');
header('Content-Length: ' . (string) filesize($path));
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header(
    "Content-Disposition: attachment; filename=\"" . addcslashes($asciiFallback, "\\\"") . "\"; filename*=UTF-8''" . rawurlencode($filename)
);

$handle = fopen($path, 'rb');
if ($handle === false) {
    http_response_code(500);
    exit('Impossible de lire le fichier.');
}
fpassthru($handle);
fclose($handle);
exit;
