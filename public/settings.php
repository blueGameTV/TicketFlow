<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireLogin();
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = t('settings.title');
$errors = [];
$success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

$prefStmt = $pdo->prepare('SELECT theme, density, sidebar_mode, language FROM user_preferences WHERE user_id = :uid');
$prefStmt->execute(['uid' => $user['id']]);
$preferences = $prefStmt->fetch() ?: ['theme' => 'light', 'density' => 'comfortable', 'sidebar_mode' => 'expanded', 'language' => $translator->locale()];

$profileStmt = $pdo->prepare('SELECT u.username, u.email, u.profile_photo, u.active, u.arrival_date, u.last_login_at, r.name AS role_name, g.name AS group_name FROM users u INNER JOIN roles r ON r.id=u.role_id LEFT JOIN groups_company g ON g.id=u.group_id WHERE u.id = :uid');
$profileStmt->execute(['uid' => $user['id']]);
$profile = $profileStmt->fetch() ?: ['username' => $user['username'] ?? '', 'email' => $user['email'] ?? '', 'profile_photo' => null, 'active'=>1, 'arrival_date'=>null, 'last_login_at'=>null, 'role_name'=>$user['role'] ?? '', 'group_name'=>null];
$profileRoleLabel = ($profile['role_name'] ?? '') === 'IT' ? 'Support IT' : (string)($profile['role_name'] ?? '');

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
            $language = (string) ($_POST['language'] ?? 'fr');
            if (!in_array($theme, ['light', 'dark', 'system'], true)) $theme = 'light';
            if (!in_array($density, ['comfortable', 'compact'], true)) $density = 'comfortable';
            if (!in_array($sidebarMode, ['expanded', 'compact'], true)) $sidebarMode = 'expanded';
            if (!in_array($language, ['fr', 'en'], true)) $language = 'fr';

            $stmt = $pdo->prepare('INSERT INTO user_preferences (user_id, theme, density, sidebar_mode, language) VALUES (:uid,:theme,:density,:sidebar,:language) ON DUPLICATE KEY UPDATE theme=VALUES(theme), density=VALUES(density), sidebar_mode=VALUES(sidebar_mode), language=VALUES(language)');
            $stmt->execute(['uid' => $user['id'], 'theme' => $theme, 'density' => $density, 'sidebar' => $sidebarMode, 'language' => $language]);
            $auditService->log((int) $user['id'], 'preferences_updated', 'user', (int) $user['id'], compact('theme', 'density', 'sidebarMode', 'language'));
            $_SESSION['flash_success'] = t('settings.saved');
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
            $_SESSION['flash_success'] = t('settings.email_save');
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
                            $_SESSION['flash_success'] = t('settings.photo_updated');
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
            $_SESSION['flash_success'] = t('settings.photo_removed');
            header('Location: settings.php');
            exit;
        }
    }
}

require __DIR__ . '/../templates/shared/header.php';
?>
<section class="page-heading page-heading-enhanced settings-heading-v0135">
    <div><span class="badge"><i class="fa-solid fa-gear"></i> <?= htmlspecialchars(t('settings.account')) ?></span><h1><?= htmlspecialchars(t('settings.title')) ?></h1><p><?= htmlspecialchars(t('settings.subtitle')) ?></p></div>
    <div class="queue-summary"><i class="fa-solid fa-user-gear"></i><div><strong>@<?= htmlspecialchars((string)$profile['username']) ?></strong><span><?= htmlspecialchars(t('role.'.($profile['role_name'] ?? ''))) ?></span></div></div>
</section>
<?php if ($success): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert error"><strong><?= htmlspecialchars(t('settings.error_title')) ?></strong><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="settings-overview-v0135">
    <section class="panel settings-profile-card-v0135">
        <div class="settings-profile-main-v0135">
            <div class="avatar avatar-xl"><?php if (!empty($profile['profile_photo'])): ?><img src="avatar.php?id=<?= (int)$user['id'] ?>" alt="<?= htmlspecialchars(t('settings.profile_photo')) ?>"><?php else: ?><i class="fa-solid fa-user"></i><?php endif; ?></div>
            <div><span class="settings-kicker"><?= htmlspecialchars(t('settings.profile')) ?></span><h2><?= htmlspecialchars($user['firstname'].' '.$user['lastname']) ?></h2><p>@<?= htmlspecialchars((string)$profile['username']) ?> · <?= htmlspecialchars((string)$profile['email']) ?></p></div>
        </div>
        <div class="settings-account-facts-v0135">
            <div><span><?= htmlspecialchars(t('settings.role')) ?></span><strong><?= htmlspecialchars(t('role.'.($profile['role_name'] ?? ''))) ?></strong></div>
            <div><span><?= htmlspecialchars(t('settings.group')) ?></span><strong><?= htmlspecialchars((string)($profile['group_name'] ?: t('common.unassigned'))) ?></strong></div>
            <div><span><?= htmlspecialchars(t('settings.account_status')) ?></span><strong><span class="status-pill <?= $profile['active'] ? 'active' : 'inactive' ?>"><?= htmlspecialchars($profile['active'] ? t('common.active') : t('common.inactive')) ?></span></strong></div>
            <div><span><?= htmlspecialchars(t('settings.last_login')) ?></span><strong><?= $profile['last_login_at'] ? htmlspecialchars(date('d/m/Y H:i', strtotime($profile['last_login_at']))) : htmlspecialchars(t('common.never')) ?></strong></div>
        </div>
    </section>
