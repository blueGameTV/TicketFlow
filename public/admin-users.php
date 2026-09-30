<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireRole('Administrateur');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = t('users.title');

$search = trim((string) ($_GET['q'] ?? ''));
$roleFilter = (int) ($_GET['role'] ?? 0);
$statusFilter = (string) ($_GET['status'] ?? 'all');

$sql = 'SELECT u.id, u.firstname, u.lastname, u.username, u.email, u.active, u.arrival_date, u.last_login_at,
               r.name AS role_name, g.name AS group_name, m.firstname AS manager_firstname, m.lastname AS manager_lastname
        FROM users u
        INNER JOIN roles r ON r.id = u.role_id
        LEFT JOIN groups_company g ON g.id = u.group_id
        LEFT JOIN users m ON m.id = g.manager_id
        WHERE 1=1';
$params = [];

if ($search !== '') {
    $term = '%' . $search . '%';
    $sql .= ' AND (u.firstname LIKE :search_firstname OR u.lastname LIKE :search_lastname OR u.username LIKE :search_username OR u.email LIKE :search_email)';
    $params['search_firstname'] = $term;
    $params['search_lastname'] = $term;
    $params['search_username'] = $term;
    $params['search_email'] = $term;
}
if ($roleFilter > 0) {
    $sql .= ' AND u.role_id = :role_id';
    $params['role_id'] = $roleFilter;
}
if ($statusFilter === 'active') {
    $sql .= ' AND u.active = 1';
} elseif ($statusFilter === 'inactive') {
    $sql .= ' AND u.active = 0';
}

$sql .= ' ORDER BY u.lastname, u.firstname';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();
$roles = $pdo->query('SELECT id, name FROM roles ORDER BY id')->fetchAll();

$success = $_SESSION['flash_success'] ?? null;
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require __DIR__ . '/../templates/shared/header.php';
?>
<section class="page-heading page-heading-enhanced admin-users-heading-v0135">
    <div><span class="badge"><i class="fa-solid fa-users"></i> <?= htmlspecialchars(t('common.administration')) ?></span><h1><?= htmlspecialchars(t('users.title')) ?></h1><p><?= htmlspecialchars(t('users.subtitle')) ?></p></div>
    <div class="queue-summary"><i class="fa-solid fa-user-group"></i><div><strong><?= count($users) ?></strong><span><?= htmlspecialchars(t('common.users_displayed',['count'=>count($users)])) ?></span></div></div>
</section>
<?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<section class="panel queue-filter-panel admin-users-filter-v0135">
    <div class="panel-heading-inline queue-filter-heading"><div><h2><i class="fa-solid fa-magnifying-glass"></i> <?= htmlspecialchars(t('users.search_filter')) ?></h2><p><?= htmlspecialchars(t('users.search_help')) ?></p></div><div class="v120-admin-user-actions"><a class="btn secondary" href="admin-user-import.php"><i class="fa-solid fa-file-import"></i> <?= htmlspecialchars(t('users.import')) ?></a><a class="btn primary" href="admin-user-form.php"><i class="fa-solid fa-user-plus"></i> <?= htmlspecialchars(t('users.add')) ?></a></div></div>
    <form class="filter-bar ticket-it-filter" method="get">
        <label class="search-field"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="<?= htmlspecialchars(t('users.search_placeholder')) ?>"></label>
        <select name="role"><option value="0"><?= htmlspecialchars(t('users.all_roles')) ?></option><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>" <?= $roleFilter === (int) $role['id'] ? 'selected' : '' ?>><?= htmlspecialchars($role['name'] === 'IT' ? 'Support IT' : $role['name']) ?></option><?php endforeach; ?></select>
        <select name="status"><option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>><?= htmlspecialchars(t('users.all_states')) ?></option><option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>><?= htmlspecialchars(t('users.active')) ?></option><option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>><?= htmlspecialchars(t('users.inactive')) ?></option></select>
        <button class="btn primary" type="submit"><i class="fa-solid fa-filter"></i> <?= htmlspecialchars(t('common.filter')) ?></button><a class="btn ghost" href="admin-users.php"><i class="fa-solid fa-rotate-left"></i> <?= htmlspecialchars(t('common.reset')) ?></a>
    </form>
</section>
<section class="panel admin-users-list-v0135">
    <div class="table-header"><div><h2><?= htmlspecialchars(t('users.accounts')) ?></h2><p class="muted"><?= count($users) ?> résultat<?= count($users) > 1 ? 's' : '' ?></p></div><span class="queue-page-chip"><i class="fa-solid fa-address-book"></i> <?= htmlspecialchars(t('users.directory')) ?></span></div>
    <?php if (!$users): ?><div class="empty-state queue-empty"><i class="fa-solid fa-user-slash"></i><strong><?= htmlspecialchars(t('users.empty')) ?></strong><span><?= htmlspecialchars(t('users.empty_help')) ?></span></div><?php else: ?>
    <div class="admin-user-card-list">
        <?php foreach ($users as $row): ?>
        <?php $roleMeta = match ($row['role_name']) { 'Administrateur' => ['class'=>'role-admin','icon'=>'fa-shield-halved'], 'IT' => ['class'=>'role-it','icon'=>'fa-screwdriver-wrench'], 'Manager' => ['class'=>'role-manager','icon'=>'fa-user-tie'], default => ['class'=>'role-collaborator','icon'=>'fa-user'] }; ?>
        <article class="admin-user-card">
            <div class="admin-user-avatar"><i class="fa-solid fa-user"></i></div>
            <div class="admin-user-main"><strong><?= htmlspecialchars($row['firstname'].' '.$row['lastname']) ?></strong><span>@<?= htmlspecialchars($row['username']) ?> · <?= htmlspecialchars($row['email']) ?></span></div>
            <div><span class="role-badge <?= htmlspecialchars($roleMeta['class']) ?>"><i class="fa-solid <?= htmlspecialchars($roleMeta['icon']) ?>"></i><?= htmlspecialchars(t('role.'.$row['role_name'])) ?></span></div>
            <div class="admin-user-meta"><span><?= htmlspecialchars(t('common.group')) ?></span><strong><?= htmlspecialchars($row['group_name'] ?? t('common.unassigned')) ?></strong></div>
            <div class="admin-user-meta"><span><?= htmlspecialchars(t('common.manager')) ?></span><strong><?= $row['manager_firstname'] ? htmlspecialchars($row['manager_firstname'].' '.$row['manager_lastname']) : '—' ?></strong></div>
            <div class="admin-user-meta"><span><?= htmlspecialchars(t('common.status')) ?></span><strong><span class="status-pill <?= $row['active'] ? 'active' : 'inactive' ?>"><?= htmlspecialchars($row['active'] ? t('common.active') : t('common.inactive')) ?></span></strong></div>
            <div class="admin-user-meta"><span><?= htmlspecialchars(t('users.last_login')) ?></span><strong><?= $row['last_login_at'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($row['last_login_at']))) : htmlspecialchars(t('common.never')) ?></strong></div>
            <a class="btn secondary small admin-user-edit" href="admin-user-form.php?id=<?= (int) $row['id'] ?>"><i class="fa-solid fa-pen"></i> <?= htmlspecialchars(t('common.edit')) ?></a>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
