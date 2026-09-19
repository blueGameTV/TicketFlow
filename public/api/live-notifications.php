<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';
header('Content-Type: application/json; charset=utf-8');
if (!$auth->check()) { http_response_code(401); echo json_encode(['ok'=>false]); exit; }
$u=$auth->user();
echo json_encode(['ok'=>true,'unread'=>$notificationService->unreadCount((int)$u['id']),'server_time'=>date(DATE_ATOM)],JSON_UNESCAPED_UNICODE);