</div>

<div class="settings-grid settings-grid-v0135">
    <section class="panel settings-card settings-card-v0135">
        <div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-camera"></i></span><div><h2><?= htmlspecialchars(t('settings.profile_photo')) ?></h2><p><?= htmlspecialchars(t('settings.profile_photo_help')) ?></p></div></div></div>
        <form method="post" enctype="multipart/form-data" class="settings-form settings-form-v0135"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="avatar"><div class="field"><label for="avatar"><?= htmlspecialchars(t('settings.new_photo')) ?></label><input class="file-input" id="avatar" name="avatar" type="file" accept="image/jpeg,image/png,image/webp" required><small><?= htmlspecialchars(t('settings.photo_formats')) ?></small></div><button class="btn primary" type="submit"><i class="fa-solid fa-camera"></i> <?= htmlspecialchars(t('settings.update_photo')) ?></button></form>
        <?php if (!empty($profile['profile_photo'])): ?><form method="post" class="inline-danger-form"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="remove_avatar"><button class="btn ghost" type="submit"><i class="fa-regular fa-trash-can"></i> <?= htmlspecialchars(t('settings.remove_photo')) ?></button></form><?php endif; ?>
    </section>
    <section class="panel settings-card settings-card-v0135 settings-interface-card-v1206">
        <div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-sliders"></i></span><div><h2><?= htmlspecialchars(t('settings.interface')) ?></h2><p><?= htmlspecialchars(t('settings.interface_help')) ?></p></div></div></div>
        <form method="post" class="settings-form settings-form-v0135"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="preferences"><div class="field"><label for="theme"><i class="fa-solid fa-circle-half-stroke"></i> <?= htmlspecialchars(t('settings.theme')) ?></label><select id="theme" name="theme" data-theme-preference><option value="light" <?= $preferences['theme']==='light'?'selected':'' ?>><?= htmlspecialchars(t('settings.theme.light')) ?></option><option value="dark" <?= $preferences['theme']==='dark'?'selected':'' ?>><?= htmlspecialchars(t('settings.theme.dark')) ?></option><option value="system" <?= $preferences['theme']==='system'?'selected':'' ?>><?= htmlspecialchars(t('settings.theme.system')) ?></option></select><small class="theme-help-v120"><?= htmlspecialchars(t('settings.theme.help')) ?></small></div><div class="field"><label for="density"><i class="fa-solid fa-arrows-up-down"></i> <?= htmlspecialchars(t('settings.density')) ?></label><select id="density" name="density"><option value="comfortable" <?= $preferences['density']==='comfortable'?'selected':'' ?>><?= htmlspecialchars(t('settings.density.comfortable')) ?></option><option value="compact" <?= $preferences['density']==='compact'?'selected':'' ?>><?= htmlspecialchars(t('settings.density.compact')) ?></option></select></div><div class="field"><label for="sidebar_mode"><i class="fa-solid fa-bars"></i> <?= htmlspecialchars(t('settings.sidebar')) ?></label><select id="sidebar_mode" name="sidebar_mode"><option value="expanded" <?= $preferences['sidebar_mode']==='expanded'?'selected':'' ?>><?= htmlspecialchars(t('settings.sidebar.expanded')) ?></option><option value="compact" <?= $preferences['sidebar_mode']==='compact'?'selected':'' ?>><?= htmlspecialchars(t('settings.sidebar.compact')) ?></option></select></div><div class="field settings-language-field-v1206"><label for="language"><i class="fa-solid fa-language"></i> <?= htmlspecialchars(t('settings.language')) ?> <span class="v1206-beta-badge">BETA</span></label><select id="language" name="language"><option value="fr" <?= ($preferences['language']??'fr')==='fr'?'selected':'' ?>><?= htmlspecialchars(t('settings.language.fr')) ?></option><option value="en" <?= ($preferences['language']??'fr')==='en'?'selected':'' ?>><?= htmlspecialchars(t('settings.language.en')) ?> (Beta)</option></select><small class="theme-help-v120"><?= htmlspecialchars(t('settings.language.help')) ?></small></div><button class="btn primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> <?= htmlspecialchars(t('settings.save')) ?></button></form>
    </section>
    <section class="panel settings-card settings-card-v0135 settings-email-card-v014 settings-notification-card-v1206">
        <div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-envelope"></i></span><div><h2><?= htmlspecialchars(t('settings.email_title')) ?></h2><p><?= htmlspecialchars(t('settings.email_help')) ?></p></div></div></div>
        <form method="post" class="settings-form settings-form-v0135 settings-email-form-v014">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
            <input type="hidden" name="action" value="email_preferences">
            <label class="switch-row"><input type="checkbox" name="email_enabled" value="1" <?= !empty($emailPreferences['email_enabled']) ? 'checked' : '' ?>><span class="switch-copy"><strong><?= htmlspecialchars(t('settings.email_enable')) ?></strong><small><?= htmlspecialchars(t('settings.email_enable_help')) ?></small></span></label>
            <div class="notification-pref-grid-v014">
                <label><input type="checkbox" name="email_messages" value="1" <?= !empty($emailPreferences['email_messages']) ? 'checked' : '' ?>><span><i class="fa-solid fa-comments"></i><strong><?= htmlspecialchars(t('settings.email_messages')) ?></strong></span></label>
                <label><input type="checkbox" name="email_ticket_updates" value="1" <?= !empty($emailPreferences['email_ticket_updates']) ? 'checked' : '' ?>><span><i class="fa-solid fa-ticket"></i><strong><?= htmlspecialchars(t('settings.email_ticket_updates')) ?></strong></span></label>
                <label><input type="checkbox" name="email_validations" value="1" <?= !empty($emailPreferences['email_validations']) ? 'checked' : '' ?>><span><i class="fa-solid fa-user-check"></i><strong><?= htmlspecialchars(t('settings.email_validations')) ?></strong></span></label>
                <label><input type="checkbox" name="email_resolution" value="1" <?= !empty($emailPreferences['email_resolution']) ? 'checked' : '' ?>><span><i class="fa-solid fa-circle-check"></i><strong><?= htmlspecialchars(t('settings.email_resolution')) ?></strong></span></label>
                <label><input type="checkbox" name="email_sla" value="1" <?= !empty($emailPreferences['email_sla']) ? 'checked' : '' ?>><span><i class="fa-solid fa-clock"></i><strong><?= htmlspecialchars(t('settings.email_sla')) ?></strong></span></label>
                <label><input type="checkbox" name="email_daily_digest" value="1" <?= !empty($emailPreferences['email_daily_digest']) ? 'checked' : '' ?>><span><i class="fa-solid fa-calendar-day"></i><strong><?= htmlspecialchars(t('settings.email_digest')) ?></strong></span></label>
            </div>
            <button class="btn primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> <?= htmlspecialchars(t('settings.email_save')) ?></button>
        </form>
    </section>
    <section class="panel settings-card settings-security-card settings-security-card-v0135">
        <div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-shield-halved"></i></span><div><h2><?= htmlspecialchars(t('settings.security')) ?></h2><p><?= htmlspecialchars(t('settings.security_help')) ?></p></div></div></div>
        <div class="settings-links settings-links-v0135"><a href="change-password.php"><span class="settings-link-icon"><i class="fa-solid fa-key"></i></span><span><strong><?= htmlspecialchars(t('settings.change_password')) ?></strong><small><?= htmlspecialchars(t('settings.change_password_help')) ?></small></span><i class="fa-solid fa-chevron-right"></i></a><a href="notifications.php"><span class="settings-link-icon"><i class="fa-solid fa-bell"></i></span><span><strong><?= htmlspecialchars(t('settings.notification_center')) ?></strong><small><?= htmlspecialchars(t('settings.notification_center_help')) ?></small></span><i class="fa-solid fa-chevron-right"></i></a></div>
        <div class="settings-security-note-v0135"><i class="fa-solid fa-circle-info"></i><span><?= htmlspecialchars(t('settings.session_note')) ?></span></div>
    </section>
</div>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
