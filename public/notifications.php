<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireLogin();
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'Notifications';

$scope = (string) ($_GET['scope'] ?? 'all');
if (!in_array($scope, ['all', 'unread'], true)) {
    $scope = 'all';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validate($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash_error'] = 'La session du formulaire a expiré. Rechargez la page puis réessayez.';
    } else {
        $action = (string) ($_POST['action'] ?? '');
        if ($action === 'mark_all_read') {
            $notificationService->markAllRead((int) $user['id']);
            $_SESSION['flash_success'] = 'Toutes les notifications ont été marquées comme lues.';
        } elseif ($action === 'mark_read') {
            $notificationService->markRead((int) ($_POST['notification_id'] ?? 0), (int) $user['id']);
            $_SESSION['flash_success'] = 'Notification marquée comme lue.';
        } elseif ($action === 'delete') {
            $notificationService->deleteForUser((int) ($_POST['notification_id'] ?? 0), (int) $user['id']);
            $_SESSION['flash_success'] = 'Notification supprimée.';
        } elseif ($action === 'delete_all') {
            $notificationService->deleteAllForUser((int) $user['id']);
            $_SESSION['flash_success'] = 'Toutes vos notifications ont été supprimées.';
        }
    }

    $redirectScope = (string) ($_POST['scope'] ?? $scope);
    if (!in_array($redirectScope, ['all', 'unread'], true)) {
        $redirectScope = 'all';
    }
    header('Location: notifications.php?scope=' . urlencode($redirectScope));
    exit;
}

$notifications = $notificationService->listForUser((int) $user['id'], $scope === 'unread', 100);
$unreadCount = $notificationService->unreadCount((int) $user['id']);

$typeLabels = [
    'new_ticket' => 'Nouveau ticket',
    'assignment' => 'Prise en charge',
    'priority' => 'Importance',
    'status' => 'Statut',
    'transfer' => 'Transfert',
    'message' => 'Message',
    'internal_note' => 'Note interne',
    'resolution' => 'Résolution',
    'manager_approval' => 'Validation Manager',
    'manager_response' => 'Réponse Manager',
    'resolution_confirmed' => 'Résolution confirmée',
    'resolution_rejected' => 'Ticket rouvert',
    'info' => 'Information',
];

require __DIR__ . '/../templates/shared/header.php';
?>
<section class="page-heading page-heading-enhanced notifications-heading-v0135">
    <div><span class="badge"><i class="fa-solid fa-bell"></i> Centre de notifications</span><h1>Notifications</h1><p>Retrouvez les événements importants liés à vos tickets. Les notifications lues sont supprimées automatiquement après 24 heures.</p></div>
    <div class="queue-summary"><i class="fa-solid fa-envelope-open-text"></i><div><strong><?= $unreadCount ?></strong><span>notification<?= $unreadCount > 1 ? 's' : '' ?> non lue<?= $unreadCount > 1 ? 's' : '' ?></span></div></div>
</section>

<section class="panel notifications-toolbar-v0135">
    <div class="ticket-scope-tabs notification-tabs-v0135"><a class="<?= $scope === 'all' ? 'active' : '' ?>" href="notifications.php?scope=all"><i class="fa-solid fa-layer-group"></i> Toutes</a><a class="<?= $scope === 'unread' ? 'active' : '' ?>" href="notifications.php?scope=unread"><i class="fa-solid fa-envelope"></i> Non lues<?= $unreadCount > 0 ? ' (' . $unreadCount . ')' : '' ?></a></div>
    <div class="notification-page-actions">
        <?php if ($unreadCount > 0): ?><form method="post" action="notifications.php?scope=<?= htmlspecialchars($scope) ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="mark_all_read"><input type="hidden" name="scope" value="<?= htmlspecialchars($scope) ?>"><button class="btn secondary" type="submit"><i class="fa-solid fa-check-double"></i> Tout marquer comme lu</button></form><?php endif; ?>
        <?php if ($notifications): ?><form method="post" action="notifications.php?scope=<?= htmlspecialchars($scope) ?>" onsubmit="return confirm('Supprimer toutes vos notifications ? Cette action est irréversible.');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="delete_all"><input type="hidden" name="scope" value="<?= htmlspecialchars($scope) ?>"><button class="btn danger" type="submit"><i class="fa-solid fa-trash-can"></i> Tout supprimer</button></form><?php endif; ?>
    </div>
</section>

<section class="panel notification-panel notification-panel-v0135">
    <?php if (!$notifications): ?><div class="empty-state notification-empty-v0135"><i class="fa-regular fa-bell-slash"></i><strong>Aucune notification dans cette vue.</strong><span>Les nouveaux événements apparaîtront automatiquement ici.</span></div><?php endif; ?>
    <div class="notification-list notification-list-v0135">
        <?php foreach ($notifications as $notification): ?>
            <?php $isUnread = empty($notification['read_at']); $type=$notification['type']; $typeIcon = match($type){'message'=>'fa-comment','assignment'=>'fa-headset','priority'=>'fa-flag','status'=>'fa-arrows-rotate','transfer'=>'fa-right-left','resolution','resolution_confirmed'=>'fa-circle-check','resolution_rejected'=>'fa-rotate-left','manager_approval','manager_response'=>'fa-user-check','new_ticket'=>'fa-ticket',default=>'fa-bell'}; ?>
            <article class="notification-item notification-card-v0135<?= $isUnread ? ' unread' : '' ?>">
                <span class="notification-card-icon"><i class="fa-solid <?= $typeIcon ?>"></i></span>
                <div class="notification-main"><div class="notification-topline"><span class="notification-type"><?= htmlspecialchars($typeLabels[$notification['type']] ?? $notification['type']) ?></span><?php if ($isUnread): ?><span class="notification-unread-badge"><i class="fa-solid fa-circle"></i> Non lue</span><?php endif; ?></div><h2><?= htmlspecialchars($notification['title']) ?></h2><?php if ($notification['message'] !== ''): ?><p><?= htmlspecialchars($notification['message']) ?></p><?php endif; ?><div class="notification-meta"><span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($notification['created_at']))) ?></span><?php if (!empty($notification['ticket_number'])): ?><span><i class="fa-solid fa-ticket"></i> <?= htmlspecialchars($notification['ticket_number']) ?></span><?php endif; ?></div></div>
                <div class="notification-actions notification-actions-v0135"><?php if (!empty($notification['link_url'])): ?><a class="btn secondary small" href="notification-open.php?id=<?= (int)$notification['id'] ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i> Ouvrir</a><?php endif; ?><?php if ($isUnread): ?><form method="post" action="notifications.php?scope=<?= htmlspecialchars($scope) ?>"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="mark_read"><input type="hidden" name="notification_id" value="<?= (int)$notification['id'] ?>"><input type="hidden" name="scope" value="<?= htmlspecialchars($scope) ?>"><button class="btn ghost small" type="submit"><i class="fa-solid fa-check"></i> Lue</button></form><?php endif; ?><form method="post" action="notifications.php?scope=<?= htmlspecialchars($scope) ?>" onsubmit="return confirm('Supprimer cette notification ?');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="notification_id" value="<?= (int)$notification['id'] ?>"><input type="hidden" name="scope" value="<?= htmlspecialchars($scope) ?>"><button class="btn danger small" type="submit"><i class="fa-solid fa-trash"></i></button></form></div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
