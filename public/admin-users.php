<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireRole('Administrateur');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'Utilisateurs';

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
    <div><span class="badge"><i class="fa-solid fa-users"></i> Administration</span><h1>Utilisateurs</h1><p>Gérez les comptes, les rôles, les groupes et l’état des utilisateurs de TicketFlow.</p></div>
    <div class="queue-summary"><i class="fa-solid fa-user-group"></i><div><strong><?= count($users) ?></strong><span>utilisateur<?= count($users) > 1 ? 's' : '' ?> affiché<?= count($users) > 1 ? 's' : '' ?></span></div></div>
</section>
<?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<section class="panel queue-filter-panel admin-users-filter-v0135">
    <div class="panel-heading-inline queue-filter-heading"><div><h2><i class="fa-solid fa-magnifying-glass"></i> Rechercher et filtrer</h2><p>Recherchez par nom, identifiant ou e-mail.</p></div><a class="btn primary" href="admin-user-form.php"><i class="fa-solid fa-user-plus"></i> Ajouter un utilisateur</a></div>
    <form class="filter-bar ticket-it-filter" method="get">
        <label class="search-field"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?= htmlspecialchars($search) ?>" placeholder="Nom, prénom, identifiant ou e-mail"></label>
        <select name="role"><option value="0">Tous les rôles</option><?php foreach ($roles as $role): ?><option value="<?= (int) $role['id'] ?>" <?= $roleFilter === (int) $role['id'] ? 'selected' : '' ?>><?= htmlspecialchars($role['name']) ?></option><?php endforeach; ?></select>
        <select name="status"><option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>Tous les états</option><option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Actifs</option><option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Désactivés</option></select>
        <button class="btn primary" type="submit"><i class="fa-solid fa-filter"></i> Filtrer</button><a class="btn ghost" href="admin-users.php"><i class="fa-solid fa-rotate-left"></i> Réinitialiser</a>
    </form>
</section>
<section class="panel admin-users-list-v0135">
    <div class="table-header"><div><h2>Comptes utilisateurs</h2><p class="muted"><?= count($users) ?> résultat<?= count($users) > 1 ? 's' : '' ?></p></div><span class="queue-page-chip"><i class="fa-solid fa-address-book"></i> Annuaire</span></div>
    <?php if (!$users): ?><div class="empty-state queue-empty"><i class="fa-solid fa-user-slash"></i><strong>Aucun utilisateur trouvé.</strong><span>Modifiez les filtres ou créez un nouveau compte.</span></div><?php else: ?>
    <div class="admin-user-card-list">
        <?php foreach ($users as $row): ?>
        <?php $roleMeta = match ($row['role_name']) { 'Administrateur' => ['class'=>'role-admin','icon'=>'fa-shield-halved'], 'IT' => ['class'=>'role-it','icon'=>'fa-screwdriver-wrench'], 'Manager' => ['class'=>'role-manager','icon'=>'fa-user-tie'], default => ['class'=>'role-collaborator','icon'=>'fa-user'] }; ?>
        <article class="admin-user-card">
            <div class="admin-user-avatar"><i class="fa-solid fa-user"></i></div>
            <div class="admin-user-main"><strong><?= htmlspecialchars($row['firstname'].' '.$row['lastname']) ?></strong><span>@<?= htmlspecialchars($row['username']) ?> · <?= htmlspecialchars($row['email']) ?></span></div>
            <div><span class="role-badge <?= htmlspecialchars($roleMeta['class']) ?>"><i class="fa-solid <?= htmlspecialchars($roleMeta['icon']) ?>"></i><?= htmlspecialchars($row['role_name']) ?></span></div>
            <div class="admin-user-meta"><span>Groupe</span><strong><?= htmlspecialchars($row['group_name'] ?? 'Non attribué') ?></strong></div>
            <div class="admin-user-meta"><span>Manager</span><strong><?= $row['manager_firstname'] ? htmlspecialchars($row['manager_firstname'].' '.$row['manager_lastname']) : '—' ?></strong></div>
            <div class="admin-user-meta"><span>État</span><strong><span class="status-pill <?= $row['active'] ? 'active' : 'inactive' ?>"><?= $row['active'] ? 'Actif' : 'Désactivé' ?></span></strong></div>
            <div class="admin-user-meta"><span>Dernière connexion</span><strong><?= $row['last_login_at'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($row['last_login_at']))) : 'Jamais' ?></strong></div>
            <a class="btn secondary small admin-user-edit" href="admin-user-form.php?id=<?= (int) $row['id'] ?>"><i class="fa-solid fa-pen"></i> Modifier</a>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
