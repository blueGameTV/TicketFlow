<?php
$role = (string) $user['role'];
$quickCards = [];
$secondaryActions = [];

if ($role === 'Administrateur') {
    $dashboardStats = $adminStatisticsService->dashboard(30);
    $summary = $dashboardStats['summary'];
    $quickCards = [
        ['icon'=>'fa-ticket','value'=>$summary['open'],'label'=>t('dashboard.open_tickets'),'text'=>t('dashboard.open_help'),'href'=>'it-tickets.php?scope=all'],
        ['icon'=>'fa-user-clock','value'=>$summary['unassigned'],'label'=>t('dashboard.unassigned'),'text'=>t('dashboard.unassigned_help'),'href'=>'it-tickets.php?scope=unassigned'],
        ['icon'=>'fa-triangle-exclamation','value'=>$summary['critical_open'],'label'=>t('dashboard.critical'),'text'=>t('dashboard.critical_help'),'href'=>'it-tickets.php?scope=all'],
        ['icon'=>'fa-user-check','value'=>$summary['pending_manager'],'label'=>t('dashboard.validations'),'text'=>t('dashboard.validations_help'),'href'=>'it-tickets.php?scope=all'],
    ];
    $secondaryActions = [
        ['icon'=>'fa-users','label'=>t('dashboard.users'),'text'=>t('dashboard.manage_users'),'href'=>'admin-users.php'],
        ['icon'=>'fa-people-group','label'=>t('dashboard.groups'),'text'=>t('dashboard.manage_groups'),'href'=>'admin-groups.php'],
        ['icon'=>'fa-chart-line','label'=>t('dashboard.statistics'),'text'=>t('dashboard.analyze'),'href'=>'admin-statistics.php'],
        ['icon'=>'fa-file-export','label'=>t('dashboard.exports'),'text'=>t('dashboard.export'),'href'=>'admin-exports.php'],
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
        ['icon'=>'fa-inbox','value'=>(int)($stats['mine_count']??0),'label'=>t('dashboard.my_tickets'),'text'=>t('dashboard.my_tickets_help'),'href'=>'it-tickets.php?scope=mine'],
        ['icon'=>'fa-box-open','value'=>(int)($stats['unassigned_count']??0),'label'=>t('dashboard.unassigned'),'text'=>t('dashboard.available'),'href'=>'it-tickets.php?scope=unassigned'],
        ['icon'=>'fa-user-check','value'=>(int)($stats['manager_wait_count']??0),'label'=>t('dashboard.validations'),'text'=>t('dashboard.validations_help'),'href'=>'it-tickets.php?scope=mine&status=waiting_manager'],
        ['icon'=>'fa-users-gear','value'=>(int)($stats['team_count']??0),'label'=>t('dashboard.team'),'text'=>t('dashboard.team_help'),'href'=>'it-tickets.php?scope=team'],
    ];
    $secondaryActions = [
        ['icon'=>'fa-inbox','label'=>t('dashboard.personal_queue'),'text'=>t('dashboard.personal_queue_help'),'href'=>'it-tickets.php?scope=mine'],
        ['icon'=>'fa-box-open','label'=>t('dashboard.unassigned_action'),'text'=>t('dashboard.take_request'),'href'=>'it-tickets.php?scope=unassigned'],
        ['icon'=>'fa-user-group','label'=>t('dashboard.team'),'text'=>t('dashboard.follow_group'),'href'=>'it-tickets.php?scope=team'],
    ];
} elseif ($role === 'Manager') {
    $ticketStmt=$pdo->prepare("SELECT SUM(CASE WHEN ts.code NOT IN ('resolved','closed','cancelled') THEN 1 ELSE 0 END) open_count, SUM(CASE WHEN ts.code='waiting_confirmation' THEN 1 ELSE 0 END) confirmation_count FROM tickets t INNER JOIN ticket_statuses ts ON ts.id=t.status_id WHERE t.requester_id=:id AND t.deleted_at IS NULL");
    $ticketStmt->execute(['id'=>$user['id']]); $s=$ticketStmt->fetch();
    $ap=$pdo->prepare("SELECT COUNT(*) FROM manager_approvals WHERE manager_id=:id AND status='pending'"); $ap->execute(['id'=>$user['id']]);
    $quickCards = [
        ['icon'=>'fa-circle-check','value'=>(int)$ap->fetchColumn(),'label'=>t('dashboard.validations'),'text'=>t('dashboard.manager_validations_help'),'href'=>'manager-approvals.php'],
        ['icon'=>'fa-ticket','value'=>(int)($s['open_count']??0),'label'=>t('dashboard.my_tickets'),'text'=>t('dashboard.user_open_help'),'href'=>'my-tickets.php'],
        ['icon'=>'fa-hourglass-half','value'=>(int)($s['confirmation_count']??0),'label'=>t('dashboard.confirm'),'text'=>t('dashboard.to_confirm_help'),'href'=>'my-tickets.php?status=waiting_confirmation'],
    ];
    $secondaryActions = [['icon'=>'fa-plus','label'=>t('nav.new_ticket'),'text'=>t('dashboard.new_request'),'href'=>'ticket-create.php']];
} else {
    $ticketStmt=$pdo->prepare("SELECT SUM(CASE WHEN ts.code NOT IN ('resolved','closed','cancelled') THEN 1 ELSE 0 END) open_count, SUM(CASE WHEN ts.code='waiting_confirmation' THEN 1 ELSE 0 END) confirmation_count FROM tickets t INNER JOIN ticket_statuses ts ON ts.id=t.status_id WHERE t.requester_id=:id AND t.deleted_at IS NULL");
    $ticketStmt->execute(['id'=>$user['id']]); $s=$ticketStmt->fetch();
    $quickCards = [
        ['icon'=>'fa-ticket','value'=>(int)($s['open_count']??0),'label'=>t('dashboard.open_tickets'),'text'=>t('dashboard.employee_open_help'),'href'=>'my-tickets.php'],
        ['icon'=>'fa-hourglass-half','value'=>(int)($s['confirmation_count']??0),'label'=>t('dashboard.confirm'),'text'=>t('dashboard.employee_confirm_help'),'href'=>'my-tickets.php?status=waiting_confirmation'],
    ];
    $secondaryActions = [['icon'=>'fa-plus','label'=>t('nav.new_ticket'),'text'=>t('dashboard.report_request'),'href'=>'ticket-create.php']];
}
?>
<section class="dashboard-hero-v013">
    <div><span class="eyebrow"><?= htmlspecialchars(t('role.'.$role)) ?></span><h1><?= htmlspecialchars(t('dashboard.hello', ['name'=>$user['firstname']])) ?></h1><p><?= htmlspecialchars(t('dashboard.overview')) ?></p></div>
    <?php if (in_array($role,['Collaborateur','Manager'],true)): ?><a class="btn primary" href="ticket-create.php"><i class="fa-solid fa-plus"></i> <?= htmlspecialchars(t('nav.new_ticket')) ?></a><?php endif; ?>
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
    <div class="panel-heading-v013"><div><span class="eyebrow"><?= htmlspecialchars(t('dashboard.quick_access')) ?></span><h2><?= htmlspecialchars(t('dashboard.actions')) ?></h2></div></div>
    <div class="quick-action-grid-v013">
    <?php foreach ($secondaryActions as $action): ?>
        <a href="<?= htmlspecialchars($action['href']) ?>" class="quick-action-v013"><span><i class="fa-solid <?= htmlspecialchars($action['icon']) ?>"></i></span><div><strong><?= htmlspecialchars($action['label']) ?></strong><small><?= htmlspecialchars($action['text']) ?></small></div><i class="fa-solid fa-chevron-right"></i></a>
    <?php endforeach; ?>
        <a href="notifications.php" class="quick-action-v013"><span><i class="fa-regular fa-bell"></i></span><div><strong><?= htmlspecialchars(t('nav.notifications')) ?></strong><small><?= htmlspecialchars(t('dashboard.notifications_help')) ?></small></div><i class="fa-solid fa-chevron-right"></i></a>
        <a href="settings.php" class="quick-action-v013"><span><i class="fa-solid fa-gear"></i></span><div><strong><?= htmlspecialchars(t('nav.settings')) ?></strong><small><?= htmlspecialchars(t('dashboard.settings_help')) ?></small></div><i class="fa-solid fa-chevron-right"></i></a>
    </div>
</section>
