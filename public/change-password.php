<?php

declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$auth->requireLogin();
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'Changer mon mot de passe';
$errors = [];
$success = null;
$required = !empty($_GET['required']) || !empty($user['must_change_password']);

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
<section class="page-heading"><div><span class="badge">Sécurité</span><h1>Changer mon mot de passe</h1><p>12 caractères minimum, avec majuscule, minuscule et chiffre.</p></div></section>
<?php if ($required): ?><div class="alert warning">Vous devez définir un nouveau mot de passe avant de continuer.</div><?php endif; ?>
<?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert error"><ul><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<section class="panel form-panel"><form method="post" class="form-grid" autocomplete="off">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
<div class="field span-2"><label>Mot de passe actuel</label><input type="password" name="current_password" required autocomplete="current-password"></div>
<div class="field"><label>Nouveau mot de passe</label><input type="password" name="new_password" minlength="12" required autocomplete="new-password"></div>
<div class="field"><label>Confirmation</label><input type="password" name="confirm_password" minlength="12" required autocomplete="new-password"></div>
<div class="form-actions span-2"><button class="btn primary" type="submit">Modifier le mot de passe</button><?php if(!$required): ?><a class="btn ghost" href="dashboard.php">Retour</a><?php endif; ?></div>
</form></section>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
