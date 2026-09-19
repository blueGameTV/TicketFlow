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
    $roleId = max(0, (int) ($_POST['role_id'] ?? 0));
    $groupId = max(0, (int) ($_POST['group_id'] ?? 0));
    $state = (string) ($_POST['state'] ?? 'all');
    if (!in_array($state, ['all', 'active', 'inactive'], true)) {
        $state = 'all';
    }

    $where = ['u.created_at >= :from', 'u.created_at < :to_exclusive'];
    $params = [
        'from' => $start->format('Y-m-d 00:00:00'),
        'to_exclusive' => $end->modify('+1 day')->format('Y-m-d 00:00:00'),
    ];
    if ($roleId > 0) {
        $where[] = 'u.role_id = :role_id';
        $params['role_id'] = $roleId;
    }
    if ($groupId > 0) {
        $where[] = 'u.group_id = :group_id';
        $params['group_id'] = $groupId;
    }
    if ($state === 'active') {
        $where[] = 'u.active = 1';
    } elseif ($state === 'inactive') {
        $where[] = 'u.active = 0';
    }

    $sql = 'SELECT u.id, u.lastname, u.firstname, u.username, u.email, r.name AS role_name,
                   g.name AS group_name,
                   CONCAT(m.firstname, " ", m.lastname) AS manager_name,
                   u.arrival_date, u.active, u.last_login_at, u.created_at,
                   GROUP_CONCAT(CONCAT(a.name, " : ", ua.login) ORDER BY a.name SEPARATOR " | ") AS application_logins
            FROM users u
            INNER JOIN roles r ON r.id = u.role_id
            LEFT JOIN groups_company g ON g.id = u.group_id
            LEFT JOIN users m ON m.id = g.manager_id
            LEFT JOIN user_applications ua ON ua.user_id = u.id
            LEFT JOIN applications a ON a.id = ua.application_id
            WHERE ' . implode(' AND ', $where) . '
            GROUP BY u.id, u.lastname, u.firstname, u.username, u.email, r.name, g.name, m.firstname, m.lastname,
                     u.arrival_date, u.active, u.last_login_at, u.created_at
            ORDER BY u.lastname, u.firstname';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $records = $stmt->fetchAll();

    $rows = [];
    foreach ($records as $record) {
        $rows[] = [
            (int) $record['id'],
            (string) $record['lastname'],
            (string) $record['firstname'],
            (string) $record['username'],
            (string) $record['email'],
            (string) $record['role_name'],
            (string) ($record['group_name'] ?? 'Non attribué'),
            (string) ($record['manager_name'] ?? ''),
            $record['arrival_date'] ? date('d/m/Y', strtotime((string) $record['arrival_date'])) : '',
            (int) $record['active'] === 1 ? 'Actif' : 'Désactivé',
            $record['last_login_at'] ? date('d/m/Y H:i', strtotime((string) $record['last_login_at'])) : 'Jamais',
            date('d/m/Y H:i', strtotime((string) $record['created_at'])),
            (string) ($record['application_logins'] ?? ''),
        ];
    }

    $headers = ['ID', 'Nom', 'Prénom', 'Identifiant', 'E-mail', 'Rôle', 'Groupe', 'Manager', 'Date arrivée', 'État', 'Dernière connexion', 'Création compte', 'Applications / logins'];
    $file = $excelExportService->createXlsx('Utilisateurs', $headers, $rows);
    $filename = 'ticketflow-utilisateurs-' . $start->format('Ymd') . '-' . $end->format('Ymd') . '.xlsx';

    $log = sprintf("[%s] admin_id=%d export=users from=%s to=%s rows=%d\n", date('c'), (int) $user['id'], $start->format('Y-m-d'), $end->format('Y-m-d'), count($rows));
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
