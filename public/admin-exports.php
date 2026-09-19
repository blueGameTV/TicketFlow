<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireRole('Administrateur');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'Extractions Excel';

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
        <span class="badge"><i class="fa-solid fa-file-excel"></i> Administration</span>
        <h1>Extractions Excel</h1>
        <p>Exportez les utilisateurs et les tickets sur une période de 6 mois maximum.</p>
        <div class="alert warning export-limit-note export-limit-note-inline"><i class="fa-solid fa-circle-info"></i><div><strong>Limite :</strong> chaque extraction couvre au maximum 6 mois.<br>L'extraction utilisateurs filtre sur la date de création du compte ; l'extraction tickets filtre sur la date de création du ticket.</div></div>
    </div>
</section>

<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>

<section class="export-grid export-grid-v013 export-grid-v0133">
    <article class="panel export-card">
        <div class="export-card-head">
            <div>
                <span class="export-icon"><i class="fa-solid fa-users"></i></span>
                <h2>Extraction utilisateurs</h2>
            </div>
            <span class="file-badge">.xlsx</span>
        </div>
        <p>Comptes, rôles, groupes, Manager, date d'arrivée, état, dernière connexion et logins applicatifs.</p>
        <form method="post" action="export-users.php" class="form-grid export-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
            <div class="field">
                <label for="users_from">Du *</label>
                <input id="users_from" type="date" name="from" value="<?= htmlspecialchars($defaultFrom) ?>" required>
            </div>
            <div class="field">
                <label for="users_to">Au *</label>
                <input id="users_to" type="date" name="to" value="<?= htmlspecialchars($defaultTo) ?>" required>
            </div>
            <div class="field">
                <label for="users_role">Rôle</label>
                <select id="users_role" name="role_id">
                    <option value="0">Tous les rôles</option>
                    <?php foreach ($roles as $role): ?>
                        <option value="<?= (int) $role['id'] ?>"><?= htmlspecialchars($role['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="users_group">Groupe</label>
                <select id="users_group" name="group_id">
                    <option value="0">Tous les groupes</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field span-2">
                <label for="users_state">État du compte</label>
                <select id="users_state" name="state">
                    <option value="all">Tous</option>
                    <option value="active">Actifs</option>
                    <option value="inactive">Désactivés</option>
                </select>
            </div>
            <div class="form-actions span-2">
                <button class="btn primary" type="submit">Télécharger l'Excel utilisateurs</button>
            </div>
        </form>
    </article>

    <article class="panel export-card">
        <div class="export-card-head">
            <div>
                <span class="export-icon"><i class="fa-solid fa-ticket"></i></span>
                <h2>Extraction tickets</h2>
            </div>
            <span class="file-badge">.xlsx</span>
        </div>
        <p>Tickets, demandeurs, groupes, IT assignés, importance, statuts et temps de résolution.</p>
        <form method="post" action="export-tickets.php" class="form-grid export-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
            <div class="field">
                <label for="tickets_from">Du *</label>
                <input id="tickets_from" type="date" name="from" value="<?= htmlspecialchars($defaultFrom) ?>" required>
            </div>
            <div class="field">
                <label for="tickets_to">Au *</label>
                <input id="tickets_to" type="date" name="to" value="<?= htmlspecialchars($defaultTo) ?>" required>
            </div>
            <div class="field">
                <label for="ticket_type">Type</label>
                <select id="ticket_type" name="type_id">
                    <option value="0">Tous les types</option>
                    <?php foreach ($types as $type): ?>
                        <option value="<?= (int) $type['id'] ?>"><?= htmlspecialchars($type['code'] . ' — ' . $type['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="ticket_status">Statut</label>
                <select id="ticket_status" name="status_id">
                    <option value="0">Tous les statuts</option>
                    <?php foreach ($statuses as $status): ?>
                        <option value="<?= (int) $status['id'] ?>"><?= htmlspecialchars($status['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="ticket_priority">Importance</label>
                <select id="ticket_priority" name="priority_id">
                    <option value="0">Toutes</option>
                    <?php foreach ($priorities as $priority): ?>
                        <option value="<?= (int) $priority['id'] ?>"><?= htmlspecialchars($priority['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field">
                <label for="ticket_group">Groupe demandeur</label>
                <select id="ticket_group" name="group_id">
                    <option value="0">Tous les groupes</option>
                    <?php foreach ($groups as $group): ?>
                        <option value="<?= (int) $group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="field span-2">
                <label for="ticket_it">IT assigné</label>
                <select id="ticket_it" name="assigned_it_id">
                    <option value="0">Tous les IT</option>
                    <option value="-1">Non attribués</option>
                    <?php foreach ($itUsers as $it): ?>
                        <option value="<?= (int) $it['id'] ?>"><?= htmlspecialchars($it['firstname'] . ' ' . $it['lastname']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-actions span-2">
                <button class="btn primary" type="submit">Télécharger l'Excel tickets</button>
            </div>
        </form>
    </article>
</section>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
