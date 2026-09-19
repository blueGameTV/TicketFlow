<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireLogin();
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'Paramètres';
$errors = [];
$success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

$prefStmt = $pdo->prepare('SELECT theme, density, sidebar_mode FROM user_preferences WHERE user_id = :uid');
$prefStmt->execute(['uid' => $user['id']]);
$preferences = $prefStmt->fetch() ?: ['theme' => 'light', 'density' => 'comfortable', 'sidebar_mode' => 'expanded'];

$profileStmt = $pdo->prepare('SELECT u.username, u.email, u.profile_photo, u.active, u.arrival_date, u.last_login_at, r.name AS role_name, g.name AS group_name FROM users u INNER JOIN roles r ON r.id=u.role_id LEFT JOIN groups_company g ON g.id=u.group_id WHERE u.id = :uid');
$profileStmt->execute(['uid' => $user['id']]);
$profile = $profileStmt->fetch() ?: ['username' => $user['username'] ?? '', 'email' => $user['email'] ?? '', 'profile_photo' => null, 'active'=>1, 'arrival_date'=>null, 'last_login_at'=>null, 'role_name'=>$user['role'] ?? '', 'group_name'=>null];

$emailPreferences = $notificationPreferenceService->get((int) $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La session du formulaire a expiré. Rechargez la page.';
    } else {
        $action = (string) ($_POST['action'] ?? 'preferences');

        if ($action === 'preferences') {
            $theme = (string) ($_POST['theme'] ?? 'light');
            $density = (string) ($_POST['density'] ?? 'comfortable');
            $sidebarMode = (string) ($_POST['sidebar_mode'] ?? 'expanded');
            if (!in_array($theme, ['light', 'dark', 'system'], true)) $theme = 'light';
            if (!in_array($density, ['comfortable', 'compact'], true)) $density = 'comfortable';
            if (!in_array($sidebarMode, ['expanded', 'compact'], true)) $sidebarMode = 'expanded';

            $stmt = $pdo->prepare('INSERT INTO user_preferences (user_id, theme, density, sidebar_mode) VALUES (:uid,:theme,:density,:sidebar) ON DUPLICATE KEY UPDATE theme=VALUES(theme), density=VALUES(density), sidebar_mode=VALUES(sidebar_mode)');
            $stmt->execute(['uid' => $user['id'], 'theme' => $theme, 'density' => $density, 'sidebar' => $sidebarMode]);
            $auditService->log((int) $user['id'], 'preferences_updated', 'user', (int) $user['id'], compact('theme', 'density', 'sidebarMode'));
            $_SESSION['flash_success'] = 'Préférences enregistrées.';
            header('Location: settings.php');
            exit;
        }

        if ($action === 'email_preferences') {
            $notificationPreferenceService->save((int) $user['id'], [
                'email_enabled' => !empty($_POST['email_enabled']),
                'email_messages' => !empty($_POST['email_messages']),
                'email_ticket_updates' => !empty($_POST['email_ticket_updates']),
                'email_validations' => !empty($_POST['email_validations']),
                'email_resolution' => !empty($_POST['email_resolution']),
                'email_sla' => !empty($_POST['email_sla']),
                'email_daily_digest' => !empty($_POST['email_daily_digest']),
            ]);
            $auditService->log((int) $user['id'], 'email_preferences_updated', 'user', (int) $user['id']);
            $_SESSION['flash_success'] = 'Préférences de notifications e-mail enregistrées.';
            header('Location: settings.php');
            exit;
        }

        if ($action === 'avatar') {
            $file = $_FILES['avatar'] ?? null;
            if (!$file || !is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                $errors[] = 'Sélectionnez une image.';
            } elseif (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
                $errors[] = 'Le transfert de la photo a échoué.';
            } elseif ((int) ($file['size'] ?? 0) > 3 * 1024 * 1024) {
                $errors[] = 'La photo de profil ne doit pas dépasser 3 Mo.';
            } else {
                $tmp = (string) ($file['tmp_name'] ?? '');
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
                $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
                if (!isset($allowed[$mime])) {
                    $errors[] = 'Format non autorisé. Utilisez JPG, PNG ou WEBP.';
                } else {
                    $avatarDir = __DIR__ . '/../storage/uploads/avatars';
                    if (!is_dir($avatarDir) && !mkdir($avatarDir, 0770, true) && !is_dir($avatarDir)) {
                        $errors[] = 'Impossible de préparer le dossier des avatars.';
                    } else {
                        $filename = 'u' . (int) $user['id'] . '_' . bin2hex(random_bytes(12)) . '.' . $allowed[$mime];
                        $destination = $avatarDir . '/' . $filename;
                        if (!move_uploaded_file($tmp, $destination)) {
                            $errors[] = 'Impossible d’enregistrer la photo de profil.';
                        } else {
                            if (!empty($profile['profile_photo'])) {
                                $old = $avatarDir . '/' . basename((string) $profile['profile_photo']);
                                if (is_file($old)) @unlink($old);
                            }
                            $pdo->prepare('UPDATE users SET profile_photo = :photo WHERE id = :uid')->execute(['photo' => $filename, 'uid' => $user['id']]);
                            $auditService->log((int) $user['id'], 'profile_photo_updated', 'user', (int) $user['id']);
                            $auth->refreshUser();
                            $_SESSION['flash_success'] = 'Photo de profil mise à jour.';
                            header('Location: settings.php');
                            exit;
                        }
                    }
                }
            }
        }

        if ($action === 'remove_avatar') {
            if (!empty($profile['profile_photo'])) {
                $old = __DIR__ . '/../storage/uploads/avatars/' . basename((string) $profile['profile_photo']);
                if (is_file($old)) @unlink($old);
            }
            $pdo->prepare('UPDATE users SET profile_photo = NULL WHERE id = :uid')->execute(['uid' => $user['id']]);
            $auth->refreshUser();
            $_SESSION['flash_success'] = 'Photo de profil supprimée. L’avatar par défaut est utilisé.';
            header('Location: settings.php');
            exit;
        }
    }
}

