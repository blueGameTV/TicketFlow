<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
$auth->requireRole('Administrateur');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'Groupes';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validate($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash_error'] = 'La session du formulaire a expiré.';
        header('Location: admin-groups.php'); exit;
    }
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    if ($id > 0 && $action === 'toggle') {
        $stmt = $pdo->prepare('UPDATE groups_company SET active = NOT active WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $_SESSION['flash_success'] = 'État du groupe modifié.';
    } elseif ($id > 0 && $action === 'delete') {
        $groupStmt = $pdo->prepare('SELECT id, name FROM groups_company WHERE id = :id LIMIT 1');
        $groupStmt->execute(['id' => $id]);
        $group = $groupStmt->fetch();
        if (!$group) {
            $_SESSION['flash_error'] = 'Groupe introuvable.';
        } else {
            $pdo->beginTransaction();
            try {
                // La clé étrangère users.group_id est ON DELETE SET NULL : les membres
                // restent actifs mais deviennent simplement « Non attribué ».
                $stmt = $pdo->prepare('DELETE FROM groups_company WHERE id = :id');
                $stmt->execute(['id' => $id]);
                $auditService->log((int) $user['id'], 'group_deleted', 'group', $id, ['name' => $group['name']]);
                $pdo->commit();
                $_SESSION['flash_success'] = 'Groupe supprimé. Les anciens membres sont maintenant sans groupe.';
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $_SESSION['flash_error'] = 'Impossible de supprimer ce groupe pour le moment.';
            }
        }
    }
    header('Location: admin-groups.php'); exit;
}

$groups = $pdo->query('SELECT g.id, g.name, g.active, g.created_at, m.firstname, m.lastname, COUNT(u.id) AS member_count
                       FROM groups_company g
                       LEFT JOIN users m ON m.id = g.manager_id
                       LEFT JOIN users u ON u.group_id = g.id
                       GROUP BY g.id, g.name, g.active, g.created_at, m.firstname, m.lastname
                       ORDER BY g.active DESC, g.name')->fetchAll();

$success = $_SESSION['flash_success'] ?? null; $error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
require __DIR__ . '/../templates/shared/header.php';
?>
<section class="page-heading page-heading-enhanced admin-groups-heading-v0135">
    <div><span class="badge"><i class="fa-solid fa-people-group"></i> Administration</span><h1>Groupes</h1><p>Organisez les équipes et services de l’entreprise autour d’un Manager responsable.</p></div>
    <div class="admin-groups-heading-actions-v0136">
        <div class="queue-summary"><i class="fa-solid fa-people-roof"></i><div><strong><?= count($groups) ?></strong><span>groupe<?= count($groups) > 1 ? 's' : '' ?> configuré<?= count($groups) > 1 ? 's' : '' ?></span></div></div>
        <a class="btn primary" href="admin-group-form.php"><i class="fa-solid fa-plus"></i> Créer un groupe</a>
    </div>
</section>
<?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<section class="panel admin-groups-panel-v0135">
    <div class="table-header"><div><h2>Groupes de l’entreprise</h2><p class="muted">Un groupe rassemble ses membres et leur Manager de référence.</p></div></div>
    <?php if (!$groups): ?><div class="empty-state queue-empty"><i class="fa-solid fa-people-group"></i><strong>Aucun groupe créé.</strong><span>Créez votre premier groupe pour organiser les utilisateurs.</span></div><?php else: ?>
    <div class="admin-group-card-list">
        <?php foreach ($groups as $group): ?>
        <article class="admin-group-card <?= !$group['active'] ? 'is-inactive' : '' ?>">
            <div class="admin-group-icon"><i class="fa-solid fa-people-group"></i></div>
            <div class="admin-group-main"><strong><?= htmlspecialchars($group['name']) ?></strong><span>Créé le <?= htmlspecialchars(date('d/m/Y', strtotime($group['created_at']))) ?></span></div>
            <div class="admin-group-meta"><span>Manager</span><strong><i class="fa-solid fa-user-tie"></i> <?= $group['firstname'] ? htmlspecialchars($group['firstname'].' '.$group['lastname']) : 'Non défini' ?></strong></div>
            <div class="admin-group-meta"><span>Membres</span><strong><i class="fa-solid fa-users"></i> <?= (int)$group['member_count'] ?></strong></div>
            <div class="admin-group-meta"><span>État</span><strong><span class="status-pill <?= $group['active'] ? 'active' : 'inactive' ?>"><?= $group['active'] ? 'Actif' : 'Désactivé' ?></span></strong></div>
            <div class="admin-group-actions">
                <a class="btn small secondary" href="admin-group-form.php?id=<?= (int)$group['id'] ?>"><i class="fa-solid fa-pen"></i> Modifier</a>
                <form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="id" value="<?= (int)$group['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn small ghost" type="submit"><i class="fa-solid <?= $group['active'] ? 'fa-pause' : 'fa-play' ?>"></i> <?= $group['active'] ? 'Désactiver' : 'Réactiver' ?></button></form>
                <form method="post" class="inline-form" onsubmit="return confirm('Supprimer définitivement ce groupe ? Les membres deviendront sans groupe.');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="id" value="<?= (int)$group['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn small danger" type="submit"><i class="fa-solid fa-trash"></i> Supprimer</button></form>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
