<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$appName = $config['app']['name'] ?? 'TicketFlow';
$token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));
$error = null;
$success = null;
$validToken = false;
$resetRow = null;

if ($token !== '') {
    $stmt = $pdo->prepare('SELECT prt.id, prt.user_id FROM password_reset_tokens prt INNER JOIN users u ON u.id = prt.user_id WHERE prt.token_hash = :hash AND prt.used_at IS NULL AND prt.expires_at > NOW() AND u.active = 1 LIMIT 1');
    $stmt->execute(['hash' => hash('sha256', $token)]);
    $resetRow = $stmt->fetch();
    $validToken = (bool) $resetRow;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $validToken) {
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if (!$csrf->validate($_POST['csrf_token'] ?? null)) {
        $error = 'La session du formulaire a expiré.';
    } elseif (strlen($password) < 12) {
        $error = 'Le mot de passe doit contenir au moins 12 caractères.';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
        $error = 'Le mot de passe doit contenir au moins une majuscule, une minuscule et un chiffre.';
    } elseif ($password !== $confirm) {
        $error = 'Les deux mots de passe ne correspondent pas.';
    } else {
        $pdo->beginTransaction();
        try {
            $pdo->prepare('UPDATE users SET password_hash = :hash, password_changed_at = NOW(), must_change_password = 0, locked_until = NULL WHERE id = :uid')->execute([
                'hash' => password_hash($password, PASSWORD_DEFAULT),
                'uid' => $resetRow['user_id'],
            ]);
            $pdo->prepare('UPDATE password_reset_tokens SET used_at = NOW() WHERE user_id = :uid AND used_at IS NULL')->execute(['uid' => $resetRow['user_id']]);
            $pdo->prepare('UPDATE user_sessions SET revoked_at = NOW() WHERE user_id = :uid AND revoked_at IS NULL')->execute(['uid' => $resetRow['user_id']]);
            $pdo->commit();
            $auditService->log((int) $resetRow['user_id'], 'password_reset_completed', 'user', (int) $resetRow['user_id']);
            $success = 'Votre mot de passe a été modifié. Vous pouvez maintenant vous connecter.';
            $validToken = false;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $error = 'Impossible de modifier le mot de passe pour le moment.';
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Réinitialiser le mot de passe - <?= htmlspecialchars($appName) ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="assets/css/app.css?v=0.15.2.1"><link rel="stylesheet" href="assets/css/v013.css?v=0.15.2.1">
</head>
<body class="auth-page auth-page-v013">
<main class="auth-layout-v013 auth-layout-single"><section class="auth-panel-v013"><div class="auth-card-v013">
    <a class="auth-back" href="login.php"><i class="fa-solid fa-arrow-left"></i> Retour à la connexion</a>
    <div class="auth-card-heading"><span class="mobile-brand"><i class="fa-solid fa-ticket"></i> <?= htmlspecialchars($appName) ?></span><h2>Nouveau mot de passe</h2><p>Choisissez un nouveau mot de passe pour votre compte.</p></div>
    <?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><a class="btn primary full" href="login.php">Se connecter</a>
    <?php elseif (!$validToken): ?><div class="alert error">Ce lien est invalide ou a expiré.</div><a class="btn secondary full" href="forgot-password.php">Demander un nouveau lien</a>
    <?php else: ?>
    <form method="post" class="auth-form-v013">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">
        <div class="field-v013"><label for="password">Nouveau mot de passe</label><div class="input-icon password-input-wrap"><i class="fa-solid fa-lock"></i><input id="password" name="password" type="password" minlength="12" required><button class="password-toggle" type="button" data-password-toggle="password"><i class="fa-regular fa-eye"></i></button></div></div>
        <div class="field-v013"><label for="password_confirm">Confirmation</label><div class="input-icon password-input-wrap"><i class="fa-solid fa-lock"></i><input id="password_confirm" name="password_confirm" type="password" minlength="12" required><button class="password-toggle" type="button" data-password-toggle="password_confirm"><i class="fa-regular fa-eye"></i></button></div></div>
        <button class="btn primary full auth-submit" type="submit">Modifier le mot de passe</button>
    </form>
    <?php endif; ?>
</div></section></main>
<script src="assets/js/ui.js?v=0.15.2.1" defer></script>
</body></html>