require __DIR__ . '/../templates/shared/header.php';
?>
<section class="page-heading page-heading-enhanced settings-heading-v0135">
    <div><span class="badge"><i class="fa-solid fa-gear"></i> Compte</span><h1>Paramètres</h1><p>Personnalisez votre profil, votre interface et les options de sécurité de votre compte.</p></div>
    <div class="queue-summary"><i class="fa-solid fa-user-gear"></i><div><strong>@<?= htmlspecialchars((string)$profile['username']) ?></strong><span><?= htmlspecialchars((string)$profile['role_name']) ?></span></div></div>
</section>
<?php if ($success): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert error"><strong>Une correction est nécessaire :</strong><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="settings-overview-v0135">
    <section class="panel settings-profile-card-v0135">
        <div class="settings-profile-main-v0135">
            <div class="avatar avatar-xl"><?php if (!empty($profile['profile_photo'])): ?><img src="avatar.php?id=<?= (int)$user['id'] ?>" alt="Photo de profil"><?php else: ?><i class="fa-solid fa-user"></i><?php endif; ?></div>
            <div><span class="settings-kicker">Profil TicketFlow</span><h2><?= htmlspecialchars($user['firstname'].' '.$user['lastname']) ?></h2><p>@<?= htmlspecialchars((string)$profile['username']) ?> · <?= htmlspecialchars((string)$profile['email']) ?></p></div>
        </div>
        <div class="settings-account-facts-v0135">
            <div><span>Rôle</span><strong><?= htmlspecialchars((string)$profile['role_name']) ?></strong></div>
            <div><span>Groupe</span><strong><?= htmlspecialchars((string)($profile['group_name'] ?: 'Non attribué')) ?></strong></div>
            <div><span>Compte</span><strong><span class="status-pill <?= $profile['active'] ? 'active' : 'inactive' ?>"><?= $profile['active'] ? 'Actif' : 'Désactivé' ?></span></strong></div>
            <div><span>Dernière connexion</span><strong><?= $profile['last_login_at'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($profile['last_login_at']))) : 'Jamais' ?></strong></div>
        </div>
    </section>
</div>

