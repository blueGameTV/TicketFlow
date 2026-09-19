<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireRole('Administrateur');
$user = $auth->user();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: admin-exports.php');
    exit;
}

if (!$csrf->validate($_POST['csrf_token'] ?? null)) {
    $_SESSION['flash_error'] = 'La session du formulaire a expiré. Veuillez réessayer.';
    header('Location: admin-exports.php');
    exit;
}

try {
    [$start, $end] = $excelExportService->validateRange((string) ($_POST['from'] ?? ''), (string) ($_POST['to'] ?? ''));
    $typeId = max(0, (int) ($_POST['type_id'] ?? 0));
    $statusId = max(0, (int) ($_POST['status_id'] ?? 0));
    $priorityId = max(0, (int) ($_POST['priority_id'] ?? 0));
    $groupId = max(0, (int) ($_POST['group_id'] ?? 0));
    $assignedItId = (int) ($_POST['assigned_it_id'] ?? 0);

    $where = ['t.deleted_at IS NULL', 't.created_at >= :from', 't.created_at < :to_exclusive'];
    $params = [
        'from' => $start->format('Y-m-d 00:00:00'),
        'to_exclusive' => $end->modify('+1 day')->format('Y-m-d 00:00:00'),
    ];
    if ($typeId > 0) {
        $where[] = 't.type_id = :type_id';
        $params['type_id'] = $typeId;
    }
    if ($statusId > 0) {
        $where[] = 't.status_id = :status_id';
        $params['status_id'] = $statusId;
    }
    if ($priorityId > 0) {
        $where[] = 't.priority_id = :priority_id';
        $params['priority_id'] = $priorityId;
    }
    if ($groupId > 0) {
        $where[] = 'requester.group_id = :group_id';
        $params['group_id'] = $groupId;
    }
    if ($assignedItId === -1) {
        $where[] = 't.assigned_it_id IS NULL';
    } elseif ($assignedItId > 0) {
        $where[] = 't.assigned_it_id = :assigned_it_id';
        $params['assigned_it_id'] = $assignedItId;
    }

    $sql = 'SELECT t.ticket_number, tt.code AS type_code, tt.name AS type_name, tc.name AS category_name,
                   t.title, CONCAT(requester.firstname, " ", requester.lastname) AS requester_name,
                   requester.email AS requester_email, requester_group.name AS requester_group,
                   CONCAT(manager.firstname, " ", manager.lastname) AS manager_name,
                   CONCAT(assigned_it.firstname, " ", assigned_it.lastname) AS assigned_it_name,
                   p.name AS priority_name, ts.name AS status_name,
                   t.created_at, t.updated_at, t.resolved_at, t.closed_at,
                   CASE WHEN t.resolved_at IS NOT NULL THEN ROUND(TIMESTAMPDIFF(MINUTE, t.created_at, t.resolved_at) / 60, 2) ELSE NULL END AS resolution_hours
            FROM tickets t
            INNER JOIN ticket_types tt ON tt.id = t.type_id
            LEFT JOIN ticket_categories tc ON tc.id = t.category_id
            INNER JOIN priorities p ON p.id = t.priority_id
            INNER JOIN ticket_statuses ts ON ts.id = t.status_id
            INNER JOIN users requester ON requester.id = t.requester_id
            LEFT JOIN groups_company requester_group ON requester_group.id = requester.group_id
            LEFT JOIN users manager ON manager.id = requester_group.manager_id
            LEFT JOIN users assigned_it ON assigned_it.id = t.assigned_it_id
            WHERE ' . implode(' AND ', $where) . '
            ORDER BY t.created_at, t.id';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    $rows = [];
    foreach ($records as $record) {
        $rows[] = [
            (string) $record['ticket_number'],
            (string) ($record['type_code'] . ' — ' . $record['type_name']),
            (string) ($record['category_name'] ?? ''),
            (string) $record['title'],
            (string) $record['requester_name'],
            (string) $record['requester_email'],
            (string) ($record['requester_group'] ?? 'Sans groupe'),
            (string) ($record['manager_name'] ?? ''),
            (string) ($record['assigned_it_name'] ?? 'Non assigné'),
            (string) $record['priority_name'],
            (string) $record['status_name'],
            date('d/m/Y H:i', strtotime((string) $record['created_at'])),
            date('d/m/Y H:i', strtotime((string) $record['updated_at'])),
            $record['resolved_at'] ? date('d/m/Y H:i', strtotime((string) $record['resolved_at'])) : '',
            $record['closed_at'] ? date('d/m/Y H:i', strtotime((string) $record['closed_at'])) : '',
            $record['resolution_hours'] !== null ? (string) $record['resolution_hours'] : '',
        ];
    }

    $headers = ['Ticket', 'Type', 'Catégorie', 'Titre', 'Demandeur', 'E-mail demandeur', 'Groupe', 'Manager', 'IT assigné', 'Importance', 'Statut', 'Créé le', 'Mis à jour le', 'Résolu le', 'Fermé le', 'Durée résolution (h)'];
    $file = $excelExportService->createXlsx('Tickets', $headers, $rows);
    $filename = 'ticketflow-tickets-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.xlsx';

    $log = sprintf("[%s] admin_id=%d export=tickets from=%s to=%s rows=%d\n", date('c'), (int) $user['id'], $start->format('Y-m-d'), $end->format('Y-m-d'), count($rows));
    @file_put_contents(__DIR__ . '/../storage/logs/exports.log', $log, FILE_APPEND | LOCK_EX);

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($file));
    header('Cache-Control: no-store, no-cache, must-revalidate');
    readfile($file);
    @unlink($file);
    exit;
} catch (Throwable $e) {
    $_SESSION['flash_error'] = $e->getMessage();
    header('Location: admin-exports.php');
    exit;
}
