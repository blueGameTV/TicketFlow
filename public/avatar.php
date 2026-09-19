<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireLogin();
$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) { http_response_code(404); exit; }
$stmt = $pdo->prepare('SELECT profile_photo FROM users WHERE id = :id LIMIT 1');
$stmt->execute(['id' => $id]);
$filename = $stmt->fetchColumn();
if (!$filename) { http_response_code(404); exit; }
$path = __DIR__ . '/../storage/uploads/avatars/' . basename((string) $filename);
if (!is_file($path)) { http_response_code(404); exit; }
$mime = (new finfo(FILEINFO_MIME_TYPE))->file($path) ?: 'application/octet-stream';
if (!in_array($mime, ['image/jpeg','image/png','image/webp'], true)) { http_response_code(415); exit; }
header('Content-Type: ' . $mime);
header('Content-Length: ' . filesize($path));
header('Cache-Control: private, max-age=3600');
readfile($path);
