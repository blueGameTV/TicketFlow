<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireRole('Administrateur');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = t('nav.configuration');
$errors = [];
$success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La session du formulaire a expiré. Rechargez la page.';
    } else {
        $action = (string) ($_POST['action'] ?? 'save');
        if ($action === 'save') {
            $companyName = trim((string) ($_POST['company_name'] ?? ''));
            $supportEmail = trim((string) ($_POST['support_email'] ?? ''));
            $managerReminder = max(1, min(720, (int) ($_POST['manager_reminder_hours'] ?? 24)));
            $resolutionReminder = max(1, min(720, (int) ($_POST['resolution_reminder_hours'] ?? 24)));
            $autoCloseHours = max(24, min(2160, (int) ($_POST['auto_close_hours'] ?? 72)));
            $digestHour = max(0, min(23, (int) ($_POST['daily_digest_hour'] ?? 8)));
            $defaultLanguage = (string)($_POST['default_language'] ?? 'fr');
            if (!in_array($defaultLanguage, ['fr','en'], true)) $defaultLanguage = 'fr';

            if ($companyName === '' || mb_strlen($companyName) > 120) {
                $errors[] = 'Le nom de l’entreprise doit contenir entre 1 et 120 caractères.';
            }
            if ($supportEmail !== '' && !filter_var($supportEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'L’adresse e-mail du support n’est pas valide.';
            }

            if (!$errors) {
                $appSettingService->setMany([
                    'company_name' => $companyName,
                    'support_email' => $supportEmail,
                    'email_notifications_enabled' => !empty($_POST['email_notifications_enabled']) ? '1' : '0',
                    'manager_reminder_hours' => (string) $managerReminder,
                    'resolution_reminder_hours' => (string) $resolutionReminder,
                    'auto_close_enabled' => !empty($_POST['auto_close_enabled']) ? '1' : '0',
                    'auto_close_hours' => (string) $autoCloseHours,
                    'daily_digest_enabled' => !empty($_POST['daily_digest_enabled']) ? '1' : '0',
                    'daily_digest_hour' => (string) $digestHour,
                    'default_language' => $defaultLanguage,
                ]);
                $auditService->log((int) $user['id'], 'configuration_updated', 'application', null, [
                    'company_name' => $companyName,
                    'email_notifications_enabled' => !empty($_POST['email_notifications_enabled']),
                    'auto_close_enabled' => !empty($_POST['auto_close_enabled']),
                    'daily_digest_enabled' => !empty($_POST['daily_digest_enabled']),
                ]);
                $_SESSION['flash_success'] = 'Configuration TicketFlow enregistrée.';
                header('Location: admin-configuration.php');
                exit;
            }
        } elseif ($action === 'test_email') {
            $target = trim((string) ($_POST['test_email'] ?? $user['email'] ?? ''));
            if (!filter_var($target, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'Indiquez une adresse e-mail valide pour le test.';
            } else {
                try {
                    $mailQueueService->queueRaw(
                        (int) $user['id'],
                        $target,
                        trim($user['firstname'] . ' ' . $user['lastname']),
                        'smtp_test',
                        '[TicketFlow] Test de configuration e-mail',
                        '<!doctype html><html><body style="font-family:Arial,sans-serif"><h2>TicketFlow</h2><p>La configuration e-mail fonctionne correctement.</p><p>Test effectué le ' . date('d/m/Y H:i:s') . '.</p></body></html>'
                    );
                    $result = $mailQueueService->process(1);
                    if (!empty($result['disabled'])) {
                        $errors[] = 'Le transport e-mail est désactivé dans config/config.php.';
                    } elseif (($result['sent'] ?? 0) > 0) {
                        $_SESSION['flash_success'] = 'E-mail de test traité avec succès.';
                        header('Location: admin-configuration.php');
                        exit;
                    } else {
                        $errors[] = 'L’e-mail de test n’a pas pu être envoyé. Consultez la file e-mail et les logs.';
                    }
                } catch (Throwable $e) {
                    $errors[] = 'Test e-mail impossible : ' . $e->getMessage();
                }
            }
        }
    }
}

$settings = $appSettingService->all();
$mail = $config['mail'] ?? ['transport' => 'disabled'];
$mailStats = $mailQueueService->stats();
require __DIR__ . '/../templates/shared/header.php';
?>
<section class="page-heading page-heading-enhanced configuration-heading-v014">
    <div>
        <span class="badge"><i class="fa-solid fa-sliders"></i> <?= htmlspecialchars(t('common.administration')) ?></span>
        <h1><?= htmlspecialchars(t('config.title')) ?> <span class="v1206-beta-badge v1206-beta-badge-title">BETA</span></h1>
        <p><?= htmlspecialchars(t('config.subtitle')) ?></p>
    </div>
    <div class="queue-summary"><i class="fa-solid fa-envelope-circle-check"></i><div><strong><?= htmlspecialchars(strtoupper((string) ($mail['transport'] ?? 'disabled'))) ?></strong><span><?= htmlspecialchars(t('config.mail_transport')) ?></span></div></div>
</section>

<?php if ($success): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert error"><strong><?= htmlspecialchars(t('config.error_title')) ?></strong><ul><?php foreach ($errors as $error): ?><li><?= htmlspecialchars($error) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="configuration-grid-v014">
    <section class="panel configuration-card-v014 span-2-v014">
        <div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-building"></i></span><div><h2><?= htmlspecialchars(t('config.app_automation')) ?></h2><p><?= htmlspecialchars(t('config.app_automation_help')) ?></p></div></div></div>
        <form method="post" class="configuration-form-v014">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
            <input type="hidden" name="action" value="save">
            <div class="form-grid">
                <div class="field"><label for="company_name"><?= htmlspecialchars(t('config.company_name')) ?></label><input id="company_name" name="company_name" maxlength="120" required value="<?= htmlspecialchars((string) $settings['company_name']) ?>"></div>
                <div class="field"><label for="support_email"><?= htmlspecialchars(t('config.support_email')) ?></label><input id="support_email" name="support_email" type="email" maxlength="190" value="<?= htmlspecialchars((string) $settings['support_email']) ?>" placeholder="support@entreprise.fr"></div>
                <div class="field"><label for="manager_reminder_hours"><?= htmlspecialchars(t('config.manager_reminder')) ?></label><div class="input-with-unit-v014"><input id="manager_reminder_hours" name="manager_reminder_hours" type="number" min="1" max="720" value="<?= (int) $settings['manager_reminder_hours'] ?>"><span><?= htmlspecialchars(t('config.hours')) ?></span></div></div>
                <div class="field"><label for="resolution_reminder_hours"><?= htmlspecialchars(t('config.user_reminder')) ?></label><div class="input-with-unit-v014"><input id="resolution_reminder_hours" name="resolution_reminder_hours" type="number" min="1" max="720" value="<?= (int) $settings['resolution_reminder_hours'] ?>"><span><?= htmlspecialchars(t('config.hours')) ?></span></div></div>
                <div class="field"><label for="auto_close_hours"><?= htmlspecialchars(t('config.auto_close_after')) ?></label><div class="input-with-unit-v014"><input id="auto_close_hours" name="auto_close_hours" type="number" min="24" max="2160" value="<?= (int) $settings['auto_close_hours'] ?>"><span><?= htmlspecialchars(t('config.hours')) ?></span></div></div>
                <div class="field"><label for="daily_digest_hour"><?= htmlspecialchars(t('config.daily_digest_hour')) ?></label><div class="input-with-unit-v014"><input id="daily_digest_hour" name="daily_digest_hour" type="number" min="0" max="23" value="<?= (int) $settings['daily_digest_hour'] ?>"><span>h</span></div></div><div class="field"><label for="default_language"><?= htmlspecialchars(t('admin.default_language')) ?></label><select id="default_language" name="default_language"><option value="fr" <?= ($settings['default_language']??'fr')==='fr'?'selected':'' ?>><?= htmlspecialchars(t('settings.language.fr')) ?></option><option value="en" <?= ($settings['default_language']??'fr')==='en'?'selected':'' ?>><?= htmlspecialchars(t('settings.language.en')) ?> (Beta)</option></select><small class="theme-help-v120"><?= htmlspecialchars(t('admin.default_language.help')) ?></small></div>
            </div>
            <div class="configuration-switches-v014">
                <label class="switch-row"><input type="checkbox" name="email_notifications_enabled" value="1" <?= !empty($settings['email_notifications_enabled']) ? 'checked' : '' ?>><span class="switch-copy"><strong><?= htmlspecialchars(t('config.email_notifications')) ?></strong><small><?= htmlspecialchars(t('config.email_notifications_help')) ?></small></span></label>
                <label class="switch-row"><input type="checkbox" name="auto_close_enabled" value="1" <?= !empty($settings['auto_close_enabled']) ? 'checked' : '' ?>><span class="switch-copy"><strong><?= htmlspecialchars(t('config.auto_close')) ?></strong><small><?= htmlspecialchars(t('config.auto_close_help')) ?></small></span></label>
                <label class="switch-row"><input type="checkbox" name="daily_digest_enabled" value="1" <?= !empty($settings['daily_digest_enabled']) ? 'checked' : '' ?>><span class="switch-copy"><strong><?= htmlspecialchars(t('config.daily_digest')) ?></strong><small><?= htmlspecialchars(t('config.daily_digest_help')) ?></small></span></label>
            </div>
            <div class="form-actions"><button class="btn primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> <?= htmlspecialchars(t('admin.save_configuration')) ?></button></div>
        </form>
    </section>

    <section class="panel configuration-card-v014">
        <div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-server"></i></span><div><h2><?= htmlspecialchars(t('config.mail_transport_title')) ?></h2><p><?= htmlspecialchars(t('config.mail_transport_help')) ?></p></div></div></div>
        <div class="configuration-facts-v014">
            <div><span><?= htmlspecialchars(t('config.transport')) ?></span><strong><?= htmlspecialchars((string) ($mail['transport'] ?? 'disabled')) ?></strong></div>
            <div><span><?= htmlspecialchars(t('config.server')) ?></span><strong><?= htmlspecialchars((string) ($mail['host'] ?? '—')) ?>:<?= (int) ($mail['port'] ?? 0) ?></strong></div>
            <div><span><?= htmlspecialchars(t('config.encryption')) ?></span><strong><?= htmlspecialchars((string) ($mail['encryption'] ?? 'none')) ?></strong></div>
            <div><span><?= htmlspecialchars(t('config.sender')) ?></span><strong><?= htmlspecialchars((string) ($mail['from_email'] ?? '—')) ?></strong></div>
        </div>
        <form method="post" class="settings-form">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
            <input type="hidden" name="action" value="test_email">
            <div class="field"><label for="test_email"><?= htmlspecialchars(t('config.test_address')) ?></label><input id="test_email" type="email" name="test_email" required value="<?= htmlspecialchars((string) ($user['email'] ?? '')) ?>"></div>
            <button class="btn secondary" type="submit"><i class="fa-solid fa-paper-plane"></i> <?= htmlspecialchars(t('config.send_test')) ?></button>
        </form>
    </section>

    <section class="panel configuration-card-v014">
        <div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-envelope-open-text"></i></span><div><h2><?= htmlspecialchars(t('config.mail_queue')) ?></h2><p><?= htmlspecialchars(t('config.mail_queue_help')) ?></p></div></div></div>
        <div class="mail-stats-v014">
            <div><span class="mail-stat-dot queued"></span><strong><?= (int) $mailStats['queued'] ?></strong><small><?= htmlspecialchars(t('config.pending')) ?></small></div>
            <div><span class="mail-stat-dot sent"></span><strong><?= (int) $mailStats['sent'] ?></strong><small><?= htmlspecialchars(t('config.sent')) ?></small></div>
            <div><span class="mail-stat-dot failed"></span><strong><?= (int) $mailStats['failed'] ?></strong><small><?= htmlspecialchars(t('config.failed')) ?></small></div>
        </div>
        <div class="settings-security-note-v0135"><i class="fa-solid fa-circle-info"></i><span><?= htmlspecialchars(t('config.log_note')) ?></span></div>
    </section>
</div>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