<div class="settings-grid settings-grid-v0135">
    <section class="panel settings-card settings-card-v0135">
        <div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-camera"></i></span><div><h2>Photo de profil</h2><p>Personnalisez l’avatar visible dans TicketFlow.</p></div></div></div>
        <form method="post" enctype="multipart/form-data" class="settings-form settings-form-v0135"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="avatar"><div class="field"><label for="avatar">Nouvelle photo</label><input class="file-input" id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" required><small>JPG, PNG ou WEBP · 3 Mo maximum.</small></div><button class="btn primary" type="submit"><i class="fa-solid fa-camera"></i> Mettre à jour la photo</button></form>
        <?php if (!empty($profile['profile_photo'])): ?><form method="post" class="inline-danger-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="remove_avatar"><button class="btn ghost" type="submit"><i class="fa-regular fa-trash-can"></i> Revenir à l’avatar par défaut</button></form><?php endif; ?>
    </section>
    <section class="panel settings-card settings-card-v0135">
        <div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-sliders"></i></span><div><h2>Interface</h2><p>Adaptez TicketFlow à votre façon de travailler.</p></div></div></div>
        <form method="post" class="settings-form settings-form-v0135"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="preferences"><div class="field"><label for="theme"><i class="fa-solid fa-circle-half-stroke"></i> Thème</label><select id="theme" name="theme"><option value="light" <?= $preferences['theme']==='light'?'selected':'' ?>>Clair</option><option value="dark" <?= $preferences['theme']==='dark'?'selected':'' ?>>Sombre</option><option value="system" <?= $preferences['theme']==='system'?'selected':'' ?>>Système</option></select></div><div class="field"><label for="density"><i class="fa-solid fa-arrows-up-down"></i> Densité</label><select id="density" name="density"><option value="comfortable" <?= $preferences['density']==='comfortable'?'selected':'' ?>>Confortable</option><option value="compact" <?= $preferences['density']==='compact'?'selected':'' ?>>Compacte</option></select></div><div class="field"><label for="sidebar_mode"><i class="fa-solid fa-bars"></i> Menu latéral</label><select id="sidebar_mode" name="sidebar_mode"><option value="expanded" <?= $preferences['sidebar_mode']==='expanded'?'selected':'' ?>>Développé</option><option value="compact" <?= $preferences['sidebar_mode']==='compact'?'selected':'' ?>>Compact</option></select></div><button class="btn primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Enregistrer les préférences</button></form>
    </section>
    <section class="panel settings-card settings-card-v0135 settings-email-card-v014">
        <div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-envelope"></i></span><div><h2>Notifications e-mail</h2><p>Choisissez les événements que TicketFlow peut vous envoyer par e-mail.</p></div></div></div>
        <form method="post" class="settings-form settings-form-v0135 settings-email-form-v014">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
            <input type="hidden" name="action" value="email_preferences">
            <label class="switch-row"><input type="checkbox" name="email_enabled" value="1" <?= !empty($emailPreferences['email_enabled']) ? 'checked' : '' ?>><span class="switch-copy"><strong>Autoriser les e-mails TicketFlow</strong><small>Coupe ou réactive tous les e-mails de votre compte.</small></span></label>
            <div class="notification-pref-grid-v014">
                <label><input type="checkbox" name="email_messages" value="1" <?= !empty($emailPreferences['email_messages']) ? 'checked' : '' ?>><span><i class="fa-solid fa-comments"></i><strong>Nouveaux messages</strong></span></label>
                <label><input type="checkbox" name="email_ticket_updates" value="1" <?= !empty($emailPreferences['email_ticket_updates']) ? 'checked' : '' ?>><span><i class="fa-solid fa-ticket"></i><strong>Évolution des tickets</strong></span></label>
                <label><input type="checkbox" name="email_validations" value="1" <?= !empty($emailPreferences['email_validations']) ? 'checked' : '' ?>><span><i class="fa-solid fa-user-check"></i><strong>Validations Manager</strong></span></label>
                <label><input type="checkbox" name="email_resolution" value="1" <?= !empty($emailPreferences['email_resolution']) ? 'checked' : '' ?>><span><i class="fa-solid fa-circle-check"></i><strong>Résolution</strong></span></label>
                <label><input type="checkbox" name="email_sla" value="1" <?= !empty($emailPreferences['email_sla']) ? 'checked' : '' ?>><span><i class="fa-solid fa-clock"></i><strong>Alertes SLA</strong></span></label>
                <label><input type="checkbox" name="email_daily_digest" value="1" <?= !empty($emailPreferences['email_daily_digest']) ? 'checked' : '' ?>><span><i class="fa-solid fa-calendar-day"></i><strong>Résumé quotidien</strong></span></label>
            </div>
            <button class="btn primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> Enregistrer les notifications</button>
        </form>
    </section>
    <section class="panel settings-card settings-security-card settings-security-card-v0135">
        <div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-shield-halved"></i></span><div><h2>Sécurité</h2><p>Protégez votre compte et contrôlez vos accès.</p></div></div></div>
        <div class="settings-links settings-links-v0135"><a href="change-password.php"><span class="settings-link-icon"><i class="fa-solid fa-key"></i></span><span><strong>Changer le mot de passe</strong><small>Renouveler votre mot de passe TicketFlow.</small></span><i class="fa-solid fa-chevron-right"></i></a><a href="notifications.php"><span class="settings-link-icon"><i class="fa-solid fa-bell"></i></span><span><strong>Centre de notifications</strong><small>Consulter et gérer vos alertes TicketFlow.</small></span><i class="fa-solid fa-chevron-right"></i></a></div>
        <div class="settings-security-note-v0135"><i class="fa-solid fa-circle-info"></i><span>Votre session expire automatiquement après une période d’inactivité pour limiter les accès non autorisés.</span></div>
    </section>
</div>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
