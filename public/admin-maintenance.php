<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireRole('Administrateur');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = t('nav.maintenance');
$errors = [];
$success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La session du formulaire a expiré.';
    } else {
        try {
            $action = (string)($_POST['action'] ?? 'manual');
            if ($action === 'manual') {
                $maintenanceService->setManual(
                    !empty($_POST['enabled']),
                    (string)($_POST['message'] ?? ''),
                    (string)($_POST['expected_end'] ?? ''),
                    !empty($_POST['allow_it'])
                );
                $auditService->log((int)$user['id'], 'maintenance_mode_updated', 'application', null, [
                    'enabled' => !empty($_POST['enabled']),
                    'allow_it' => !empty($_POST['allow_it']),
                ]);
                $_SESSION['flash_success'] = 'Mode maintenance mis à jour.';
                header('Location: admin-maintenance.php');
                exit;
            }
            if ($action === 'schedule') {
                $id = $maintenanceService->schedule((int)$user['id'], $_POST);
                $auditService->log((int)$user['id'], 'maintenance_scheduled', 'maintenance_window', $id, [
                    'title' => (string)($_POST['title'] ?? ''),
                    'block_access' => !empty($_POST['block_access']),
                ]);
                $_SESSION['flash_success'] = t('maint.scheduled_success');
                header('Location: admin-maintenance.php');
                exit;
            }
            if ($action === 'cancel') {
                $id = (int)($_POST['id'] ?? 0);
                $maintenanceService->cancel($id, (int)$user['id']);
                $auditService->log((int)$user['id'], 'maintenance_cancelled', 'maintenance_window', $id);
                $_SESSION['flash_success'] = t('maint.cancelled_success');
                header('Location: admin-maintenance.php');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$settings = $appSettingService->all();
$windows = $maintenanceService->all(100);
require __DIR__ . '/../templates/shared/header.php';
?>
<section class="page-heading page-heading-enhanced v110-admin-maintenance-heading"><div><span class="badge"><i class="fa-solid fa-screwdriver-wrench"></i> <?= htmlspecialchars(t('common.administration')) ?></span><h1><?= htmlspecialchars(t('maint.title')) ?></h1><p><?= htmlspecialchars(t('maint.subtitle')) ?></p></div></section>
<?php if ($success): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert error"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="v110-grid v110-maintenance-admin-grid">
<section class="panel v110-maintenance-admin-card v110-maintenance-admin-current">
<div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-power-off"></i></span><div><h2><?= htmlspecialchars(t('maint.immediate')) ?></h2><p><?= htmlspecialchars(t('maint.immediate_help')) ?></p></div></div></div>
<form method="post" class="form-grid v110-maintenance-admin-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="manual">
<div class="field span-2"><label class="v110-check-row"><input type="checkbox" name="enabled" value="1" <?= !empty($settings['maintenance_mode_enabled']) && $settings['maintenance_mode_enabled'] !== '0' ? 'checked' : '' ?>> <?= htmlspecialchars(t('maint.enable')) ?></label></div>
<div class="field span-2"><label><?= htmlspecialchars(t('maint.message')) ?></label><textarea name="message" maxlength="1200" rows="5"><?= htmlspecialchars((string)($settings['maintenance_message'] ?? t('maint.default_message'))) ?></textarea></div>
<div class="field"><label><?= htmlspecialchars(t('maint.estimated_end')) ?></label><input type="datetime-local" name="expected_end" value="<?= htmlspecialchars(!empty($settings['maintenance_expected_end']) ? date('Y-m-d\TH:i', strtotime((string)$settings['maintenance_expected_end'])) : '') ?>"><span class="v110-form-help"><?= htmlspecialchars(t('maint.estimated_end_help')) ?></span></div>
<div class="field"><label class="v110-check-row"><input type="checkbox" name="allow_it" value="1" <?= ($settings['maintenance_allow_it'] ?? '1') !== '0' ? 'checked' : '' ?>> <?= htmlspecialchars(t('maint.allow_it')) ?></label><span class="v110-form-help"><?= htmlspecialchars(t('maint.admin_always')) ?></span></div>
<div class="form-actions span-2"><button class="btn primary" type="submit"><?= htmlspecialchars(t('maint.save')) ?></button></div>
</form>
</section>

<section class="panel v110-maintenance-admin-card v110-maintenance-admin-schedule">
<div class="panel-title-row"><div><span class="panel-icon"><i class="fa-regular fa-calendar"></i></span><div><h2><?= htmlspecialchars(t('maint.schedule')) ?></h2><p><?= htmlspecialchars(t('maint.schedule_help')) ?></p></div></div></div>
<form method="post" class="form-grid v110-maintenance-admin-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="schedule">
<div class="field span-2"><label><?= htmlspecialchars(t('maint.title_field')) ?></label><input name="title" maxlength="160" required placeholder="<?= htmlspecialchars(t('maint.title_placeholder')) ?>"></div>
<div class="field span-2"><label><?= htmlspecialchars(t('maint.message_field')) ?></label><textarea name="message" maxlength="1200" rows="4" required placeholder="<?= htmlspecialchars(t('maint.message_placeholder')) ?>"></textarea></div>
<div class="field"><label><?= htmlspecialchars(t('maint.start')) ?></label><input type="datetime-local" name="starts_at" required></div>
<div class="field"><label><?= htmlspecialchars(t('maint.end')) ?></label><input type="datetime-local" name="ends_at" required></div>
<div class="field span-2"><label class="v110-check-row"><input type="checkbox" name="block_access" value="1"> <?= htmlspecialchars(t('maint.block')) ?></label></div>
<div class="form-actions span-2"><button class="btn primary" type="submit"><?= htmlspecialchars(t('maint.plan')) ?></button></div>
</form>
</section>

<section class="panel v110-span-2 v110-maintenance-admin-card v110-maintenance-calendar">
<div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-list-check"></i></span><div><h2><?= htmlspecialchars(t('maint.calendar')) ?></h2><p><?= htmlspecialchars(t('maint.calendar_help')) ?></p></div></div></div>
<div class="v110-list">
<?php if (!$windows): ?><div class="v110-empty"><?= htmlspecialchars(t('maint.none')) ?></div><?php endif; ?>
<?php foreach ($windows as $window): $cancelled = !empty($window['cancelled_at']); $past = strtotime((string)$window['ends_at']) < time(); ?>
<article class="v110-list-item"><div class="v110-list-item-head"><div><span class="v110-pill <?= $cancelled ? 'resolved' : 'maintenance' ?>"><?= htmlspecialchars($cancelled ? t('maint.cancelled') : ($past ? t('maint.finished') : t('maint.planned'))) ?></span><h3><?= htmlspecialchars((string)$window['title']) ?></h3></div><strong><?= htmlspecialchars(!empty($window['block_access']) ? t('maint.blocked') : t('maint.info_only')) ?></strong></div><p><?= nl2br(htmlspecialchars((string)$window['message'])) ?></p><div class="v110-meta"><span><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$window['starts_at']))) ?> → <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$window['ends_at']))) ?></span><span><?= htmlspecialchars(t('maint.created_by',['name'=>$window['creator_firstname'].' '.$window['creator_lastname']])) ?></span></div><?php if (!$cancelled && !$past): ?><div class="v110-actions"><form method="post" onsubmit="return confirm('<?= htmlspecialchars(addslashes(t('maint.cancel_confirm'))) ?>')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$window['id'] ?>"><button class="btn secondary v110-danger" type="submit"><?= htmlspecialchars(t('maint.cancel')) ?></button></form></div><?php endif; ?></article>
<?php endforeach; ?>
</div>
</section>
</div>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
