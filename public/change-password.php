<?php

declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$auth->requireLogin();
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'Changer mon mot de passe';
$errors = [];
$success = null;
if (!empty($user['must_change_password'])) {
    header('Location: dashboard.php?password_required=1');
    exit;
}
$required = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La session du formulaire a expiré.';
    } else {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE id=:id');
        $stmt->execute(['id'=>$user['id']]);
        $hash = (string)$stmt->fetchColumn();
        if (!password_verify($current, $hash)) $errors[]='Le mot de passe actuel est incorrect.';
        if (mb_strlen($new) < 12) $errors[]='Le nouveau mot de passe doit contenir au moins 12 caractères.';
        if (!preg_match('/[A-Z]/', $new) || !preg_match('/[a-z]/', $new) || !preg_match('/\d/', $new)) $errors[]='Le mot de passe doit contenir au moins une majuscule, une minuscule et un chiffre.';
        if ($new !== $confirm) $errors[]='Les deux nouveaux mots de passe ne correspondent pas.';
        if ($current !== '' && hash_equals($current, $new)) $errors[]='Le nouveau mot de passe doit être différent de l’ancien.';
        if (!$errors) {
            $pdo->prepare('UPDATE users SET password_hash=:hash,must_change_password=0,password_changed_at=NOW() WHERE id=:id')->execute(['hash'=>password_hash($new,PASSWORD_DEFAULT),'id'=>$user['id']]);
            $auditService->log((int)$user['id'],'password_changed','user',(int)$user['id']);
            $auth->refreshUser();
            $success='Mot de passe modifié avec succès.';
            $required=false;
        }
    }
}
require __DIR__ . '/../templates/shared/header.php';
?>
<section class="page-heading page-heading-enhanced password-heading-v1206">
    <div><span class="badge"><i class="fa-solid fa-shield-halved"></i> Sécurité</span><h1>Changer mon mot de passe</h1><p>Renforcez la sécurité de votre compte avec un mot de passe unique et robuste.</p></div>
    <div class="password-heading-security-v1206"><i class="fa-solid fa-lock"></i><span>12 caractères minimum</span></div>
</section>
<?php if ($required): ?><div class="alert warning">Vous devez définir un nouveau mot de passe avant de continuer.</div><?php endif; ?>
<?php if ($success): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert error"><ul><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="panel password-card-v1206">
    <div class="password-card-intro-v1206"><span class="panel-icon"><i class="fa-solid fa-key"></i></span><div><h2>Nouveau mot de passe</h2><p>Utilisez au minimum 12 caractères avec une majuscule, une minuscule et un chiffre.</p></div></div>
    <form method="post" class="form-grid password-form-v1206" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
        <div class="field span-2"><label for="current_password"><i class="fa-solid fa-lock-open"></i> Mot de passe actuel</label><input id="current_password" type="password" name="current_password" required autocomplete="current-password" placeholder="••••••••••••"></div>
        <div class="field"><label for="new_password"><i class="fa-solid fa-key"></i> Nouveau mot de passe</label><input id="new_password" type="password" name="new_password" minlength="12" required autocomplete="new-password" placeholder="12 caractères minimum"></div>
        <div class="field"><label for="confirm_password"><i class="fa-solid fa-check-double"></i> Confirmation</label><input id="confirm_password" type="password" name="confirm_password" minlength="12" required autocomplete="new-password" placeholder="Confirmez le nouveau mot de passe"></div>
        <div class="password-strength-v12061 span-2" data-password-strength>
            <div class="password-strength-head-v12061"><div><strong>Solidité du mot de passe</strong><small>La jauge évolue pendant la saisie.</small></div><span class="password-strength-label-v12061" data-strength-label>Non évalué</span></div>
            <div class="password-strength-track-v12061" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
        </div>
        <div class="password-rules-v1206 span-2" data-password-rules>
            <span data-rule="length"><i class="fa-regular fa-circle"></i> 12+ caractères</span>
            <span data-rule="upper"><i class="fa-regular fa-circle"></i> Majuscule</span>
            <span data-rule="lower"><i class="fa-regular fa-circle"></i> Minuscule</span>
            <span data-rule="digit"><i class="fa-regular fa-circle"></i> Chiffre</span>
            <span data-rule="match"><i class="fa-regular fa-circle"></i> Confirmation identique</span>
        </div>
        <div class="form-actions span-2"><button class="btn primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Modifier le mot de passe</button><?php if(!$required): ?><a class="btn ghost" href="dashboard.php"><i class="fa-solid fa-arrow-left"></i> Retour</a><?php endif; ?></div>
    </form>
</section>
<script src="assets/js/password-strength.js?v=1.2.0" defer></script>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
