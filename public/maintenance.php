<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$logged = $auth->check();
$role = $logged ? $auth->role() : null;

if (!$maintenanceService->accessBlocked()) {
    header('Location: ' . ($logged ? 'dashboard.php' : 'login.php'));
    exit;
}

if ($maintenanceService->roleAllowed($role)) {
    header('Location: dashboard.php');
    exit;
}

$state = $maintenanceService->publicMessage();
$endTimestamp = !empty($state['ends_at']) ? strtotime((string)$state['ends_at']) : false;
$lang = function_exists('current_language') ? current_language() : 'fr';
$isEnglish = $lang === 'en';

$title = (string)($state['title'] ?? '');
$message = (string)($state['message'] ?? '');

$labels = $isEnglish ? [
    'page_title' => 'Maintenance - TicketFlow',
    'status' => 'Maintenance in progress',
    'overline' => 'Technical intervention',
    'expected_end' => 'Expected end',
    'unknown_end' => 'To be confirmed',
    'check' => 'Automatic check',
    'check_value' => 'Every 10 seconds',
    'session' => 'Your session is preserved. Access will return automatically when the intervention ends.',
    'logout' => 'Sign out',
    'login' => 'Administrator / IT Support login',
    'footnote' => 'Thank you for your patience. The IT Support team is working to restore the service.',
] : [
    'page_title' => 'Maintenance - TicketFlow',
    'status' => 'Maintenance en cours',
    'overline' => 'Intervention technique',
    'expected_end' => 'Fin prévue',
    'unknown_end' => 'À confirmer',
    'check' => 'Vérification automatique',
    'check_value' => 'Toutes les 10 secondes',
    'session' => 'Votre session est conservée. L’accès reviendra automatiquement dès la fin de l’intervention.',
    'logout' => 'Se déconnecter',
    'login' => 'Connexion Administrateur / Support IT',
    'footnote' => 'Merci de votre patience. L’équipe Support IT travaille au rétablissement du service.',
];

if ($isEnglish) {
    if ($title === 'TicketFlow est en maintenance') $title = 'TicketFlow is under maintenance';
    if ($message === 'TicketFlow est actuellement en maintenance.') $message = 'TicketFlow is currently under maintenance.';
}
?>
<!doctype html>
<html lang="<?= htmlspecialchars($isEnglish ? 'en' : 'fr') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light dark">
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="refresh" content="10">
    <title><?= htmlspecialchars($labels['page_title']) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css?v=1.3.0-dev">
</head>
<body class="tf-maintenance-page">
<main class="tf-maintenance-shell">
    <section class="tf-maintenance-card" aria-labelledby="maintenance-title">
        <div class="tf-maintenance-accent" aria-hidden="true"></div>
        <header class="tf-maintenance-topbar">
            <div class="tf-maintenance-brand"><span class="tf-maintenance-brand-icon" aria-hidden="true"><i class="fa-solid fa-ticket"></i></span><span>TicketFlow</span></div>
            <span class="tf-maintenance-status"><span class="tf-maintenance-status-dot" aria-hidden="true"></span><?= htmlspecialchars($labels['status']) ?></span>
        </header>
        <div class="tf-maintenance-hero">
            <div class="tf-maintenance-main-icon" aria-hidden="true"><i class="fa-solid fa-screwdriver-wrench"></i></div>
            <div class="tf-maintenance-copy"><span class="tf-maintenance-overline"><?= htmlspecialchars($labels['overline']) ?></span><h1 id="maintenance-title"><?= htmlspecialchars($title) ?></h1><p class="tf-maintenance-message"><?= nl2br(htmlspecialchars($message)) ?></p></div>
        </div>
        <div class="tf-maintenance-info-grid">
            <article class="tf-maintenance-info"><span class="tf-maintenance-info-icon" aria-hidden="true"><i class="fa-regular fa-clock"></i></span><div><span><?= htmlspecialchars($labels['expected_end']) ?></span><strong><?= $endTimestamp !== false ? htmlspecialchars(date($isEnglish ? 'd/m/Y H:i' : 'd/m/Y à H:i', $endTimestamp)) : htmlspecialchars($labels['unknown_end']) ?></strong></div></article>
            <article class="tf-maintenance-info"><span class="tf-maintenance-info-icon" aria-hidden="true"><i class="fa-solid fa-rotate"></i></span><div><span><?= htmlspecialchars($labels['check']) ?></span><strong><?= htmlspecialchars($labels['check_value']) ?></strong></div></article>
        </div>
        <div class="tf-maintenance-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i><span><?= htmlspecialchars($labels['session']) ?></span></div>
        <div class="tf-maintenance-actions">
            <?php if ($logged): ?>
                <form method="post" action="logout.php"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><button class="tf-maintenance-button secondary" type="submit"><i class="fa-solid fa-arrow-right-from-bracket"></i><?= htmlspecialchars($labels['logout']) ?></button></form>
            <?php else: ?>
                <a class="tf-maintenance-button primary" href="login.php"><i class="fa-solid fa-lock"></i><?= htmlspecialchars($labels['login']) ?></a>
            <?php endif; ?>
        </div>
    </section>
    <p class="tf-maintenance-footnote"><i class="fa-solid fa-headset" aria-hidden="true"></i><?= htmlspecialchars($labels['footnote']) ?></p>
</main>
</body>
</html>
