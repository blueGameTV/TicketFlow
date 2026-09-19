<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if ($auth->check()) {
    header('Location: dashboard.php');
    exit;
}

$error = null;
$identifier = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = mb_strtolower(trim((string) ($_POST['identifier'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $token = $_POST['csrf_token'] ?? null;

    if (!$csrf->validate(is_string($token) ? $token : null)) {
        $error = 'La session du formulaire a expiré. Rechargez la page.';
    } elseif ($identifier === '' || $password === '') {
        $error = 'Identifiant/e-mail et mot de passe requis.';
    } elseif (!$auth->attempt($identifier, $password)) {
        usleep(250000);
        $error = $auth->lastError() ?? 'Identifiant/e-mail ou mot de passe incorrect.';
    } else {
        $u = $auth->user();
        if (!empty($u['must_change_password'])) {
            header('Location: change-password.php?required=1');
        } else {
            header('Location: dashboard.php');
        }
        exit;
    }
}

$appName = $config['app']['name'] ?? 'TicketFlow';
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Connexion - <?= htmlspecialchars($appName) ?></title>
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css?v=0.15.2.1">
    <link rel="stylesheet" href="assets/css/v013.css?v=0.15.2.1">
</head>
<body class="auth-page auth-page-v013">
<main class="auth-layout-v013">
    <section class="auth-visual-v013 auth-visual-v0136" aria-label="Présentation TicketFlow">
        <div class="auth-brand-v013">
            <div class="brand-mark"><i class="fa-solid fa-ticket"></i></div>
            <div>
                <span class="eyebrow">Plateforme de support</span>
                <h1><?= htmlspecialchars($appName) ?></h1>
            </div>
        </div>
        <div class="auth-copy-v013">
            <span class="auth-overline-v0136"><i class="fa-solid fa-network-wired"></i> Environnement IT professionnel</span>
            <h2>Vos demandes IT, au même endroit.</h2>
            <p>Centralisez les incidents, demandes d’accès et validations dans une interface moderne pensée pour les équipes support et les collaborateurs.</p>
            <div class="auth-points">
                <span><i class="fa-solid fa-bolt"></i> Suivi en direct</span>
                <span><i class="fa-solid fa-shield-halved"></i> Accès sécurisé</span>
                <span><i class="fa-solid fa-clock"></i> Suivi SLA</span>
            </div>
        </div>
        <div class="auth-scene-v0136" aria-hidden="true">
            <img src="assets/img/auth-office.svg" alt="">
        </div>
    </section>

    <section class="auth-panel-v013">
        <div class="auth-card-v013">
            <div class="auth-card-heading">
                <span class="mobile-brand"><i class="fa-solid fa-ticket"></i> <?= htmlspecialchars($appName) ?></span>
                <h2>Connexion</h2>
                <p>Connectez-vous avec votre identifiant TicketFlow ou votre adresse e-mail.</p>
            </div>

            <?php if ($error !== null): ?>
                <div class="alert error" role="alert"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="post" action="login.php" autocomplete="on" class="auth-form-v013">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">

                <div class="field-v013">
                    <label for="identifier">Identifiant ou e-mail</label>
                    <div class="input-icon">
                        <i class="fa-regular fa-user"></i>
                        <input id="identifier" name="identifier" type="text" maxlength="190" required autocomplete="username" value="<?= htmlspecialchars($identifier) ?>" placeholder="jdupont ou prenom.nom@entreprise.fr">
                    </div>
                </div>

                <div class="field-v013">
                    <div class="label-row">
                        <label for="password">Mot de passe</label>
                        <a href="forgot-password.php">Mot de passe oublié ?</a>
                    </div>
                    <div class="input-icon password-input-wrap">
                        <i class="fa-solid fa-lock"></i>
                        <input id="password" name="password" type="password" required autocomplete="current-password">
                        <button class="password-toggle" type="button" data-password-toggle="password" aria-label="Afficher le mot de passe"><i class="fa-regular fa-eye"></i></button>
                    </div>
                </div>

                <button class="btn primary full auth-submit" type="submit"><i class="fa-solid fa-arrow-right-to-bracket"></i> Se connecter</button>
            </form>
        </div>
    </section>
</main>
<script src="assets/js/ui.js?v=0.15.2.1" defer></script>
</body>
</html>
