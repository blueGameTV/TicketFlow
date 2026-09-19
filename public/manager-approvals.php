<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireRole('Manager');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'Validations Manager';

$scope = (string) ($_GET['scope'] ?? 'pending');
if (!in_array($scope, ['pending', 'all'], true)) {
    $scope = 'pending';
}

$sql = 'SELECT ma.id, ma.status, ma.comment, ma.requested_at, ma.responded_at,
               t.ticket_number, t.title, t.created_at,
               ts.code AS ticket_status_code, ts.name AS ticket_status_name,
               p.name AS priority_name, p.level AS priority_level,
               CONCAT(requester.firstname, " ", requester.lastname) AS requester_name,
               CONCAT(requested_by.firstname, " ", requested_by.lastname) AS requested_by_name
        FROM manager_approvals ma
        INNER JOIN tickets t ON t.id = ma.ticket_id
        INNER JOIN ticket_statuses ts ON ts.id = t.status_id
        INNER JOIN priorities p ON p.id = t.priority_id
        INNER JOIN users requester ON requester.id = t.requester_id
        INNER JOIN users requested_by ON requested_by.id = ma.requested_by
        WHERE ma.manager_id = :manager_id AND t.deleted_at IS NULL';

if ($scope === 'pending') {
    $sql .= " AND ma.status = 'pending'";
}
$sql .= ' ORDER BY CASE WHEN ma.status = \'pending\' THEN 0 ELSE 1 END, ma.requested_at DESC, ma.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute(['manager_id' => $user['id']]);
$approvals = $stmt->fetchAll();

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM manager_approvals WHERE manager_id = :manager_id AND status = 'pending'");
$countStmt->execute(['manager_id' => $user['id']]);
$pendingCount = (int) $countStmt->fetchColumn();

$labels = [
    'pending' => ['En attente', 'waiting'],
    'approved' => ['Validée', 'approved'],
    'rejected' => ['Refusée', 'rejected'],
    'more_info' => ['Plus d’informations', 'more-info'],
];

require __DIR__ . '/../templates/shared/header.php';
?>
<section class="page-heading ticket-queue-heading manager-validation-heading-v0135">
    <div>
        <span class="badge"><i class="fa-solid fa-user-tie"></i> Manager</span>
        <h1>Validations</h1>
        <p>Traitez les demandes envoyées par l’équipe IT depuis une file claire et priorisée.</p>
    </div>
    <div class="queue-summary manager-validation-summary">
        <i class="fa-solid fa-list-check"></i>
        <div><strong><?= $pendingCount ?></strong><span>validation<?= $pendingCount > 1 ? 's' : '' ?> en attente</span></div>
    </div>
</section>

<div class="ticket-scope-tabs queue-tabs manager-validation-tabs">
    <a class="<?= $scope === 'pending' ? 'active' : '' ?>" href="manager-approvals.php?scope=pending"><i class="fa-solid fa-hourglass-half"></i> En attente<?= $pendingCount > 0 ? ' (' . $pendingCount . ')' : '' ?></a>
    <a class="<?= $scope === 'all' ? 'active' : '' ?>" href="manager-approvals.php?scope=all"><i class="fa-solid fa-clock-rotate-left"></i> Historique</a>
</div>

<section class="panel table-panel ticket-queue-panel manager-validation-list-panel">
    <div class="table-header">
        <div><h2><?= $scope === 'pending' ? 'À traiter' : 'Historique des validations' ?></h2><p class="muted"><?= count($approvals) ?> résultat<?= count($approvals) > 1 ? 's' : '' ?></p></div>
        <span class="queue-page-chip"><i class="fa-solid <?= $scope === 'pending' ? 'fa-list-check' : 'fa-clock-rotate-left' ?>"></i> <?= $scope === 'pending' ? 'En cours' : 'Historique' ?></span>
    </div>
    <?php if (!$approvals): ?>
        <div class="empty-state queue-empty"><i class="fa-regular fa-circle-check"></i><strong>Aucune validation dans cette vue.</strong><span><?= $scope === 'pending' ? 'Vous n’avez aucune décision en attente.' : 'Aucune validation historique à afficher.' ?></span></div>
    <?php else: ?>
        <div class="ticket-queue-list manager-validation-list">
        <?php foreach ($approvals as $approval): $label = $labels[$approval['status']] ?? [$approval['status'], '']; ?>
            <article class="ticket-queue-item manager-validation-item priority-border-<?= (int) $approval['priority_level'] ?>">
                <div class="queue-type-icon"><i class="fa-solid fa-user-check"></i></div>
                <div class="queue-ticket-main">
                    <div class="queue-ticket-topline"><strong><?= htmlspecialchars($approval['ticket_number']) ?></strong><span>Validation</span></div>
                    <h3><?= htmlspecialchars($approval['title']) ?></h3>
                    <div class="queue-ticket-meta">
                        <span><i class="fa-regular fa-user"></i> <?= htmlspecialchars($approval['requester_name']) ?></span>
                        <span><i class="fa-solid fa-headset"></i> Demandée par <?= htmlspecialchars($approval['requested_by_name']) ?></span>
                        <span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($approval['requested_at']))) ?></span>
                    </div>
                </div>
                <div class="queue-ticket-badges">
                    <span class="priority-pill priority-level-<?= (int) $approval['priority_level'] ?>"><i class="fa-solid fa-flag"></i> <?= htmlspecialchars($approval['priority_name']) ?></span>
                    <span class="approval-status approval-<?= htmlspecialchars($label[1]) ?>"><i class="fa-solid <?= $approval['status'] === 'pending' ? 'fa-hourglass-half' : ($approval['status'] === 'approved' ? 'fa-check' : ($approval['status'] === 'rejected' ? 'fa-xmark' : 'fa-circle-info')) ?>"></i> <?= htmlspecialchars($label[0]) ?></span>
                </div>
                <div class="queue-assignee manager-validation-decision">
                    <span>Décision</span>
                    <strong><?= $approval['comment'] ? htmlspecialchars($approval['comment']) : ($approval['status'] === 'pending' ? 'En attente de votre réponse' : 'Aucun commentaire') ?></strong>
                </div>
                <a class="btn secondary queue-open-btn" href="ticket.php?number=<?= urlencode($approval['ticket_number']) ?>"><i class="fa-solid <?= $approval['status'] === 'pending' ? 'fa-reply' : 'fa-eye' ?>"></i> <?= $approval['status'] === 'pending' ? 'Répondre' : 'Voir' ?></a>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
