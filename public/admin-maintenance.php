<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireRole('Administrateur');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'Maintenance';
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
                $_SESSION['flash_success'] = 'Maintenance planifiée.';
                header('Location: admin-maintenance.php');
                exit;
            }
            if ($action === 'cancel') {
                $id = (int)($_POST['id'] ?? 0);
                $maintenanceService->cancel($id, (int)$user['id']);
                $auditService->log((int)$user['id'], 'maintenance_cancelled', 'maintenance_window', $id);
                $_SESSION['flash_success'] = 'Maintenance annulée.';
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
<section class="page-heading page-heading-enhanced v110-admin-maintenance-heading"><div><span class="badge"><i class="fa-solid fa-screwdriver-wrench"></i> Administration</span><h1>Maintenance TicketFlow</h1><p>Activez une maintenance immédiate ou programmez une intervention. Les Collaborateurs et Managers sont redirigés automatiquement vers la page de maintenance lorsque l'accès est bloqué.</p></div></section>
<?php if ($success): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert error"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="v110-grid v110-maintenance-admin-grid">
<section class="panel v110-maintenance-admin-card v110-maintenance-admin-current">
<div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-power-off"></i></span><div><h2>Mode maintenance immédiat</h2><p>Bloque l’accès aux utilisateurs non autorisés.</p></div></div></div>
<form method="post" class="form-grid v110-maintenance-admin-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="manual">
<div class="field span-2"><label class="v110-check-row"><input type="checkbox" name="enabled" value="1" <?= !empty($settings['maintenance_mode_enabled']) && $settings['maintenance_mode_enabled'] !== '0' ? 'checked' : '' ?>> Activer le mode maintenance</label></div>
<div class="field span-2"><label>Message affiché</label><textarea name="message" maxlength="1200" rows="5"><?= htmlspecialchars((string)($settings['maintenance_message'] ?? 'TicketFlow est actuellement en maintenance.')) ?></textarea></div>
<div class="field"><label>Fin estimée</label><input type="datetime-local" name="expected_end" value="<?= htmlspecialchars(!empty($settings['maintenance_expected_end']) ? date('Y-m-d\TH:i', strtotime((string)$settings['maintenance_expected_end'])) : '') ?>"><span class="v110-form-help">Si une date est renseignée, le mode maintenance se désactive automatiquement à cette heure.</span></div>
<div class="field"><label class="v110-check-row"><input type="checkbox" name="allow_it" value="1" <?= ($settings['maintenance_allow_it'] ?? '1') !== '0' ? 'checked' : '' ?>> Autoriser les IT pendant la maintenance</label><span class="v110-form-help">Les Administrateurs restent toujours autorisés.</span></div>
<div class="form-actions span-2"><button class="btn primary" type="submit">Enregistrer le mode maintenance</button></div>
</form>
</section>

<section class="panel v110-maintenance-admin-card v110-maintenance-admin-schedule">
<div class="panel-title-row"><div><span class="panel-icon"><i class="fa-regular fa-calendar"></i></span><div><h2>Planifier une maintenance</h2><p>Une banderole sera visible 24 h avant le début.</p></div></div></div>
<form method="post" class="form-grid v110-maintenance-admin-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="schedule">
<div class="field span-2"><label>Titre *</label><input name="title" maxlength="160" required placeholder="Maintenance réseau"></div>
<div class="field span-2"><label>Message *</label><textarea name="message" maxlength="1200" rows="4" required placeholder="Le VPN sera indisponible pendant l’intervention."></textarea></div>
<div class="field"><label>Début *</label><input type="datetime-local" name="starts_at" required></div>
<div class="field"><label>Fin prévue *</label><input type="datetime-local" name="ends_at" required></div>
<div class="field span-2"><label class="v110-check-row"><input type="checkbox" name="block_access" value="1"> Bloquer l’accès à TicketFlow pendant cette fenêtre</label></div>
<div class="form-actions span-2"><button class="btn primary" type="submit">Planifier</button></div>
</form>
</section>

<section class="panel v110-span-2 v110-maintenance-admin-card v110-maintenance-calendar">
<div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-list-check"></i></span><div><h2>Calendrier des maintenances</h2><p>Maintenances passées, futures et annulées.</p></div></div></div>
<div class="v110-list">
<?php if (!$windows): ?><div class="v110-empty">Aucune maintenance planifiée.</div><?php endif; ?>
<?php foreach ($windows as $window): $cancelled = !empty($window['cancelled_at']); $past = strtotime((string)$window['ends_at']) < time(); ?>
<article class="v110-list-item"><div class="v110-list-item-head"><div><span class="v110-pill <?= $cancelled ? 'resolved' : 'maintenance' ?>"><?= $cancelled ? 'Annulée' : ($past ? 'Terminée' : 'Planifiée') ?></span><h3><?= htmlspecialchars((string)$window['title']) ?></h3></div><strong><?= !empty($window['block_access']) ? 'Accès bloqué' : 'Information uniquement' ?></strong></div><p><?= nl2br(htmlspecialchars((string)$window['message'])) ?></p><div class="v110-meta"><span><?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$window['starts_at']))) ?> → <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$window['ends_at']))) ?></span><span>Créée par <?= htmlspecialchars($window['creator_firstname'] . ' ' . $window['creator_lastname']) ?></span></div><?php if (!$cancelled && !$past): ?><div class="v110-actions"><form method="post" onsubmit="return confirm('Annuler cette maintenance ?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="cancel"><input type="hidden" name="id" value="<?= (int)$window['id'] ?>"><button class="btn secondary v110-danger" type="submit">Annuler</button></form></div><?php endif; ?></article>
<?php endforeach; ?>
</div>
</section>
</div>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
