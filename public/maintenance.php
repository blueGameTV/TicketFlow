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
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="color-scheme" content="light">
    <meta http-equiv="refresh" content="10">
    <title>Maintenance - TicketFlow</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/v110.css?v=1.1.0">
</head>
<body class="v110-maintenance-page">
    <main class="v110-maintenance-shell">
        <section class="v110-maintenance-card" aria-labelledby="maintenance-title">
            <div class="v110-maintenance-accent"></div>
            <div class="v110-maintenance-topline">
                <div class="v110-maintenance-brand"><span><i class="fa-solid fa-ticket"></i></span> TicketFlow</div>
                <span class="v110-maintenance-status"><i class="fa-solid fa-circle"></i> Maintenance en cours</span>
            </div>
            <div class="v110-maintenance-icon"><i class="fa-solid fa-screwdriver-wrench"></i></div>
            <span class="v110-maintenance-overline">Intervention technique</span>
            <h1 id="maintenance-title"><?= htmlspecialchars((string)$state['title']) ?></h1>
            <p class="v110-maintenance-message"><?= nl2br(htmlspecialchars((string)$state['message'])) ?></p>
            <div class="v110-maintenance-status-grid">
                <div class="v110-maintenance-timebox"><i class="fa-regular fa-clock"></i><div><span>Fin prévue</span><strong><?= $endTimestamp !== false ? htmlspecialchars(date('d/m/Y à H:i', $endTimestamp)) : 'À confirmer' ?></strong></div></div>
                <div class="v110-maintenance-timebox"><i class="fa-solid fa-rotate"></i><div><span>Vérification</span><strong>Toutes les 10 secondes</strong></div></div>
            </div>
            <div class="v110-maintenance-note"><i class="fa-solid fa-circle-info"></i><span>Votre session est conservée. L'accès reviendra automatiquement dès la fin de l'intervention.</span></div>
            <?php if ($logged): ?>
                <form method="post" action="logout.php" class="v110-maintenance-logout">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
                    <button type="submit"><i class="fa-solid fa-arrow-right-from-bracket"></i> Se déconnecter</button>
                </form>
            <?php else: ?>
                <a class="v110-maintenance-login" href="login.php"><i class="fa-solid fa-lock"></i> Connexion Administrateur / IT</a>
            <?php endif; ?>
        </section>
        <p class="v110-maintenance-footnote">Merci de votre patience. L'équipe IT travaille au rétablissement du service.</p>
    </main>
</body>
</html>
