<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
$auth->requireLogin();
$user = $auth->user();
$role = (string)($user['role'] ?? '');

$accessBlocked = $maintenanceService->accessBlocked() && !$maintenanceService->roleAllowed($role);
if ($accessBlocked) {
    echo json_encode(['ok'=>true,'access_blocked'=>true,'redirect'=>'maintenance.php','alerts'=>[],'maintenance'=>[]], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$alerts = array_map(static function (array $row) use ($serviceAlertService): array {
    $detail = $serviceAlertService->severityLabel((string)$row['severity']) . ' · Début ' . date('d/m/Y H:i', strtotime((string)$row['starts_at']));
    if (!empty($row['ends_at'])) $detail .= ' · Fin prévue ' . date('d/m/Y H:i', strtotime((string)$row['ends_at']));
    return [
        'id'=>(int)$row['id'],'service_name'=>(string)$row['service_name'],'title'=>(string)$row['title'],
        'message'=>(string)$row['message'],'severity'=>(string)$row['severity'],
        'severity_label'=>$serviceAlertService->severityLabel((string)$row['severity']),'detail_meta'=>$detail,
    ];
}, $serviceAlertService->active());

$maintenance = array_map(static function (array $row): array {
    return [
        'id'=>(int)$row['id'],'title'=>(string)$row['title'],'message'=>(string)$row['message'],
        'period'=>date('d/m/Y H:i', strtotime((string)$row['starts_at'])) . ' → ' . date('d/m/Y H:i', strtotime((string)$row['ends_at'])),
        'short_period'=>date('d/m H:i', strtotime((string)$row['starts_at'])),
    ];
}, $maintenanceService->upcoming(24));

echo json_encode(['ok'=>true,'access_blocked'=>false,'alerts'=>$alerts,'maintenance'=>$maintenance], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
