<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$auth->requireLogin();
$user = $auth->user();
$q = trim((string) ($_GET['q'] ?? ''));

if (mb_strlen($q) < 2) {
    echo json_encode(['ok' => true, 'query' => $q, 'tickets' => [], 'users' => [], 'groups' => []], JSON_UNESCAPED_UNICODE);
    exit;
}

$term = '%' . $q . '%';

$ticketStmt = $pdo->prepare(
    'SELECT t.id, t.ticket_number, t.title, t.requester_id, t.assigned_it_id,
            ts.code AS status_code, ts.name AS status_name,
            p.name AS priority_name, p.level AS priority_level,
            CONCAT(r.firstname, " ", r.lastname) AS requester_name
     FROM tickets t
     INNER JOIN ticket_statuses ts ON ts.id = t.status_id
     INNER JOIN priorities p ON p.id = t.priority_id
     INNER JOIN users r ON r.id = t.requester_id
     WHERE t.deleted_at IS NULL
       AND (t.ticket_number LIKE :number OR t.title LIKE :title OR CONCAT(r.firstname, " ", r.lastname) LIKE :requester)
     ORDER BY t.updated_at DESC
     LIMIT 30'
);
$ticketStmt->execute(['number' => $term, 'title' => $term, 'requester' => $term]);
$ticketCandidates = $ticketStmt->fetchAll();
$tickets = [];
foreach ($ticketCandidates as $ticket) {
    if (!$ticketService->canView($ticket, $user)) {
        continue;
    }
    $tickets[] = [
        'number' => $ticket['ticket_number'],
        'title' => $ticket['title'],
        'status' => $ticket['status_name'],
        'status_code' => $ticket['status_code'],
        'priority' => $ticket['priority_name'],
        'priority_level' => (int) $ticket['priority_level'],
        'requester' => $ticket['requester_name'],
        'url' => 'ticket.php?number=' . rawurlencode((string) $ticket['ticket_number']),
    ];
    if (count($tickets) >= 8) break;
}

$users = [];
$groups = [];
if (in_array($user['role'], ['Administrateur', 'IT'], true)) {
    $userStmt = $pdo->prepare(
        'SELECT u.id, u.firstname, u.lastname, u.username, u.email, u.active, r.name AS role_name
         FROM users u
         INNER JOIN roles r ON r.id = u.role_id
         WHERE u.firstname LIKE :first OR u.lastname LIKE :last OR u.username LIKE :username OR u.email LIKE :email
         ORDER BY u.active DESC, u.lastname, u.firstname
         LIMIT 6'
    );
    $userStmt->execute(['first' => $term, 'last' => $term, 'username' => $term, 'email' => $term]);
    foreach ($userStmt->fetchAll() as $row) {
        $users[] = [
            'name' => trim($row['firstname'] . ' ' . $row['lastname']),
            'username' => $row['username'],
            'email' => $row['email'],
            'role' => $row['role_name'],
            'active' => (bool) $row['active'],
            'url' => $user['role'] === 'Administrateur' ? 'admin-user-form.php?id=' . (int) $row['id'] : null,
        ];
    }

    $groupStmt = $pdo->prepare(
        'SELECT g.id, g.name, g.active, COUNT(u.id) AS member_count,
                CONCAT(m.firstname, " ", m.lastname) AS manager_name
         FROM groups_company g
         LEFT JOIN users m ON m.id = g.manager_id
         LEFT JOIN users u ON u.group_id = g.id
         WHERE g.name LIKE :name
         GROUP BY g.id, g.name, g.active, m.firstname, m.lastname
         ORDER BY g.active DESC, g.name
         LIMIT 5'
    );
    $groupStmt->execute(['name' => $term]);
    foreach ($groupStmt->fetchAll() as $row) {
        $groups[] = [
            'name' => $row['name'],
            'manager' => $row['manager_name'] ?: 'Non défini',
            'members' => (int) $row['member_count'],
            'active' => (bool) $row['active'],
            'url' => $user['role'] === 'Administrateur' ? 'admin-group-form.php?id=' . (int) $row['id'] : null,
        ];
    }
}

echo json_encode([
    'ok' => true,
    'query' => $q,
    'tickets' => $tickets,
    'users' => $users,
    'groups' => $groups,
], JSON_UNESCAPED_UNICODE);
