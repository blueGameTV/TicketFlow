<?php
$currentPage = basename((string) ($_SERVER['PHP_SELF'] ?? ''));
$role = (string) ($user['role'] ?? '');
$headerUnreadNotifications = isset($notificationService) ? $notificationService->unreadCount((int) ($user['id'] ?? 0)) : 0;

$prefStmt = $pdo->prepare('SELECT theme, density, sidebar_mode FROM user_preferences WHERE user_id = :uid');
$prefStmt->execute(['uid' => $user['id']]);
$uiPreferences = $prefStmt->fetch() ?: ['theme' => 'light', 'density' => 'comfortable', 'sidebar_mode' => 'expanded'];

$profileStmt = $pdo->prepare('SELECT username, profile_photo FROM users WHERE id = :uid');
$profileStmt->execute(['uid' => $user['id']]);
$headerProfile = $profileStmt->fetch() ?: ['username' => $user['username'] ?? '', 'profile_photo' => $user['profile_photo'] ?? null];

$isActive = static function (array $pages) use ($currentPage): string {
    return in_array($currentPage, $pages, true) ? ' active' : '';
};
?>
<!doctype html>
<html lang="fr" data-theme="<?= htmlspecialchars((string) $uiPreferences['theme']) ?>" data-density="<?= htmlspecialchars((string) $uiPreferences['density']) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <title><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?> - <?= htmlspecialchars($appName) ?></title>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css?v=0.15.2.1">
    <link rel="stylesheet" href="assets/css/v013.css?v=0.15.2.1">
