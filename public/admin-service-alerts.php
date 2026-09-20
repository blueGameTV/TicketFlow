<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireRole('Administrateur', 'IT');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'Alertes de service';
$errors = [];
$success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La session du formulaire a expiré.';
    } else {
        $action = (string)($_POST['action'] ?? 'create');
        try {
            if ($action === 'create') {
                $id = $serviceAlertService->create((int)$user['id'], $_POST);
                $auditService->log((int)$user['id'], 'service_alert_created', 'service_alert', $id, [
                    'title' => (string)($_POST['title'] ?? ''),
                    'severity' => (string)($_POST['severity'] ?? ''),
                ]);
                $_SESSION['flash_success'] = 'Alerte de service publiée.';
                header('Location: admin-service-alerts.php');
                exit;
            }
            if ($action === 'resolve') {
                $id = (int)($_POST['id'] ?? 0);
                $serviceAlertService->resolve($id, (int)$user['id']);
                $auditService->log((int)$user['id'], 'service_alert_resolved', 'service_alert', $id);
                $_SESSION['flash_success'] = 'Alerte marquée comme résolue.';
                header('Location: admin-service-alerts.php');
                exit;
            }
            if ($action === 'delete') {
                if (($user['role'] ?? '') !== 'Administrateur') {
                    throw new RuntimeException('Seul un Administrateur peut supprimer une alerte.');
                }
                $id = (int)($_POST['id'] ?? 0);
                $serviceAlertService->delete($id);
                $auditService->log((int)$user['id'], 'service_alert_deleted', 'service_alert', $id);
                $_SESSION['flash_success'] = 'Alerte supprimée.';
                header('Location: admin-service-alerts.php');
                exit;
            }
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }
}

$alerts = $serviceAlertService->all(150);
require __DIR__ . '/../templates/shared/header.php';
?>
<section class="page-heading page-heading-enhanced">
    <div><span class="badge"><i class="fa-solid fa-triangle-exclamation"></i> Service</span><h1>Alertes de service globales</h1><p>Informez immédiatement les utilisateurs d’un incident, d’une dégradation ou d’une maintenance.</p></div>
</section>
<?php if ($success): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="alert error"><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>

<div class="v110-grid">
    <section class="panel">
        <div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-bullhorn"></i></span><div><h2>Créer une alerte</h2><p>La banderole apparaît automatiquement pour les utilisateurs connectés.</p></div></div></div>
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
            <input type="hidden" name="action" value="create">
            <div class="field"><label>Application / service *</label><input name="service_name" maxlength="120" required placeholder="Microsoft Outlook, VPN, ERP…"></div>
            <div class="field"><label>Niveau *</label><select name="severity" required><option value="info">Information</option><option value="degraded">Dégradation</option><option value="major">Incident majeur</option><option value="critical">Incident critique</option><option value="maintenance">Maintenance</option></select></div>
            <div class="field span-2"><label>Titre * <span class="v110-form-help">80 caractères maximum</span></label><input name="title" maxlength="80" required placeholder="Indisponibilité en cours"></div>
            <div class="field span-2"><label>Message * <span class="v110-form-help">2000 caractères maximum</span></label><textarea name="message" maxlength="2000" rows="6" required placeholder="L’équipe IT analyse actuellement le problème…"></textarea></div>
            <div class="field"><label>Début</label><input type="datetime-local" name="starts_at" value="<?= date('Y-m-d\TH:i') ?>"></div>
            <div class="field"><label>Fin prévue</label><input type="datetime-local" name="ends_at"></div>
            <div class="field span-2"><label>État initial</label><select name="status"><option value="active">En cours</option><option value="monitoring">Surveillance</option></select></div>
            <div class="form-actions span-2"><button class="btn primary" type="submit"><i class="fa-solid fa-bullhorn"></i> Publier l’alerte</button></div>
        </form>
    </section>

    <section class="panel">
        <div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-wave-square"></i></span><div><h2>Alertes actives</h2><p><?= count($serviceAlertService->active()) ?> alerte(s) actuellement visible(s).</p></div></div></div>
        <div class="v110-list">
            <?php $activeCount = 0; foreach ($alerts as $alert): if (!in_array($alert['status'], ['active','monitoring'], true)) continue; $activeCount++; ?>
                <article class="v110-list-item">
                    <div class="v110-list-item-head"><div><span class="v110-pill <?= htmlspecialchars((string)$alert['severity']) ?>"><?= htmlspecialchars($serviceAlertService->severityLabel((string)$alert['severity'])) ?></span><h3><?= htmlspecialchars((string)$alert['title']) ?></h3></div><strong><?= htmlspecialchars((string)$alert['service_name']) ?></strong></div>
                    <p><?= nl2br(htmlspecialchars((string)$alert['message'])) ?></p>
                    <div class="v110-meta"><span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$alert['starts_at']))) ?></span><span>Créée par <?= htmlspecialchars($alert['creator_firstname'] . ' ' . $alert['creator_lastname']) ?></span></div>
                    <div class="v110-actions"><form method="post"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="resolve"><input type="hidden" name="id" value="<?= (int)$alert['id'] ?>"><button class="btn secondary" type="submit"><i class="fa-solid fa-check"></i> Marquer résolue</button></form><?php if (($user['role'] ?? '') === 'Administrateur'): ?><form method="post" onsubmit="return confirm('Supprimer définitivement cette alerte ?')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$alert['id'] ?>"><button class="btn secondary v110-danger" type="submit"><i class="fa-solid fa-trash"></i> Supprimer</button></form><?php endif; ?></div>
                </article>
            <?php endforeach; if ($activeCount === 0): ?><div class="v110-empty">Aucune alerte active.</div><?php endif; ?>
        </div>
    </section>

    <section class="panel v110-span-2">
        <div class="panel-title-row"><div><span class="panel-icon"><i class="fa-solid fa-clock-rotate-left"></i></span><div><h2>Historique</h2><p>Dernières alertes publiées.</p></div></div></div>
        <div class="v110-list">
            <?php foreach ($alerts as $alert): ?>
                <article class="v110-list-item"><div class="v110-list-item-head"><div><span class="v110-pill <?= $alert['status'] === 'resolved' ? 'resolved' : htmlspecialchars((string)$alert['severity']) ?>"><?= $alert['status'] === 'resolved' ? 'Résolue' : htmlspecialchars($serviceAlertService->severityLabel((string)$alert['severity'])) ?></span><strong><?= htmlspecialchars((string)$alert['title']) ?></strong></div><span><?= htmlspecialchars((string)$alert['service_name']) ?></span></div><div class="v110-meta"><span>Créée le <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$alert['created_at']))) ?></span><?php if (!empty($alert['resolved_at'])): ?><span>Résolue le <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string)$alert['resolved_at']))) ?></span><?php endif; ?></div></article>
            <?php endforeach; ?>
        </div>
    </section>
</div>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
