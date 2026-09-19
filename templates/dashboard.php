<?php
$role = (string) $user['role'];
$quickCards = [];
$secondaryActions = [];

if ($role === 'Administrateur') {
    $dashboardStats = $adminStatisticsService->dashboard(30);
    $summary = $dashboardStats['summary'];
    $quickCards = [
        ['icon'=>'fa-ticket','value'=>$summary['open'],'label'=>'Tickets ouverts','text'=>'Tickets nécessitant encore une action.','href'=>'it-tickets.php?scope=all'],
        ['icon'=>'fa-user-clock','value'=>$summary['unassigned'],'label'=>'Non attribués','text'=>'Tickets ouverts sans technicien IT.','href'=>'it-tickets.php?scope=unassigned'],
        ['icon'=>'fa-triangle-exclamation','value'=>$summary['critical_open'],'label'=>'Critiques','text'=>'Tickets critiques actuellement ouverts.','href'=>'it-tickets.php?scope=all'],
        ['icon'=>'fa-user-check','value'=>$summary['pending_manager'],'label'=>'Validations','text'=>'Demandes Manager encore en attente.','href'=>'it-tickets.php?scope=all'],
    ];
    $secondaryActions = [
        ['icon'=>'fa-users','label'=>'Utilisateurs','text'=>'Gérer les comptes et les rôles','href'=>'admin-users.php'],
        ['icon'=>'fa-people-group','label'=>'Groupes','text'=>'Gérer les services et Managers','href'=>'admin-groups.php'],
        ['icon'=>'fa-chart-line','label'=>'Statistiques','text'=>'Analyser l’activité de TicketFlow','href'=>'admin-statistics.php'],
        ['icon'=>'fa-file-export','label'=>'Extractions','text'=>'Exporter les utilisateurs et tickets','href'=>'admin-exports.php'],
    ];
} elseif ($role === 'IT') {
    $statsStmt = $pdo->prepare(
        "SELECT
            SUM(CASE WHEN t.assigned_it_id = :uid_mine AND ts.code NOT IN ('resolved','closed','cancelled') THEN 1 ELSE 0 END) AS mine_count,
            SUM(CASE WHEN t.assigned_it_id IS NULL AND ts.code NOT IN ('resolved','closed','cancelled') THEN 1 ELSE 0 END) AS unassigned_count,
            SUM(CASE WHEN ts.code = 'waiting_manager' THEN 1 ELSE 0 END) AS manager_wait_count,
            SUM(CASE WHEN assigned_it.group_id = :team_group_id AND ts.code NOT IN ('resolved','closed','cancelled') THEN 1 ELSE 0 END) AS team_count
         FROM tickets t
         INNER JOIN ticket_statuses ts ON ts.id = t.status_id
         LEFT JOIN users assigned_it ON assigned_it.id = t.assigned_it_id
         WHERE t.deleted_at IS NULL"
    );
    $statsStmt->execute([
        'uid_mine' => (int) $user['id'],
        'team_group_id' => $user['group_id'] !== null ? (int) $user['group_id'] : -1,
    ]);
    $stats = $statsStmt->fetch() ?: [];

    $quickCards = [
        ['icon'=>'fa-inbox','value'=>(int)($stats['mine_count']??0),'label'=>'Mes tickets','text'=>'Tickets actuellement attribués à votre compte.','href'=>'it-tickets.php?scope=mine'],
        ['icon'=>'fa-box-open','value'=>(int)($stats['unassigned_count']??0),'label'=>'Non attribués','text'=>'Tickets disponibles à prendre en charge.','href'=>'it-tickets.php?scope=unassigned'],
        ['icon'=>'fa-user-check','value'=>(int)($stats['manager_wait_count']??0),'label'=>'Validations','text'=>'Tickets actuellement en attente d’un Manager.','href'=>'it-tickets.php?scope=mine&status=waiting_manager'],
        ['icon'=>'fa-users-gear','value'=>(int)($stats['team_count']??0),'label'=>'Équipe IT','text'=>'Tickets ouverts suivis par votre groupe IT.','href'=>'it-tickets.php?scope=team'],
    ];
    $secondaryActions = [
        ['icon'=>'fa-inbox','label'=>'File personnelle','text'=>'Voir tous mes tickets','href'=>'it-tickets.php?scope=mine'],
        ['icon'=>'fa-box-open','label'=>'Tickets non attribués','text'=>'Prendre en charge une nouvelle demande','href'=>'it-tickets.php?scope=unassigned'],
        ['icon'=>'fa-user-group','label'=>'Équipe IT','text'=>'Suivre les tickets du groupe','href'=>'it-tickets.php?scope=team'],
    ];
} elseif ($role === 'Manager') {
    $ticketStmt=$pdo->prepare("SELECT SUM(CASE WHEN ts.code NOT IN ('resolved','closed','cancelled') THEN 1 ELSE 0 END) open_count, SUM(CASE WHEN ts.code='waiting_confirmation' THEN 1 ELSE 0 END) confirmation_count FROM tickets t INNER JOIN ticket_statuses ts ON ts.id=t.status_id WHERE t.requester_id=:id AND t.deleted_at IS NULL");
    $ticketStmt->execute(['id'=>$user['id']]); $s=$ticketStmt->fetch();
    $ap=$pdo->prepare("SELECT COUNT(*) FROM manager_approvals WHERE manager_id=:id AND status='pending'"); $ap->execute(['id'=>$user['id']]);
    $quickCards = [
        ['icon'=>'fa-circle-check','value'=>(int)$ap->fetchColumn(),'label'=>'Validations','text'=>'Demandes IT nécessitant votre décision.','href'=>'manager-approvals.php'],
        ['icon'=>'fa-ticket','value'=>(int)($s['open_count']??0),'label'=>'Mes tickets','text'=>'Vos demandes actuellement ouvertes.','href'=>'my-tickets.php'],
        ['icon'=>'fa-hourglass-half','value'=>(int)($s['confirmation_count']??0),'label'=>'À confirmer','text'=>'Résolutions qui attendent votre confirmation.','href'=>'my-tickets.php?status=waiting_confirmation'],
    ];
    $secondaryActions = [['icon'=>'fa-plus','label'=>'Nouveau ticket','text'=>'Créer une nouvelle demande','href'=>'ticket-create.php']];
} else {
    $ticketStmt=$pdo->prepare("SELECT SUM(CASE WHEN ts.code NOT IN ('resolved','closed','cancelled') THEN 1 ELSE 0 END) open_count, SUM(CASE WHEN ts.code='waiting_confirmation' THEN 1 ELSE 0 END) confirmation_count FROM tickets t INNER JOIN ticket_statuses ts ON ts.id=t.status_id WHERE t.requester_id=:id AND t.deleted_at IS NULL");
    $ticketStmt->execute(['id'=>$user['id']]); $s=$ticketStmt->fetch();
    $quickCards = [
        ['icon'=>'fa-ticket','value'=>(int)($s['open_count']??0),'label'=>'Tickets ouverts','text'=>'Vos demandes actuellement en traitement.','href'=>'my-tickets.php'],
        ['icon'=>'fa-hourglass-half','value'=>(int)($s['confirmation_count']??0),'label'=>'À confirmer','text'=>'Résolutions qui attendent votre réponse.','href'=>'my-tickets.php?status=waiting_confirmation'],
    ];
    $secondaryActions = [['icon'=>'fa-plus','label'=>'Nouveau ticket','text'=>'Signaler un incident ou faire une demande','href'=>'ticket-create.php']];
}
?>
<section class="dashboard-hero-v013">
    <div><span class="eyebrow"><?= htmlspecialchars($role) ?></span><h1>Bonjour <?= htmlspecialchars($user['firstname']) ?> 👋</h1><p>Voici un aperçu de votre activité TicketFlow.</p></div>
    <?php if (in_array($role,['Collaborateur','Manager'],true)): ?><a class="btn primary" href="ticket-create.php"><i class="fa-solid fa-plus"></i> Nouveau ticket</a><?php endif; ?>
