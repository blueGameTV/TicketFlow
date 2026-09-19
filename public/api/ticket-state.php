<?php

declare(strict_types=1);

require_once __DIR__ . '/../../src/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$auth->requireLogin();
$user = $auth->user();
$number = trim((string) ($_GET['number'] ?? ''));

$stmt = $pdo->prepare(
    'SELECT t.*, ts.code AS status_code, ts.name AS status_name,
            p.name AS priority_name, p.level AS priority_level,
            CONCAT(ai.firstname, " ", ai.lastname) AS assigned_it_name
     FROM tickets t
     INNER JOIN ticket_statuses ts ON ts.id = t.status_id
     INNER JOIN priorities p ON p.id = t.priority_id
     LEFT JOIN users ai ON ai.id = t.assigned_it_id
     WHERE t.ticket_number = :number AND t.deleted_at IS NULL
     LIMIT 1'
);
$stmt->execute(['number' => $number]);
$ticket = $stmt->fetch();

if (!$ticket || !$ticketService->canView($ticket, $user)) {
    http_response_code(403);
    echo json_encode(['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}

$notificationService->markTicketRead((int) $ticket['id'], (int) $user['id']);

$messageSql =
    'SELECT m.id, m.message, m.internal, m.created_at,
            m.author_id,
            CONCAT(u.firstname, " ", u.lastname) AS author_name,
            r.name AS author_role
     FROM ticket_messages m
     INNER JOIN users u ON u.id = m.author_id
     INNER JOIN roles r ON r.id = u.role_id
     WHERE m.ticket_id = :id';

if (!in_array($user['role'], ['IT', 'Administrateur'], true)) {
    $messageSql .= ' AND m.internal = 0';
}
$messageSql .= ' ORDER BY m.created_at, m.id';

$messageStmt = $pdo->prepare($messageSql);
$messageStmt->execute(['id' => $ticket['id']]);
$messages = $messageStmt->fetchAll();

// Le hash couvre tout ce qui peut modifier visuellement la page ticket.
// Il permet au navigateur de ne télécharger le fragment HTML complet que
// lorsqu'un vrai changement a eu lieu.
$stateStmt = $pdo->prepare(
    'SELECT
        COALESCE(MAX(h.id), 0) AS history_max_id,
        (SELECT COALESCE(MAX(m.id), 0) FROM ticket_messages m WHERE m.ticket_id = :ticket_id_2) AS message_max_id,
        (SELECT COALESCE(MAX(a.id), 0) FROM ticket_attachments a WHERE a.ticket_id = :ticket_id_3) AS attachment_max_id,
        (SELECT COALESCE(MAX(ma.id), 0) FROM manager_approvals ma WHERE ma.ticket_id = :ticket_id_4) AS approval_max_id,
        (SELECT COALESCE(MAX(CONCAT(COALESCE(ma.responded_at, ma.requested_at), "|", ma.status, "|", COALESCE(ma.comment, ""))), "")
           FROM manager_approvals ma WHERE ma.ticket_id = :ticket_id_5) AS approval_state
     FROM ticket_history h
     WHERE h.ticket_id = :ticket_id_1'
);
$stateStmt->execute([
    'ticket_id_1' => $ticket['id'],
    'ticket_id_2' => $ticket['id'],
    'ticket_id_3' => $ticket['id'],
    'ticket_id_4' => $ticket['id'],
    'ticket_id_5' => $ticket['id'],
]);
$related = $stateStmt->fetch() ?: [];

$statePayload = [
    'status_id' => $ticket['status_id'],
    'priority_id' => $ticket['priority_id'],
    'assigned_it_id' => $ticket['assigned_it_id'],
    'resolution' => $ticket['resolution'],
    'resolved_at' => $ticket['resolved_at'],
    'closed_at' => $ticket['closed_at'],
    'assigned_at' => $ticket['assigned_at'] ?? null,
    'updated_at' => $ticket['updated_at'],
    'history_max_id' => $related['history_max_id'] ?? 0,
    'message_max_id' => $related['message_max_id'] ?? 0,
    'attachment_max_id' => $related['attachment_max_id'] ?? 0,
    'approval_max_id' => $related['approval_max_id'] ?? 0,
    'approval_state' => $related['approval_state'] ?? '',
];
$stateHash = hash('sha256', json_encode($statePayload, JSON_UNESCAPED_UNICODE));

echo json_encode([
    'ok' => true,
    'state_hash' => $stateHash,
    'ticket' => [
        'status_code' => $ticket['status_code'],
        'status_name' => $ticket['status_name'],
        'priority_name' => $ticket['priority_name'],
        'priority_level' => (int) $ticket['priority_level'],
        'assigned_it_name' => $ticket['assigned_it_name'] ?: 'Non assigné',
        'updated_at' => $ticket['updated_at'],
        'resolution' => $ticket['resolution'],
    ],
    'messages' => $messages,
    'unread' => $notificationService->unreadCount((int) $user['id']),
], JSON_UNESCAPED_UNICODE);
