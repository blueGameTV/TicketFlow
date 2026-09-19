<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

if ($auth->check()) {
    header('Location: dashboard.php');
    exit;
}

$appName = $config['app']['name'] ?? 'TicketFlow';
$error = null;
$success = null;
$identifier = '';
$developmentResetUrl = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = mb_strtolower(trim((string) ($_POST['identifier'] ?? '')));

    if (!$csrf->validate($_POST['csrf_token'] ?? null)) {
        $error = 'La session du formulaire a expiré. Rechargez la page.';
    } elseif ($identifier === '') {
        $error = 'Saisissez votre identifiant ou votre adresse e-mail.';
    } else {
        $stmt = $pdo->prepare('SELECT id, email FROM users WHERE active = 1 AND (LOWER(email) = :identifier_email OR LOWER(username) = :identifier_username) LIMIT 1');
        $stmt->execute(['identifier_email' => $identifier, 'identifier_username' => $identifier]);
        $account = $stmt->fetch();

        if ($account) {
            $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL')->execute(['uid' => $account['id']]);
            $rawToken = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $rawToken);
            $insert = $pdo->prepare('INSERT INTO password_reset_tokens (user_id, token_hash, expires_at) VALUES (:uid, :hash, DATE_ADD(NOW(), INTERVAL 30 MINUTE))');
            $insert->execute(['uid' => $account['id'], 'hash' => $tokenHash]);
            $auditService->log((int) $account['id'], 'password_reset_requested', 'user', (int) $account['id']);

            // L'envoi e-mail sera ajouté dans la v0.14. En environnement de développement,
            // le lien est affiché afin de permettre les tests complets dès la v0.13.
            if (($config['app']['environment'] ?? 'production') === 'development') {
                $developmentResetUrl = 'reset-password.php?token=' . urlencode($rawToken);
            }
        }

        $success = 'Si le compte existe, une demande de réinitialisation a été créée. Le lien expire après 30 minutes.';
        $identifier = '';
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mot de passe oublié - <?= htmlspecialchars($appName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css?v=0.15.2.1">
    <link rel="stylesheet" href="assets/css/v013.css?v=0.15.2.1">
</head>
<body class="auth-page auth-page-v013">
<main class="auth-layout-v013 auth-layout-single">
    <section class="auth-panel-v013">
        <div class="auth-card-v013">
            <a class="auth-back" href="login.php"><i class="fa-solid fa-arrow-left"></i> Retour à la connexion</a>
            <div class="auth-card-heading">
                <span class="mobile-brand"><i class="fa-solid fa-ticket"></i> <?= htmlspecialchars($appName) ?></span>
                <h2>Mot de passe oublié</h2>
                <p>Saisissez votre identifiant ou votre adresse e-mail.</p>
            </div>
            <?php if ($error): ?><div class="alert error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
            <?php if ($success): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
            <?php if ($developmentResetUrl): ?>
                <div class="dev-reset-box">
                    <strong>Mode développement</strong>
                    <p>L'envoi e-mail sera ajouté en v0.14. Pour tester maintenant :</p>
                    <a class="btn secondary full" href="<?= htmlspecialchars($developmentResetUrl) ?>">Ouvrir le lien de réinitialisation</a>
                </div>
            <?php endif; ?>
            <form method="post" class="auth-form-v013">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
                <div class="field-v013">
                    <label for="identifier">Identifiant ou e-mail</label>
                    <div class="input-icon"><i class="fa-regular fa-user"></i><input id="identifier" name="identifier" required value="<?= htmlspecialchars($identifier) ?>" placeholder="jdupont"></div>
                </div>
                <button class="btn primary full auth-submit" type="submit"><i class="fa-solid fa-paper-plane"></i> Demander la réinitialisation</button>
            </form>
        </div>
    </section>
</main>
</body>
</html>
