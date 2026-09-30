<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
$auth->requireRole('Administrateur');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = t('groups.title');

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
    <div><span class="badge"><i class="fa-solid fa-people-group"></i> <?= htmlspecialchars(t('common.administration')) ?></span><h1><?= htmlspecialchars(t('groups.title')) ?></h1><p><?= htmlspecialchars(t('groups.subtitle')) ?></p></div>
    <div class="admin-groups-heading-actions-v0136">
        <div class="queue-summary"><i class="fa-solid fa-people-roof"></i><div><strong><?= count($groups) ?></strong><span><?= htmlspecialchars(t('common.groups_configured',['count'=>count($groups)])) ?></span></div></div>
        <a class="btn primary" href="admin-group-form.php"><i class="fa-solid fa-plus"></i> <?= htmlspecialchars(t('groups.create')) ?></a>
    </div>
</section>
<?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<section class="panel admin-groups-panel-v0135">
    <div class="table-header"><div><h2><?= htmlspecialchars(t('groups.company')) ?></h2><p class="muted"><?= htmlspecialchars(t('groups.company_help')) ?></p></div></div>
    <?php if (!$groups): ?><div class="empty-state queue-empty"><i class="fa-solid fa-people-group"></i><strong><?= htmlspecialchars(t('groups.empty')) ?></strong><span><?= htmlspecialchars(t('groups.empty_help')) ?></span></div><?php else: ?>
    <div class="admin-group-card-list">
        <?php foreach ($groups as $group): ?>
        <article class="admin-group-card <?= !$group['active'] ? 'is-inactive' : '' ?>">
            <div class="admin-group-icon"><i class="fa-solid fa-people-group"></i></div>
            <div class="admin-group-main"><strong><?= htmlspecialchars($group['name']) ?></strong><span><?= htmlspecialchars(t('common.created_on',['date'=>date('d/m/Y', strtotime($group['created_at']))])) ?></span></div>
            <div class="admin-group-meta"><span><?= htmlspecialchars(t('common.manager')) ?></span><strong><i class="fa-solid fa-user-tie"></i> <?= $group['firstname'] ? htmlspecialchars($group['firstname'].' '.$group['lastname']) : htmlspecialchars(t('common.not_defined')) ?></strong></div>
            <div class="admin-group-meta"><span><?= htmlspecialchars(t('groups.members')) ?></span><strong><i class="fa-solid fa-users"></i> <?= (int)$group['member_count'] ?></strong></div>
            <div class="admin-group-meta"><span><?= htmlspecialchars(t('common.status')) ?></span><strong><span class="status-pill <?= $group['active'] ? 'active' : 'inactive' ?>"><?= htmlspecialchars($group['active'] ? t('common.active') : t('common.inactive')) ?></span></strong></div>
            <div class="admin-group-actions">
                <a class="btn small secondary" href="admin-group-form.php?id=<?= (int)$group['id'] ?>"><i class="fa-solid fa-pen"></i> <?= htmlspecialchars(t('common.edit')) ?></a>
                <form method="post" class="inline-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="id" value="<?= (int)$group['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn small ghost" type="submit"><i class="fa-solid <?= $group['active'] ? 'fa-pause' : 'fa-play' ?>"></i> <?= htmlspecialchars($group['active'] ? t('common.disable') : t('common.enable')) ?></button></form>
                <form method="post" class="inline-form" onsubmit="return confirm('<?= htmlspecialchars(addslashes(t('groups.delete_confirm'))) ?>');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="id" value="<?= (int)$group['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn small danger" type="submit"><i class="fa-solid fa-trash"></i> <?= htmlspecialchars(t('common.delete')) ?></button></form>
            </div>
        </article>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
