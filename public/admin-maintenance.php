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
        $errors[] = t('maint.form_expired');
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

                $_SESSION['flash_success'] = t('maint.manual_saved');
                header('Location: admin-maintenance.php');
                exit;
            }

            if ($action === 'stop_manual') {
                $maintenanceService->stopManual();
                $auditService->log((int)$user['id'], 'maintenance_mode_stopped', 'application', null);
                $_SESSION['flash_success'] = t('maint.manual_stopped');
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

            if ($action === 'update_active') {
                $id = (int)($_POST['id'] ?? 0);
                $maintenanceService->updateWindow($id, $_POST);
                $auditService->log((int)$user['id'], 'maintenance_active_updated', 'maintenance_window', $id, [
                    'title' => (string)($_POST['title'] ?? ''),
                    'starts_at' => (string)($_POST['starts_at'] ?? ''),
                    'ends_at' => (string)($_POST['ends_at'] ?? ''),
                    'block_access' => !empty($_POST['block_access']),
                ]);
                $_SESSION['flash_success'] = t('maint.active_updated');
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
$active = $maintenanceService->activeState();
$windows = $maintenanceService->all(100);

$formatInput = static function (?string $value): string {
    if (!$value) return '';
    $ts = strtotime($value);
    return $ts === false ? '' : date('Y-m-d\TH:i', $ts);
};

require __DIR__ . '/../templates/shared/header.php';
?>

<section class="page-heading page-heading-enhanced v130-maintenance-heading">
    <div>
        <span class="badge"><i class="fa-solid fa-screwdriver-wrench"></i> <?= htmlspecialchars(t('common.administration')) ?></span>
        <h1><?= htmlspecialchars(t('maint.title')) ?></h1>
        <p><?= htmlspecialchars(t('maint.subtitle')) ?></p>
    </div>
</section>

<?php if ($success): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert error"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<section class="panel v130-active-maintenance <?= !empty($active['active']) ? 'is-active' : 'is-idle' ?>">
    <div class="v130-active-maintenance-head">
        <div><span class="panel-icon"><i class="fa-solid fa-tower-broadcast"></i></span><div>
            <div class="v130-maintenance-title-line">
                <h2><?= htmlspecialchars(t('maint.active_title')) ?></h2>
                <?php if (!empty($active['active'])): ?><span class="v130-maintenance-state live"><i class="fa-solid fa-circle"></i> <?= htmlspecialchars(t('maint.active_now')) ?></span>
                <?php else: ?><span class="v130-maintenance-state idle"><?= htmlspecialchars(t('maint.no_active')) ?></span><?php endif; ?>
            </div>
            <p><?= htmlspecialchars(t('maint.active_help')) ?></p>
        </div></div>
    </div>

    <?php if (empty($active['active'])): ?>
        <div class="v130-maintenance-empty"><i class="fa-regular fa-circle-check"></i><div><strong><?= htmlspecialchars(t('maint.no_active_title')) ?></strong><span><?= htmlspecialchars(t('maint.no_active_help')) ?></span></div></div>
    <?php elseif (($active['source'] ?? '') === 'manual'): ?>
        <div class="v130-maintenance-active-grid">
            <div class="v130-maintenance-summary">
                <span class="v130-maintenance-source"><?= htmlspecialchars(t('maint.source_manual')) ?></span>
                <h3><?= htmlspecialchars(t('maint.immediate')) ?></h3>
                <p><?= nl2br(htmlspecialchars((string)$active['message'])) ?></p>
                <div class="v130-maintenance-facts">
                    <div><span><?= htmlspecialchars(t('maint.access')) ?></span><strong><?= htmlspecialchars(t('maint.blocked')) ?></strong></div>
                    <div><span><?= htmlspecialchars(t('maint.it_access')) ?></span><strong><?= htmlspecialchars(!empty($active['allow_it']) ? t('common.yes') : t('common.no')) ?></strong></div>
                    <div><span><?= htmlspecialchars(t('maint.estimated_end')) ?></span><strong><?= !empty($active['ends_at']) ? htmlspecialchars(date('d/m/Y H:i', strtotime((string)$active['ends_at']))) : htmlspecialchars(t('maint.no_end_defined')) ?></strong></div>
                </div>
            </div>
            <div class="v130-maintenance-active-actions">
                <div class="v130-maintenance-action-note"><i class="fa-solid fa-pen-to-square"></i><span><?= htmlspecialchars(t('maint.manual_edit_help')) ?></span></div>
                <form method="post" onsubmit="return confirm('<?= htmlspecialchars(addslashes(t('maint.stop_manual_confirm'))) ?>')">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
                    <input type="hidden" name="action" value="stop_manual">
                    <button class="btn danger" type="submit"><i class="fa-solid fa-stop"></i> <?= htmlspecialchars(t('maint.stop_now')) ?></button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <form method="post" class="v130-maintenance-edit-active">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
            <input type="hidden" name="action" value="update_active">
            <input type="hidden" name="id" value="<?= (int)$active['id'] ?>">
            <div class="v130-maintenance-edit-top">
                <div><span class="v130-maintenance-source"><?= htmlspecialchars(t('maint.source_scheduled')) ?></span><h3><?= htmlspecialchars((string)$active['title']) ?></h3></div>
                <span class="v130-maintenance-access <?= !empty($active['block_access']) ? 'blocked' : 'info' ?>"><i class="fa-solid <?= !empty($active['block_access']) ? 'fa-lock' : 'fa-circle-info' ?>"></i><?= htmlspecialchars(!empty($active['block_access']) ? t('maint.blocked') : t('maint.info_only')) ?></span>
            </div>
            <div class="form-grid">
                <div class="field span-2"><label><?= htmlspecialchars(t('maint.title_field')) ?></label><input name="title" maxlength="160" required value="<?= htmlspecialchars((string)$active['title']) ?>"></div>
                <div class="field span-2"><label><?= htmlspecialchars(t('maint.message_field')) ?></label><textarea name="message" maxlength="1200" rows="4" required><?= htmlspecialchars((string)$active['message']) ?></textarea></div>
                <div class="field"><label><?= htmlspecialchars(t('maint.start')) ?></label><input type="datetime-local" name="starts_at" required value="<?= htmlspecialchars($formatInput((string)$active['starts_at'])) ?>"><span class="v110-form-help"><?= htmlspecialchars(t('maint.active_start_help')) ?></span></div>
                <div class="field"><label><?= htmlspecialchars(t('maint.end')) ?></label><input type="datetime-local" name="ends_at" required value="<?= htmlspecialchars($formatInput((string)$active['ends_at'])) ?>"><span class="v110-form-help"><?= htmlspecialchars(t('maint.active_end_help')) ?></span></div>
                <div class="field span-2"><label class="v110-check-row"><input type="checkbox" name="block_access" value="1" <?= !empty($active['block_access']) ? 'checked' : '' ?>> <?= htmlspecialchars(t('maint.block')) ?></label></div>
            </div>
            <div class="v130-maintenance-edit-actions"><button class="btn primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> <?= htmlspecialchars(t('maint.save_active_changes')) ?></button></div>
        </form>
        <div class="v130-maintenance-stop-row"><div><strong><?= htmlspecialchars(t('maint.stop_active_title')) ?></strong><span><?= htmlspecialchars(t('maint.stop_active_help')) ?></span></div>
            <form method="post" onsubmit="return confirm('<?= htmlspecialchars(addslashes(t('maint.cancel_active_confirm'))) ?>')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$active['id'] ?>">
                <button class="btn danger" type="submit"><i class="fa-solid fa-stop"></i> <?= htmlspecialchars(t('maint.stop_now')) ?></button>
            </form>
        </div>
    <?php endif; ?>
</section>

<div class="v110-grid v110-maintenance-admin-grid">
<section class="panel v110-maintenance-admin-card v110-maintenance-admin-current">
    <div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-power-off"></i></span><div><h2><?= htmlspecialchars(t('maint.immediate')) ?></h2><p><?= htmlspecialchars(t('maint.immediate_help')) ?></p></div></div></div>
    <form method="post" class="form-grid v110-maintenance-admin-form">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="manual">
        <div class="field span-2"><label class="v110-check-row"><input type="checkbox" name="enabled" value="1" <?= !empty($settings['maintenance_mode_enabled']) && $settings['maintenance_mode_enabled'] !== '0' ? 'checked' : '' ?>> <?= htmlspecialchars(t('maint.enable')) ?></label></div>
        <div class="field span-2"><label><?= htmlspecialchars(t('maint.message')) ?></label><textarea name="message" maxlength="1200" rows="5"><?= htmlspecialchars((string)($settings['maintenance_message'] ?? t('maint.default_message'))) ?></textarea></div>
        <div class="field"><label><?= htmlspecialchars(t('maint.estimated_end')) ?></label><input type="datetime-local" name="expected_end" value="<?= htmlspecialchars($formatInput((string)($settings['maintenance_expected_end'] ?? ''))) ?>"><span class="v110-form-help"><?= htmlspecialchars(t('maint.estimated_end_help')) ?></span></div>
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
        <?php foreach ($windows as $window):
            $cancelled = !empty($window['cancelled_at']); $startTs = strtotime((string)$window['starts_at']); $endTs = strtotime((string)$window['ends_at']);
            $isActive = !$cancelled && $startTs <= time() && $endTs > time(); $past = $endTs < time();
            $statusLabel = $cancelled ? t('maint.cancelled') : ($isActive ? t('maint.active_now') : ($past ? t('maint.finished') : t('maint.planned')));
        ?>
        <article class="v110-list-item <?= $isActive ? 'v130-window-active' : '' ?>">
            <div class="v110-list-item-head"><div><span class="v110-pill <?= $cancelled ? 'resolved' : ($isActive ? 'active' : 'maintenance') ?>"><?= htmlspecialchars($statusLabel) ?></span><h3><?= htmlspecialchars((string)$window['title']) ?></h3></div><strong><?= htmlspecialchars(!empty($window['block_access']) ? t('maint.blocked') : t('maint.info_only')) ?></strong></div>
            <p><?= nl2br(htmlspecialchars((string)$window['message'])) ?></p>
            <div class="v110-meta"><span><?= htmlspecialchars(date('d/m/Y H:i', $startTs)) ?> → <?= htmlspecialchars(date('d/m/Y H:i', $endTs)) ?></span><span><?= htmlspecialchars(t('maint.created_by', ['name' => $window['creator_firstname'].' '.$window['creator_lastname']])) ?></span></div>
            <?php if (!$cancelled && !$past && !$isActive): ?><div class="v110-actions"><form method="post" onsubmit="return confirm('<?= htmlspecialchars(addslashes(t('maint.cancel_confirm'))) ?>')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$window['id'] ?>"><button class="btn secondary v110-danger" type="submit"><?= htmlspecialchars(t('maint.cancel')) ?></button></form></div>
            <?php elseif ($isActive): ?><div class="v130-active-calendar-note"><i class="fa-solid fa-tower-broadcast"></i><span><?= htmlspecialchars(t('maint.manage_active_above')) ?></span></div><?php endif; ?>
        </article>
        <?php endforeach; ?>
    </div>
</section>
</div>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
