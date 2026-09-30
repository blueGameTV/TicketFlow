<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireRole('Administrateur');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = t('exports.title');

$today = new DateTimeImmutable('today');
$defaultFrom = $today->sub(new DateInterval('P30D'))->format('Y-m-d');
$defaultTo = $today->format('Y-m-d');

$roles = $pdo->query('SELECT id, name FROM roles ORDER BY id')->fetchAll();
$groups = $pdo->query('SELECT id, name FROM groups_company ORDER BY name')->fetchAll();
$types = $pdo->query('SELECT id, code, name FROM ticket_types ORDER BY id')->fetchAll();
$statuses = $pdo->query('SELECT id, code, name FROM ticket_statuses ORDER BY id')->fetchAll();
$priorities = $pdo->query('SELECT id, name FROM priorities ORDER BY level')->fetchAll();
$itUsers = $pdo->query("SELECT u.id, u.firstname, u.lastname FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE r.name='IT' ORDER BY u.lastname, u.firstname")->fetchAll();

$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_error']);

require __DIR__ . '/../templates/shared/header.php';
?>
<section class="page-heading export-page-heading page-heading-enhanced">
    <div>
        <span class="badge"><i class="fa-solid fa-file-excel"></i> <?= htmlspecialchars(t('common.administration')) ?></span>
        <h1><?= htmlspecialchars(t('exports.title')) ?></h1>
        <p><?= htmlspecialchars(t('exports.subtitle')) ?></p>
        <div class="alert warning export-limit-note export-limit-note-inline"><i class="fa-solid fa-circle-info"></i><div><strong><?= htmlspecialchars(t('exports.limit')) ?></strong> <?= htmlspecialchars(t('exports.limit_help')) ?></div></div>
    </div>
</section>

