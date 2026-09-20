<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireRole('Administrateur');
$user = $auth->user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$csrf->validate($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    exit('Requête invalide.');
}

try {
    [$start, $end] = $excelExportService->validateRange((string)($_POST['from'] ?? ''), (string)($_POST['to'] ?? ''));
    $from = $start->format('Y-m-d 00:00:00');
    $to = $end->format('Y-m-d 23:59:59');

    $rows = [];
    $alertStmt = $pdo->prepare(
        "SELECT sa.id, sa.service_name, sa.title, sa.severity, sa.status, sa.starts_at, sa.ends_at,
                sa.resolved_at, u.firstname, u.lastname
         FROM service_alerts sa
         INNER JOIN users u ON u.id=sa.created_by
         WHERE sa.created_at BETWEEN :from AND :to
         ORDER BY sa.created_at ASC"
    );
    $alertStmt->execute(['from' => $from, 'to' => $to]);
    foreach ($alertStmt->fetchAll() as $row) {
        $rows[] = [
            'Alerte service', (int)$row['id'], (string)$row['service_name'], (string)$row['title'],
            $serviceAlertService->severityLabel((string)$row['severity']), (string)$row['status'],
            (string)$row['starts_at'], (string)($row['ends_at'] ?? ''), (string)($row['resolved_at'] ?? ''),
            trim($row['firstname'] . ' ' . $row['lastname']),
        ];
    }

    $maintStmt = $pdo->prepare(
        "SELECT mw.id, mw.title, mw.message, mw.starts_at, mw.ends_at, mw.block_access, mw.cancelled_at,
                u.firstname, u.lastname
         FROM maintenance_windows mw
         INNER JOIN users u ON u.id=mw.created_by
         WHERE mw.created_at BETWEEN :from AND :to
         ORDER BY mw.created_at ASC"
    );
    $maintStmt->execute(['from' => $from, 'to' => $to]);
    foreach ($maintStmt->fetchAll() as $row) {
        $rows[] = [
            'Maintenance', (int)$row['id'], 'TicketFlow / infrastructure', (string)$row['title'],
            !empty($row['block_access']) ? 'Blocage accès' : 'Information',
            !empty($row['cancelled_at']) ? 'Annulée' : (strtotime((string)$row['ends_at']) < time() ? 'Terminée' : 'Planifiée'),
            (string)$row['starts_at'], (string)$row['ends_at'], (string)($row['cancelled_at'] ?? ''),
            trim($row['firstname'] . ' ' . $row['lastname']),
        ];
    }

    usort($rows, static fn(array $a, array $b): int => strcmp((string)$a[6], (string)$b[6]));
    $file = $excelExportService->createXlsx('Opérations', [
        'Type', 'ID', 'Service', 'Titre', 'Niveau / impact', 'État', 'Début', 'Fin', 'Résolution / annulation', 'Créé par'
    ], $rows);

    $auditService->log((int)$user['id'], 'operations_exported', 'export', null, [
        'from' => $start->format('Y-m-d'), 'to' => $end->format('Y-m-d'), 'rows' => count($rows),
    ]);

    $filename = 'ticketflow-operations-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.xlsx';
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($file));
    readfile($file);
    @unlink($file);
    exit;
} catch (Throwable $e) {
    $_SESSION['flash_error'] = $e->getMessage();
    header('Location: admin-exports.php');
    exit;
}