</section>

<section class="metric-grid-v013">
<?php foreach ($quickCards as $card): ?>
    <a class="metric-card-v013" href="<?= htmlspecialchars($card['href']) ?>">
        <div class="metric-icon-v013"><i class="fa-solid <?= htmlspecialchars($card['icon']) ?>"></i></div>
        <div><span class="metric-value-v013"><?= (int)$card['value'] ?></span><h2><?= htmlspecialchars($card['label']) ?></h2><p><?= htmlspecialchars($card['text']) ?></p></div>
        <i class="fa-solid fa-arrow-right metric-arrow"></i>
    </a>
<?php endforeach; ?>
</section>

<section class="panel panel-v013 dashboard-actions-panel">
    <div class="panel-heading-v013"><div><span class="eyebrow">Accès rapide</span><h2>Actions</h2></div></div>
    <div class="quick-action-grid-v013">
    <?php foreach ($secondaryActions as $action): ?>
        <a href="<?= htmlspecialchars($action['href']) ?>" class="quick-action-v013"><span><i class="fa-solid <?= htmlspecialchars($action['icon']) ?>"></i></span><div><strong><?= htmlspecialchars($action['label']) ?></strong><small><?= htmlspecialchars($action['text']) ?></small></div><i class="fa-solid fa-chevron-right"></i></a>
    <?php endforeach; ?>
        <a href="notifications.php" class="quick-action-v013"><span><i class="fa-regular fa-bell"></i></span><div><strong>Notifications</strong><small>Consulter les événements récents</small></div><i class="fa-solid fa-chevron-right"></i></a>
        <a href="settings.php" class="quick-action-v013"><span><i class="fa-solid fa-gear"></i></span><div><strong>Paramètres</strong><small>Profil, interface et sécurité</small></div><i class="fa-solid fa-chevron-right"></i></a>
    </div>
</section>