<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<section class="export-grid export-grid-v013 export-grid-v0133">
    <article class="panel export-card">
        <div class="export-card-head"><div><span class="export-icon"><i class="fa-solid fa-users"></i></span><h2><?= htmlspecialchars(t('exports.users')) ?></h2></div><span class="file-badge">.xlsx</span></div>
        <p><?= htmlspecialchars(t('exports.users_help')) ?></p>
        <form method="post" action="export-users.php" class="form-grid export-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
            <div class="field"><label for="users_from"><?= htmlspecialchars(t('common.from')) ?></label><input id="users_from" type="date" name="from" value="<?= htmlspecialchars($defaultFrom) ?>" required></div>
            <div class="field"><label for="users_to"><?= htmlspecialchars(t('common.to')) ?></label><input id="users_to" type="date" name="to" value="<?= htmlspecialchars($defaultTo) ?>" required></div>
            <div class="field"><label for="users_role"><?= htmlspecialchars(t('exports.role')) ?></label><select id="users_role" name="role_id"><option value="0"><?= htmlspecialchars(t('exports.all_roles')) ?></option><?php foreach ($roles as $role): ?><option value="<?= (int)$role['id'] ?>"><?= htmlspecialchars(t('role.'.$role['name'])) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="users_group"><?= htmlspecialchars(t('exports.group')) ?></label><select id="users_group" name="group_id"><option value="0"><?= htmlspecialchars(t('exports.all_groups')) ?></option><?php foreach ($groups as $group): ?><option value="<?= (int)$group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field span-2"><label for="users_state"><?= htmlspecialchars(t('exports.account_state')) ?></label><select id="users_state" name="state"><option value="all"><?= htmlspecialchars(t('common.all')) ?></option><option value="active"><?= htmlspecialchars(t('exports.active')) ?></option><option value="inactive"><?= htmlspecialchars(t('exports.inactive')) ?></option></select></div>
            <div class="form-actions span-2"><button class="btn primary" type="submit"><?= htmlspecialchars(t('exports.download_users')) ?></button></div>
        </form>
    </article>

    <article class="panel export-card">
        <div class="export-card-head"><div><span class="export-icon"><i class="fa-solid fa-ticket"></i></span><h2><?= htmlspecialchars(t('exports.tickets')) ?></h2></div><span class="file-badge">.xlsx</span></div>
        <p><?= htmlspecialchars(t('exports.tickets_help')) ?></p>
        <form method="post" action="export-tickets.php" class="form-grid export-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
            <div class="field"><label for="tickets_from"><?= htmlspecialchars(t('common.from')) ?></label><input id="tickets_from" type="date" name="from" value="<?= htmlspecialchars($defaultFrom) ?>" required></div>
            <div class="field"><label for="tickets_to"><?= htmlspecialchars(t('common.to')) ?></label><input id="tickets_to" type="date" name="to" value="<?= htmlspecialchars($defaultTo) ?>" required></div>
            <div class="field"><label for="ticket_type"><?= htmlspecialchars(t('exports.type')) ?></label><select id="ticket_type" name="type_id"><option value="0"><?= htmlspecialchars(t('exports.all_types')) ?></option><?php foreach ($types as $type): ?><option value="<?= (int)$type['id'] ?>"><?= htmlspecialchars($type['code'] . ' — ' . $type['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="ticket_status"><?= htmlspecialchars(t('exports.status')) ?></label><select id="ticket_status" name="status_id"><option value="0"><?= htmlspecialchars(t('exports.all_statuses')) ?></option><?php foreach ($statuses as $status): ?><option value="<?= (int)$status['id'] ?>"><?= htmlspecialchars(t('status.'.$status['code'])) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="ticket_priority"><?= htmlspecialchars(t('exports.priority')) ?></label><select id="ticket_priority" name="priority_id"><option value="0"><?= htmlspecialchars(t('exports.all_priorities')) ?></option><?php foreach ($priorities as $priority): ?><option value="<?= (int)$priority['id'] ?>"><?= htmlspecialchars(t('priority.'.strtolower($priority['name']))) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label for="ticket_group"><?= htmlspecialchars(t('exports.requester_group')) ?></label><select id="ticket_group" name="group_id"><option value="0"><?= htmlspecialchars(t('exports.all_groups')) ?></option><?php foreach ($groups as $group): ?><option value="<?= (int)$group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field span-2"><label for="ticket_it"><?= htmlspecialchars(t('exports.assigned_it')) ?></label><select id="ticket_it" name="assigned_it_id"><option value="0"><?= htmlspecialchars(t('exports.all_it')) ?></option><option value="-1"><?= htmlspecialchars(t('exports.unassigned')) ?></option><?php foreach ($itUsers as $it): ?><option value="<?= (int)$it['id'] ?>"><?= htmlspecialchars($it['firstname'] . ' ' . $it['lastname']) ?></option><?php endforeach; ?></select></div>
            <div class="form-actions span-2"><button class="btn primary" type="submit"><?= htmlspecialchars(t('exports.download_tickets')) ?></button></div>
        </form>
    </article>

    <article class="panel export-card v110-span-2">
        <div class="export-card-head"><div><span class="export-icon"><i class="fa-solid fa-chart-gantt"></i></span><h2><?= htmlspecialchars(t('exports.operations')) ?></h2></div><span class="file-badge"><?= htmlspecialchars(t('exports.new_xlsx')) ?></span></div>
        <p><?= htmlspecialchars(t('exports.operations_help')) ?></p>
        <form method="post" action="export-operations.php" class="form-grid export-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
            <div class="field"><label for="operations_from"><?= htmlspecialchars(t('common.from')) ?></label><input id="operations_from" type="date" name="from" value="<?= htmlspecialchars($defaultFrom) ?>" required></div>
            <div class="field"><label for="operations_to"><?= htmlspecialchars(t('common.to')) ?></label><input id="operations_to" type="date" name="to" value="<?= htmlspecialchars($defaultTo) ?>" required></div>
            <div class="form-actions span-2"><button class="btn primary" type="submit"><i class="fa-solid fa-file-excel"></i> <?= htmlspecialchars(t('exports.download_operations')) ?></button></div>
        </form>
    </article>
</section>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
