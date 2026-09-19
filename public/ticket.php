<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireLogin();
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$number = trim((string) ($_GET['number'] ?? $_POST['number'] ?? ''));

function loadTicket(PDO $pdo, string $number): array|false
{
    $stmt = $pdo->prepare(
        'SELECT t.*, ts.code AS status_code, ts.name AS status_name,
                tt.code AS type_code, tt.name AS type_name,
                p.name AS priority_name, p.level AS priority_level,
                tc.name AS category_name,
                CONCAT(r.firstname, " ", r.lastname) AS requester_name,
                r.email AS requester_email,
                r.group_id AS requester_group_id,
                rg.name AS requester_group_name,
                rg.manager_id AS requester_manager_id,
                CONCAT(manager.firstname, " ", manager.lastname) AS requester_manager_name,
                CONCAT(ai.firstname, " ", ai.lastname) AS assigned_it_name,
                ai.group_id AS assigned_it_group_id
         FROM tickets t
         INNER JOIN ticket_statuses ts ON ts.id = t.status_id
         INNER JOIN ticket_types tt ON tt.id = t.type_id
         INNER JOIN priorities p ON p.id = t.priority_id
         LEFT JOIN ticket_categories tc ON tc.id = t.category_id
         INNER JOIN users r ON r.id = t.requester_id
         LEFT JOIN groups_company rg ON rg.id = r.group_id
         LEFT JOIN users manager ON manager.id = rg.manager_id
         LEFT JOIN users ai ON ai.id = t.assigned_it_id
         WHERE t.ticket_number = :number AND t.deleted_at IS NULL
         LIMIT 1'
    );
    $stmt->execute(['number' => $number]);
    return $stmt->fetch();
}

$ticket = loadTicket($pdo, $number);
if (!$ticket) {
    http_response_code(404);
    exit('Ticket introuvable.');
}
if (!$ticketService->canView($ticket, $user)) {
    http_response_code(403);
    exit('Accès refusé.');
}

// L'utilisateur consulte déjà ce ticket : les notifications associées sont
// considérées comme vues afin de ne pas afficher un badge inutile.
$notificationService->markTicketRead((int) $ticket['id'], (int) $user['id']);

$currentManagerApproval = null;
if ($user['role'] === 'Manager') {
    $preloadManagerApprovalStmt = $pdo->prepare(
        "SELECT id, status, comment, requested_at, responded_at
         FROM manager_approvals
         WHERE ticket_id = :ticket_id AND manager_id = :manager_id
         ORDER BY requested_at DESC, id DESC
         LIMIT 1"
    );
    $preloadManagerApprovalStmt->execute([
        'ticket_id' => $ticket['id'],
        'manager_id' => $user['id'],
    ]);
    $currentManagerApproval = $preloadManagerApprovalStmt->fetch() ?: null;
}
$isApprovalManagerContext = $user['role'] === 'Manager'
    && $currentManagerApproval !== null
    && (int) $ticket['requester_id'] !== (int) $user['id'];