</head>
<body class="app-body role-<?= htmlspecialchars(strtolower(str_replace(['é','è','ê','à','ù','ç',' '], ['e','e','e','a','u','c','-'], $role))) ?> sidebar-<?= htmlspecialchars((string) $uiPreferences['sidebar_mode']) ?>">
<div class="app-shell">
    <aside class="sidebar" id="app-sidebar" aria-label="Navigation principale">
        <div class="sidebar-brand">
            <a href="dashboard.php" class="brand-lockup"><span class="brand-icon"><i class="fa-solid fa-ticket"></i></span><span class="brand-text"><?= htmlspecialchars($appName) ?></span></a>
            <button class="sidebar-close" type="button" data-sidebar-close aria-label="Fermer le menu"><i class="fa-solid fa-xmark"></i></button>
        </div>

        <nav class="sidebar-nav">
            <a class="nav-item<?= $isActive(['dashboard.php']) ?>" href="dashboard.php"><i class="fa-solid fa-house"></i><span>Dashboard</span></a>

            <?php if ($role === 'Administrateur'): ?>
                <div class="nav-section-label">Administration</div>
                <a class="nav-item<?= $isActive(['admin-users.php','admin-user-form.php']) ?>" href="admin-users.php"><i class="fa-solid fa-users"></i><span>Utilisateurs</span></a>
                <a class="nav-item<?= $isActive(['admin-groups.php','admin-group-form.php']) ?>" href="admin-groups.php"><i class="fa-solid fa-people-group"></i><span>Groupes</span></a>
                <a class="nav-item<?= $isActive(['it-tickets.php','ticket.php']) ?>" href="it-tickets.php?scope=all"><i class="fa-solid fa-ticket-simple"></i><span>Tickets</span></a>
                <a class="nav-item" href="it-tickets.php?scope=archive"><i class="fa-solid fa-box-archive"></i><span>Archives</span></a>
                <a class="nav-item<?= $isActive(['admin-statistics.php']) ?>" href="admin-statistics.php"><i class="fa-solid fa-chart-line"></i><span>Statistiques</span></a>
                <a class="nav-item<?= $isActive(['admin-exports.php']) ?>" href="admin-exports.php"><i class="fa-solid fa-file-export"></i><span>Extractions</span></a>
                <a class="nav-item<?= $isActive(['admin-audit.php']) ?>" href="admin-audit.php"><i class="fa-solid fa-shield-halved"></i><span>Audit</span></a>
                <a class="nav-item<?= $isActive(['admin-configuration.php']) ?>" href="admin-configuration.php"><i class="fa-solid fa-sliders"></i><span>Configuration</span></a>
            <?php elseif ($role === 'IT'): ?>
                <div class="nav-section-label">Support</div>
                <a class="nav-item<?= $isActive(['it-tickets.php','ticket.php']) ?>" href="it-tickets.php?scope=mine"><i class="fa-solid fa-inbox"></i><span>Mes tickets</span></a>
                <a class="nav-item" href="it-tickets.php?scope=unassigned"><i class="fa-solid fa-box-open"></i><span>Non attribués</span></a>
                <a class="nav-item" href="it-tickets.php?scope=team"><i class="fa-solid fa-user-group"></i><span>Équipe IT</span></a>
                <a class="nav-item" href="it-tickets.php?scope=archive"><i class="fa-solid fa-box-archive"></i><span>Archives</span></a>
            <?php elseif ($role === 'Manager'): ?>
                <div class="nav-section-label">Tickets</div>
                <a class="nav-item<?= $isActive(['manager-approvals.php']) ?>" href="manager-approvals.php"><i class="fa-solid fa-circle-check"></i><span>Validations</span></a>
                <a class="nav-item<?= $isActive(['my-tickets.php','ticket.php']) ?>" href="my-tickets.php"><i class="fa-solid fa-ticket-simple"></i><span>Mes tickets</span></a>
                <a class="nav-item<?= $isActive(['ticket-create.php']) ?>" href="ticket-create.php"><i class="fa-solid fa-plus"></i><span>Nouveau ticket</span></a>
            <?php else: ?>
                <div class="nav-section-label">Tickets</div>
                <a class="nav-item<?= $isActive(['my-tickets.php','ticket.php']) ?>" href="my-tickets.php"><i class="fa-solid fa-ticket-simple"></i><span>Mes tickets</span></a>
                <a class="nav-item<?= $isActive(['ticket-create.php']) ?>" href="ticket-create.php"><i class="fa-solid fa-plus"></i><span>Nouveau ticket</span></a>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <a class="nav-item<?= $isActive(['settings.php','change-password.php']) ?>" href="settings.php"><i class="fa-solid fa-gear"></i><span>Paramètres</span></a>
            <div class="sidebar-profile">
                <div class="avatar avatar-sm">
                    <?php if (!empty($headerProfile['profile_photo'])): ?><img src="avatar.php?id=<?= (int) $user['id'] ?>" alt="Photo de profil">
                    <?php else: ?><i class="fa-solid fa-user"></i><?php endif; ?>
                </div>
                <div class="sidebar-profile-copy"><strong><?= htmlspecialchars($user['firstname'] . ' ' . $user['lastname']) ?></strong><span>@<?= htmlspecialchars((string) $headerProfile['username']) ?></span></div>
                <form method="post" action="logout.php" class="logout-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><button type="submit" aria-label="Déconnexion" title="Déconnexion"><i class="fa-solid fa-arrow-right-from-bracket"></i></button></form>
            </div>
        </div>
    </aside>

    <div class="sidebar-overlay" data-sidebar-overlay></div>

    <div class="app-main">
        <header class="app-topbar app-topbar-v015">
            <div class="topbar-left-v013"><button class="mobile-menu-button" type="button" data-sidebar-open aria-label="Ouvrir le menu"><i class="fa-solid fa-bars"></i></button><div class="topbar-page-copy"><span><?= htmlspecialchars($role) ?></span><strong><?= htmlspecialchars($pageTitle ?? 'Dashboard') ?></strong></div></div>

            <div class="global-search-v015" data-global-search>
                <button class="global-search-mobile-toggle-v015" type="button" data-global-search-toggle aria-label="Ouvrir la recherche"><i class="fa-solid fa-magnifying-glass"></i></button>
                <div class="global-search-box-v015">
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input type="search" id="global-search-input" autocomplete="off" placeholder="Rechercher un ticket, utilisateur, groupe…" aria-label="Recherche globale TicketFlow">
                    <kbd>Ctrl K</kbd>
                </div>
                <div class="global-search-results-v015" id="global-search-results" hidden></div>
            </div>

            <div class="topbar-actions-v013">
                <a class="icon-button notification-button" href="notifications.php" aria-label="Notifications"><i class="fa-regular fa-bell"></i><?php if ($headerUnreadNotifications > 0): ?><span id="live-notification-count" class="notification-count"><?= $headerUnreadNotifications > 99 ? '99+' : (int) $headerUnreadNotifications ?></span><?php else: ?><span id="live-notification-count" class="notification-count is-hidden">0</span><?php endif; ?></a>
            </div>
        </header>

        <main class="dashboard-container app-content">