$errors = [];
$isAjax = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) === 'fetch';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La session du formulaire a expiré. Rechargez la page puis réessayez.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        try {
            if ($action === 'take') {
                $ticketService->takeOwnership((int) $ticket['id'], $user);
                $_SESSION['flash_success'] = 'Le ticket vous est maintenant attribué.';
            } elseif ($action === 'message') {
                $isTerminal = in_array($ticket['status_code'], ['resolved', 'closed', 'cancelled'], true);
                if ($isTerminal) {
                    throw new RuntimeException('Vous ne pouvez pas participer à la conversation d’un ticket terminé.');
                }
                if ($isApprovalManagerContext) {
                    throw new RuntimeException('Dans le cadre d’une validation, le Manager ne peut pas envoyer de message depuis ce ticket.');
                }
                $internal = !empty($_POST['internal']);
                if ($internal && !in_array($user['role'], ['IT', 'Administrateur'], true)) {
                    throw new RuntimeException('Vous ne pouvez pas créer de note interne.');
                }
                $ticketService->addMessage((int) $ticket['id'], (int) $user['id'], (string) ($_POST['message'] ?? ''), $internal);
                $_SESSION['flash_success'] = $internal ? 'Note interne ajoutée.' : 'Message envoyé.';
            } elseif ($action === 'priority') {
                $ticketService->updatePriority((int) $ticket['id'], (int) ($_POST['priority_id'] ?? 0), $user);
                $_SESSION['flash_success'] = 'Importance mise à jour.';
            } elseif ($action === 'status') {
                $ticketService->updateStatus((int) $ticket['id'], (string) ($_POST['status_code'] ?? ''), $user);
                $_SESSION['flash_success'] = 'Statut mis à jour.';
            } elseif ($action === 'transfer') {
                $ticketService->transfer((int) $ticket['id'], (int) ($_POST['it_id'] ?? 0), $user);
                $_SESSION['flash_success'] = 'Ticket transféré.';
            } elseif ($action === 'manager_request') {
                $ticketService->requestManagerApproval((int) $ticket['id'], $user);
                $_SESSION['flash_success'] = 'La demande de validation a été envoyée au Manager.';
            } elseif ($action === 'manager_response') {
                $ticketService->respondManagerApproval(
                    (int) ($_POST['approval_id'] ?? 0),
                    (string) ($_POST['decision'] ?? ''),
                    (string) ($_POST['comment'] ?? ''),
                    $user
                );
                $_SESSION['flash_success'] = 'Votre réponse a été enregistrée.';
            } elseif ($action === 'resolution') {
                $ticketService->proposeResolution((int) $ticket['id'], (string) ($_POST['resolution'] ?? ''), $user);
                $_SESSION['flash_success'] = 'La résolution a été proposée au demandeur.';
            } elseif ($action === 'confirm_resolution') {
                $ticketService->confirmResolution((int) $ticket['id'], $user);
                $_SESSION['flash_success'] = 'Merci. Le ticket est maintenant marqué comme résolu.';
            } elseif ($action === 'reject_resolution') {
                $ticketService->rejectResolution((int) $ticket['id'], (string) ($_POST['comment'] ?? ''), $user);
                $_SESSION['flash_success'] = 'Le ticket a été rouvert et renvoyé à IT.';
            } elseif ($action === 'attachment_upload') {
                $count = $attachmentService->uploadMany($ticket, $user, $_FILES['attachments'] ?? []);
                $_SESSION['flash_success'] = $count > 1
                    ? $count . ' pièces jointes ajoutées.'
                    : 'Pièce jointe ajoutée.';
            } else {
                throw new RuntimeException('Action inconnue.');
            }

            if ($isAjax) {
                $msg=(string)($_SESSION['flash_success'] ?? 'Action enregistrée.'); unset($_SESSION['flash_success']);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok'=>true,'message'=>$msg], JSON_UNESCAPED_UNICODE); exit;
            }
            header('Location: ticket.php?number=' . urlencode($number));
            exit;
        } catch (Throwable $e) {
            if ($isAjax) { header('Content-Type: application/json; charset=utf-8'); http_response_code(422); echo json_encode(['ok'=>false,'message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE); exit; }
            $errors[] = $e->getMessage();
        }
    }

    $ticket = loadTicket($pdo, $number);
}

$pageTitle = $ticket['ticket_number'];

$historyStmt = $pdo->prepare(
    'SELECT h.action, h.old_value, h.new_value, h.created_at,
            CONCAT(u.firstname, " ", u.lastname) AS user_name
     FROM ticket_history h
     LEFT JOIN users u ON u.id = h.user_id
     WHERE h.ticket_id = :ticket_id
     ORDER BY h.created_at DESC, h.id DESC'
);
$historyStmt->execute(['ticket_id' => $ticket['id']]);
$history = $historyStmt->fetchAll();

$messageSql = 'SELECT m.id, m.message, m.internal, m.created_at,
                      m.author_id, CONCAT(u.firstname, " ", u.lastname) AS author_name,
                      r.name AS author_role
               FROM ticket_messages m
               INNER JOIN users u ON u.id = m.author_id
               INNER JOIN roles r ON r.id = u.role_id
               WHERE m.ticket_id = :ticket_id';
if (!in_array($user['role'], ['IT', 'Administrateur'], true)) {
    $messageSql .= ' AND m.internal = 0';
}
$messageSql .= ' ORDER BY m.created_at ASC, m.id ASC';
$messageStmt = $pdo->prepare($messageSql);
$messageStmt->execute(['ticket_id' => $ticket['id']]);
$messages = $messageStmt->fetchAll();

$attachments = $attachmentService->listForTicket((int) $ticket['id']);
$uploadMaxBytes = (int) (($config['uploads']['max_file_size'] ?? 10 * 1024 * 1024));
$uploadMaxFiles = (int) (($config['uploads']['max_files_per_upload'] ?? 5));
$formatAttachmentSize = static function (int $bytes): string {
    if ($bytes >= 1024 * 1024) {
        return number_format($bytes / (1024 * 1024), 1, ',', ' ') . ' Mo';
    }
    return number_format(max(1, $bytes) / 1024, 0, ',', ' ') . ' Ko';
};

$approvalStmt = $pdo->prepare(
    'SELECT ma.id, ma.status, ma.comment, ma.requested_at, ma.responded_at,
            CONCAT(m.firstname, " ", m.lastname) AS manager_name,
            CONCAT(rb.firstname, " ", rb.lastname) AS requested_by_name
     FROM manager_approvals ma
     INNER JOIN users m ON m.id = ma.manager_id
     INNER JOIN users rb ON rb.id = ma.requested_by
     WHERE ma.ticket_id = :ticket_id
     ORDER BY ma.requested_at DESC, ma.id DESC'
);
$approvalStmt->execute(['ticket_id' => $ticket['id']]);
$managerApprovals = $approvalStmt->fetchAll();

$pendingManagerApproval = null;
foreach ($managerApprovals as $approval) {
    if ($approval['status'] === 'pending') {
        $pendingManagerApproval = $approval;
        break;
    }
}

$isTerminalTicket = in_array($ticket['status_code'], ['resolved', 'closed', 'cancelled'], true);
$isRequester = (int) $ticket['requester_id'] === (int) $user['id'];
$isSupportUser = in_array($user['role'], ['IT', 'Administrateur'], true);
$isApprovalManager = $user['role'] === 'Manager' && $currentManagerApproval !== null;
$canMessage = !$isTerminalTicket && ($isRequester || $isSupportUser);
$canAttachments = !$isTerminalTicket && ($isRequester || $isSupportUser || $isApprovalManager);

$priorities = [];
$allowedStatuses = [];
$transferIts = [];
if (in_array($user['role'], ['IT', 'Administrateur'], true)) {
    $priorities = $pdo->query('SELECT id, name, level FROM priorities ORDER BY level')->fetchAll();
    $statusCodes = $user['role'] === 'Administrateur'
        ? ['new','assigned','in_progress','waiting_user','waiting_manager','waiting_confirmation','closed','cancelled']
        : ['assigned','in_progress','waiting_user','cancelled'];
    $placeholders = implode(',', array_fill(0, count($statusCodes), '?'));
    $statusStmt = $pdo->prepare("SELECT code, name FROM ticket_statuses WHERE code IN ($placeholders) ORDER BY id");
    $statusStmt->execute($statusCodes);
    $allowedStatuses = $statusStmt->fetchAll();

    if ($user['role'] === 'IT' && $user['group_id'] !== null) {
        $itStmt = $pdo->prepare(
            "SELECT u.id, u.firstname, u.lastname
             FROM users u INNER JOIN roles r ON r.id = u.role_id
             WHERE r.name = 'IT' AND u.active = 1 AND u.group_id = :group_id
             ORDER BY u.lastname, u.firstname"
        );
        $itStmt->execute(['group_id' => $user['group_id']]);
    } else {
        $itStmt = $pdo->query(
            "SELECT u.id, u.firstname, u.lastname
             FROM users u INNER JOIN roles r ON r.id = u.role_id
             WHERE r.name = 'IT' AND u.active = 1
             ORDER BY u.lastname, u.firstname"
        );
    }
    $transferIts = $itStmt->fetchAll();
}

$approvalLabels = [
    'pending' => ['En attente', 'waiting'],
    'approved' => ['Validée', 'approved'],
    'rejected' => ['Refusée', 'rejected'],
    'more_info' => ['Plus d’informations', 'more-info'],
];

$formatSlaDate = static function (?string $value): string {
    if ($value === null || $value === '') {
        return '—';
    }
    return date('d/m/Y H:i', strtotime($value));
};

$responseDue = $ticket['sla_response_due_at'] ?? null;
$resolutionDue = $ticket['sla_resolution_due_at'] ?? null;
$responseMet = $ticket['assigned_at'] !== null && $responseDue !== null
    ? strtotime((string) $ticket['assigned_at']) <= strtotime((string) $responseDue)
    : null;
$responseOver = $responseDue !== null && $ticket['assigned_at'] === null && strtotime((string) $responseDue) < time();
$resolutionMet = $ticket['resolved_at'] !== null && $resolutionDue !== null
    ? strtotime((string) $ticket['resolved_at']) <= strtotime((string) $resolutionDue)
    : null;
$resolutionOver = $resolutionDue !== null
    && $ticket['resolved_at'] === null
    && !in_array($ticket['status_code'], ['resolved','closed','cancelled'], true)
    && strtotime((string) $resolutionDue) < time();

$flash = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

$isFragmentRequest = (string) ($_GET['fragment'] ?? '') === '1';
if (!$isFragmentRequest) {
    require __DIR__ . '/../templates/shared/header.php';
}
?>
<div id="live-ticket-root" data-live-ticket-root>
<section class="page-heading">
    <div>
        <span class="badge"><?= htmlspecialchars($ticket['ticket_number']) ?></span>
        <h1><?= htmlspecialchars($ticket['title']) ?></h1>
        <p>Créé le <?= htmlspecialchars(date('d/m/Y à H:i', strtotime($ticket['created_at']))) ?> par <?= htmlspecialchars($ticket['requester_name']) ?>.</p>
    </div>
    <?php if ($user['role'] === 'Manager' && (int) $ticket['requester_id'] !== (int) $user['id']): ?>
        <a class="btn secondary" href="manager-approvals.php">← Validations</a>
    <?php elseif (in_array($user['role'], ['Collaborateur', 'Manager'], true)): ?>
        <a class="btn secondary" href="my-tickets.php">← Mes tickets</a>
    <?php elseif ($user['role'] === 'IT'): ?>
        <a class="btn secondary" href="it-tickets.php?scope=mine">← Tickets IT</a>
    <?php else: ?>
        <a class="btn secondary" href="dashboard.php">← Dashboard</a>
    <?php endif; ?>
</section>

<?php if ($flash): ?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert error"><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="ticket-layout ticket-layout-wide">
    <div>
        <section class="panel ticket-description ticket-description-enhanced">
            <div class="ticket-section-title"><h2>Description</h2><span id="live-ticket-status" class="ticket-status status-<?= htmlspecialchars($ticket['status_code']) ?>"><?= htmlspecialchars($ticket['status_name']) ?></span></div>
            <div class="description-text"><?= nl2br(htmlspecialchars($ticket['description'])) ?></div>
        </section>

        <?php if ($ticket['resolution']): ?>
            <section class="panel resolution-panel">
                <div class="ticket-section-title"><h2>Résolution proposée</h2><?php if ($ticket['status_code'] === 'waiting_confirmation'): ?><span class="status-pill active">À confirmer</span><?php endif; ?></div>
                <div class="description-text"><?= nl2br(htmlspecialchars($ticket['resolution'])) ?></div>
            </section>
        <?php endif; ?>

        <?php if ((int) $ticket['requester_id'] === (int) $user['id'] && $ticket['status_code'] === 'waiting_confirmation'): ?>
            <section class="panel confirmation-panel">
                <h2>Votre problème est-il résolu ?</h2>
                <p class="muted">Si vous confirmez, le ticket sera marqué comme résolu.</p>
                <div class="confirmation-actions">
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
                        <input type="hidden" name="number" value="<?= htmlspecialchars($ticket['ticket_number']) ?>">
                        <input type="hidden" name="action" value="confirm_resolution">
                        <button class="btn primary" type="submit">Oui, le problème est résolu</button>
                    </form>
                    <form method="post" class="reject-resolution-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
                        <input type="hidden" name="number" value="<?= htmlspecialchars($ticket['ticket_number']) ?>">
                        <input type="hidden" name="action" value="reject_resolution">
                        <div class="field"><label for="resolution-comment">Non, le problème persiste</label><textarea id="resolution-comment" name="comment" maxlength="2000" placeholder="Vous pouvez expliquer ce qui ne fonctionne toujours pas…"></textarea></div>
                        <button class="btn secondary" type="submit">Rouvrir le ticket</button>
                    </form>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($managerApprovals): ?>
            <section class="panel manager-approval-history">
                <div class="ticket-section-title"><h2>Validations Manager</h2><span class="muted"><?= count($managerApprovals) ?> demande<?= count($managerApprovals) > 1 ? 's' : '' ?></span></div>
                <div class="approval-list">
                    <?php foreach ($managerApprovals as $approval): $label = $approvalLabels[$approval['status']] ?? [$approval['status'], '']; ?>
                        <article class="approval-card">
                            <div class="approval-card-head">
                                <div><strong><?= htmlspecialchars($approval['manager_name']) ?></strong><small>Demandée par <?= htmlspecialchars($approval['requested_by_name']) ?> le <?= htmlspecialchars(date('d/m/Y H:i', strtotime($approval['requested_at']))) ?></small></div>
                                <span class="approval-status approval-<?= htmlspecialchars($label[1]) ?>"><?= htmlspecialchars($label[0]) ?></span>
                            </div>
                            <?php if ($approval['comment']): ?><p><?= nl2br(htmlspecialchars($approval['comment'])) ?></p><?php endif; ?>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if ($isApprovalManager && !$isTerminalTicket): ?>
            <div class="manager-participation-note manager-participation-note-v0136"><i class="fa-solid fa-clipboard-check"></i><div><strong>Décision Manager requise</strong><span>Consultez les échanges puis utilisez le panneau de droite pour valider, demander des précisions ou refuser cette demande.</span></div></div>
        <?php endif; ?>
        <section class="panel conversation-panel-v0136">
            <div class="ticket-section-title"><h2><i class="fa-solid fa-comments"></i> Conversation</h2><span id="live-message-count" class="muted"><?= count($messages) ?> message<?= count($messages) > 1 ? 's' : '' ?></span></div>
            <div id="live-conversation" class="conversation conversation-chat-v0136">
                <?php if (!$messages): ?><p class="empty-state">Aucun message pour le moment.</p><?php endif; ?>
                <?php foreach ($messages as $message): $isOwnMessage = (int) $message['author_id'] === (int) $user['id']; ?>
                    <article class="chat-message-v0136 <?= $isOwnMessage ? 'is-own' : 'is-other' ?> <?= $message['internal'] ? 'internal-note' : '' ?>">
                        <div class="chat-message-avatar-v0136" aria-hidden="true"><i class="fa-solid <?= $isOwnMessage ? 'fa-user' : ($message['author_role'] === 'Manager' ? 'fa-user-tie' : (in_array($message['author_role'], ['IT', 'Administrateur'], true) ? 'fa-headset' : 'fa-user')) ?>"></i></div>
                        <div class="chat-message-bubble-v0136">
                            <div class="chat-message-meta-v0136">
                                <div><strong><?= htmlspecialchars($message['author_name']) ?></strong><span><?= htmlspecialchars($message['author_role']) ?></span><?php if ($message['internal']): ?> <span class="internal-badge">Interne IT</span><?php endif; ?></div>
                                <time><?= htmlspecialchars(date('d/m/Y H:i', strtotime($message['created_at']))) ?></time>
                            </div>
                            <div class="chat-message-text-v0136"><?= nl2br(htmlspecialchars($message['message'])) ?></div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <?php if ($canMessage): ?>
                <form class="message-form ticket-composer-card ticket-composer-chat-v0136" method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
                    <input type="hidden" name="number" value="<?= htmlspecialchars($ticket['ticket_number']) ?>">
                    <input type="hidden" name="action" value="message">
                    <div class="composer-header-v0136"><strong><i class="fa-regular fa-pen-to-square"></i> Nouveau message</strong><span>Ajoutez une réponse pour poursuivre les échanges.</span></div>
                    <div class="field"><textarea id="message" name="message" maxlength="4000" required placeholder="Écrivez votre message…"></textarea></div>
                    <div class="message-actions">
                        <?php if (in_array($user['role'], ['IT', 'Administrateur'], true)): ?>
                            <label class="internal-checkbox"><input type="checkbox" name="internal" value="1"> Note interne, invisible pour le demandeur</label>
                        <?php endif; ?>
                        <button class="btn primary" type="submit"><i class="fa-solid fa-paper-plane"></i> Envoyer</button>
                    </div>
                </form>
            <?php elseif ($isApprovalManager && !$isTerminalTicket): ?>
                <div class="conversation-restricted-note conversation-restricted-note-v0136">
                    <i class="fa-solid fa-lock"></i>
                    <div>
                        <strong>Réponse désactivée dans ce contexte</strong>
                        <span>Le Manager peut lire l’historique mais la décision doit être prise depuis le bloc “Validation demandée”.</span>
                    </div>
                </div>
            <?php endif; ?>
        </section>

        <?php if (!$isApprovalManagerContext): ?>
        <section class="panel attachment-panel">
            <div class="ticket-section-title">
                <h2>Pièces jointes</h2>
                <span class="muted"><?= count($attachments) ?> fichier<?= count($attachments) > 1 ? 's' : '' ?></span>
            </div>

            <?php if ($attachments): ?>
                <div class="attachment-list">
                    <?php foreach ($attachments as $attachment): ?>
                        <a class="attachment-item" href="attachment-download.php?id=<?= (int) $attachment['id'] ?>">
                            <span class="attachment-icon">↧</span>
                            <span class="attachment-details">
                                <strong><?= htmlspecialchars($attachment['original_name']) ?></strong>
                                <small><?= htmlspecialchars($formatAttachmentSize((int) $attachment['size_bytes'])) ?> · ajouté par <?= htmlspecialchars($attachment['uploader_name']) ?> le <?= htmlspecialchars(date('d/m/Y H:i', strtotime($attachment['created_at']))) ?></small>
                            </span>
                            <span class="attachment-download">Télécharger</span>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="empty-state">Aucune pièce jointe pour ce ticket.</p>
            <?php endif; ?>

            <?php if ($canAttachments): ?>
                <form class="attachment-upload-form ticket-attachment-composer" method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
                    <input type="hidden" name="number" value="<?= htmlspecialchars($ticket['ticket_number']) ?>">
                    <input type="hidden" name="action" value="attachment_upload">
                    <div class="field">
                        <label for="attachments">Ajouter des fichiers</label>
                        <input id="attachments" class="file-input" type="file" name="attachments[]" multiple required accept=".png,.jpg,.jpeg,.pdf,.txt,.log,.csv">
                        <small class="field-hint">PNG, JPG/JPEG, PDF, TXT, LOG ou CSV · <?= (int) $uploadMaxFiles ?> fichiers maximum · <?= htmlspecialchars($formatAttachmentSize($uploadMaxBytes)) ?> maximum par fichier.</small>
                    </div>
                    <button class="btn secondary" type="submit">Ajouter les pièces jointes</button>
                </form>
            <?php endif; ?>
        </section>
        <?php endif; ?>

        <?php if (in_array($user['role'], ['Administrateur', 'IT'], true)): ?>
            <section class="panel ticket-history">
                <h2>Historique</h2>
                <div class="timeline">
                    <?php foreach ($history as $item): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div>
                                <strong><?= htmlspecialchars($item['action']) ?></strong>
                                <?php if ($item['old_value'] || $item['new_value']): ?>
                                    <p><?php if ($item['old_value']): ?><?= htmlspecialchars($item['old_value']) ?> → <?php endif; ?><?= htmlspecialchars($item['new_value'] ?? '') ?></p>
                                <?php endif; ?>
                                <small><?= htmlspecialchars($item['user_name'] ?: 'Système') ?> · <?= htmlspecialchars(date('d/m/Y H:i', strtotime($item['created_at']))) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
    </div>

    <aside class="ticket-sidebar-wrap">
        <section class="panel ticket-sidebar">
            <h2>Informations</h2>
            <dl class="ticket-info-list">
                <div><dt>Type</dt><dd><?= htmlspecialchars($ticket['type_name']) ?></dd></div>
                <div><dt>Catégorie</dt><dd><?= htmlspecialchars($ticket['category_name'] ?? 'Non définie') ?></dd></div>
                <div><dt>Importance</dt><dd><span id="live-ticket-priority" class="priority-pill priority-level-<?= (int) $ticket['priority_level'] ?>"><?= htmlspecialchars($ticket['priority_name']) ?></span></dd></div>
                <div><dt>Statut</dt><dd id="live-ticket-status-text"><?= htmlspecialchars($ticket['status_name']) ?></dd></div>
                <div><dt>Demandeur</dt><dd><?= htmlspecialchars($ticket['requester_name']) ?><small><?= htmlspecialchars($ticket['requester_email']) ?></small></dd></div>
                <?php if (in_array($user['role'], ['Administrateur', 'IT'], true)): ?>
                    <div><dt>Groupe</dt><dd><?= htmlspecialchars($ticket['requester_group_name'] ?: 'Non défini') ?></dd></div>
                    <div><dt>Manager</dt><dd><?= htmlspecialchars($ticket['requester_manager_name'] ?: 'Non défini') ?></dd></div>
                <?php endif; ?>
                <div><dt>IT assigné</dt><dd id="live-ticket-assignee"><?= htmlspecialchars($ticket['assigned_it_name'] ?: 'Non assigné') ?></dd></div>
                <?php if ($user['role'] === 'Administrateur'): ?>
                    <div><dt>Prise en charge SLA</dt><dd>
                        <?php if ($responseMet === true): ?><span class="sla-pill sla-ok">Respecté</span>
                        <?php elseif ($responseMet === false || $responseOver): ?><span class="sla-pill sla-breached">Dépassé</span>
                        <?php else: ?><span class="sla-pill sla-pending">En cours</span><?php endif; ?>
                        <small>Échéance : <?= htmlspecialchars($formatSlaDate($responseDue)) ?></small>
                    </dd></div>
                    <div><dt>Résolution SLA</dt><dd>
                        <?php if ($resolutionMet === true): ?><span class="sla-pill sla-ok">Respecté</span>
                        <?php elseif ($resolutionMet === false || $resolutionOver): ?><span class="sla-pill sla-breached">Dépassé</span>
                        <?php elseif (in_array($ticket['status_code'], ['resolved','closed','cancelled'], true)): ?><span class="sla-pill sla-neutral">Terminé</span>
                        <?php else: ?><span class="sla-pill sla-pending">En cours</span><?php endif; ?>
                        <small>Échéance : <?= htmlspecialchars($formatSlaDate($resolutionDue)) ?></small>
                    </dd></div>
                    <div><dt>Première prise en charge</dt><dd><?= htmlspecialchars($formatSlaDate($ticket['assigned_at'] ?? null)) ?></dd></div>
                <?php endif; ?>
                <div><dt>Dernière mise à jour</dt><dd><?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['updated_at']))) ?></dd></div>
            </dl>
        </section>

        <?php if ($user['role'] === 'Manager' && $currentManagerApproval && $currentManagerApproval['status'] === 'pending'): ?>
            <section class="panel manager-decision-panel manager-decision-panel-enhanced">
                <h2>Validation demandée</h2>
                <p class="muted">IT attend votre décision avant de poursuivre cette demande.</p>
                <form method="post">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
                    <input type="hidden" name="number" value="<?= htmlspecialchars($ticket['ticket_number']) ?>">
                    <input type="hidden" name="action" value="manager_response">
                    <input type="hidden" name="approval_id" value="<?= (int) $currentManagerApproval['id'] ?>">
                    <div class="field"><label for="manager-comment">Commentaire</label><textarea id="manager-comment" name="comment" maxlength="2000" placeholder="Facultatif pour valider/refuser, utile si vous demandez des précisions."></textarea></div>
                    <div class="manager-decision-actions">
                        <button class="btn primary" type="submit" name="decision" value="approved">Valider</button>
                        <button class="btn secondary" type="submit" name="decision" value="more_info">Demander des informations</button>
                        <button class="btn danger" type="submit" name="decision" value="rejected">Refuser</button>
                    </div>
                </form>
            </section>
        <?php endif; ?>

        <?php if (in_array($user['role'], ['IT', 'Administrateur'], true) && !($user['role'] === 'IT' && in_array($ticket['status_code'], ['resolved','closed','cancelled'], true))): ?>
            <section class="panel it-actions-panel it-actions-panel-enhanced">
                <h2>Actions IT</h2>

                <?php if ($user['role'] === 'IT' && $ticket['assigned_it_id'] === null): ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
                        <input type="hidden" name="number" value="<?= htmlspecialchars($ticket['ticket_number']) ?>">
                        <input type="hidden" name="action" value="take">
                        <button class="btn primary full" type="submit">Prendre en charge</button>
                    </form>
                <?php endif; ?>

                <form method="post" class="compact-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
                    <input type="hidden" name="number" value="<?= htmlspecialchars($ticket['ticket_number']) ?>">
                    <input type="hidden" name="action" value="status">
                    <label>Statut</label>
                    <div class="inline-control"><select name="status_code">
                        <?php $manualCodes = array_column($allowedStatuses, 'code'); ?>
                        <?php if (!in_array($ticket['status_code'], $manualCodes, true)): ?>
                            <option value="" selected disabled><?= htmlspecialchars($ticket['status_name']) ?> (automatique)</option>
                        <?php endif; ?>
                        <?php foreach ($allowedStatuses as $item): ?>
                            <option value="<?= htmlspecialchars($item['code']) ?>" <?= $ticket['status_code'] === $item['code'] ? 'selected' : '' ?>><?= htmlspecialchars($item['name']) ?></option>
                        <?php endforeach; ?>
                    </select><button class="btn secondary small" type="submit">Modifier</button></div>
                </form>

                <form method="post" class="compact-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
                    <input type="hidden" name="number" value="<?= htmlspecialchars($ticket['ticket_number']) ?>">
                    <input type="hidden" name="action" value="priority">
                    <label>Importance</label>
                    <div class="inline-control"><select name="priority_id"><?php foreach ($priorities as $item): ?><option value="<?= (int) $item['id'] ?>" <?= (int) $ticket['priority_id'] === (int) $item['id'] ? 'selected' : '' ?>><?= htmlspecialchars($item['name']) ?></option><?php endforeach; ?></select><button class="btn secondary small" type="submit">Modifier</button></div>
                </form>

                <?php if ($transferIts): ?>
                    <form method="post" class="compact-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
                        <input type="hidden" name="number" value="<?= htmlspecialchars($ticket['ticket_number']) ?>">
                        <input type="hidden" name="action" value="transfer">
                        <label>Transférer à</label>
                        <div class="inline-control"><select name="it_id" required><option value="">Choisir un IT</option><?php foreach ($transferIts as $it): ?><option value="<?= (int) $it['id'] ?>" <?= (int) $ticket['assigned_it_id'] === (int) $it['id'] ? 'selected' : '' ?>><?= htmlspecialchars($it['firstname'] . ' ' . $it['lastname']) ?></option><?php endforeach; ?></select><button class="btn secondary small" type="submit">Transférer</button></div>
                    </form>
                <?php endif; ?>

                <?php if (!in_array($ticket['status_code'], ['closed', 'cancelled', 'waiting_confirmation'], true)): ?>
                    <form method="post" class="compact-form manager-request-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
                        <input type="hidden" name="number" value="<?= htmlspecialchars($ticket['ticket_number']) ?>">
                        <input type="hidden" name="action" value="manager_request">
                        <label>Validation Manager</label>
                        <?php if (!$ticket['requester_manager_id']): ?>
                            <p class="inline-warning">Aucun Manager n’est défini pour le groupe du demandeur.</p>
                        <?php elseif ($pendingManagerApproval): ?>
                            <p class="inline-warning">Une validation est déjà en attente auprès de <?= htmlspecialchars($pendingManagerApproval['manager_name']) ?>.</p>
                        <?php else: ?>
                            <button class="btn secondary full" type="submit">Demander la validation de <?= htmlspecialchars($ticket['requester_manager_name']) ?></button>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </section>

            <?php if (!in_array($ticket['status_code'], ['resolved','closed','cancelled','waiting_manager','waiting_confirmation'], true)): ?>
                <section class="panel resolution-form-panel resolution-form-panel-enhanced">
                    <h2>Proposer la résolution</h2>
                    <p class="muted">Le ticket passera en attente de confirmation du demandeur.</p>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
                        <input type="hidden" name="number" value="<?= htmlspecialchars($ticket['ticket_number']) ?>">
                        <input type="hidden" name="action" value="resolution">
                        <div class="field"><textarea name="resolution" minlength="5" maxlength="4000" required placeholder="Expliquez la solution appliquée…"><?= htmlspecialchars($ticket['resolution'] ?? '') ?></textarea></div>
                        <button class="btn primary full" type="submit">Proposer au demandeur</button>
                    </form>
                </section>
            <?php endif; ?>
        <?php endif; ?>
    </aside>
</div>
</div>
<?php if ($isFragmentRequest) { exit; } ?>
<div id="live-toast" class="live-toast" hidden></div>
<script>window.TICKETFLOW_TICKET_NUMBER=<?= json_encode($ticket['ticket_number']) ?>; window.TICKETFLOW_CURRENT_USER_ID=<?= json_encode((int) $user['id']) ?>;</script>
<script src="assets/js/live-ticket.js?v=0.15.2.1" defer></script>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
