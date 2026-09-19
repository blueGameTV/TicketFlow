<?php

declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireRole('Administrateur');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'Journal d’audit';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validate($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash_error'] = 'La session du formulaire a expiré.';
    } elseif (($_POST['action'] ?? '') === 'clear_audit') {
        $count = (int) $pdo->query('SELECT COUNT(*) FROM audit_logs')->fetchColumn();
        $pdo->exec('DELETE FROM audit_logs');
        $_SESSION['flash_success'] = $count > 0
            ? $count . ' entrée' . ($count > 1 ? 's' : '') . ' du journal supprimée' . ($count > 1 ? 's' : '') . '.'
            : 'Le journal d’audit était déjà vide.';
    }
    header('Location: admin-audit.php');
    exit;
}

$q = trim((string) ($_GET['q'] ?? ''));
$action = trim((string) ($_GET['action'] ?? ''));
$sql = 'SELECT a.*, CONCAT(u.firstname," ",u.lastname) user_name,u.email FROM audit_logs a LEFT JOIN users u ON u.id=a.user_id WHERE 1=1';
$params = [];
if ($q !== '') {
    $term = '%' . $q . '%';
    $sql .= ' AND (u.email LIKE :q_email OR u.firstname LIKE :q_firstname OR u.lastname LIKE :q_lastname OR CAST(a.entity_id AS CHAR) LIKE :q_entity OR a.details LIKE :q_details)';
    $params['q_email'] = $term;
    $params['q_firstname'] = $term;
    $params['q_lastname'] = $term;
    $params['q_entity'] = $term;
    $params['q_details'] = $term;
}
if ($action !== '') {
    $sql .= ' AND a.action=:action';
    $params['action'] = $action;
}
$sql .= ' ORDER BY a.created_at DESC,a.id DESC LIMIT 300';
$st = $pdo->prepare($sql);
$st->execute($params);
$logs = $st->fetchAll();
$actions = $pdo->query('SELECT DISTINCT action FROM audit_logs ORDER BY action')->fetchAll(PDO::FETCH_COLUMN);
$success = $_SESSION['flash_success'] ?? null;
$error = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);

require __DIR__ . '/../templates/shared/header.php';
?>
<section class="page-heading page-heading-enhanced audit-heading-v0135">
    <div><span class="badge"><i class="fa-solid fa-shield-halved"></i> Sécurité</span><h1>Journal d’audit</h1><p>Consultez les connexions et les actions sensibles enregistrées par TicketFlow.</p></div>
    <div class="queue-summary"><i class="fa-solid fa-list-ul"></i><div><strong><?= count($logs) ?></strong><span>entrée<?= count($logs) > 1 ? 's' : '' ?> affichée<?= count($logs) > 1 ? 's' : '' ?></span></div></div>
</section>
<?php if ($success): ?><div class="alert success"><?= htmlspecialchars($success) ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<section class="panel queue-filter-panel audit-filter-panel audit-filter-v0135">
    <div class="panel-heading-inline queue-filter-heading"><div><h2><i class="fa-solid fa-filter"></i> Filtres du journal</h2><p>Les 300 événements les plus récents sont consultables.</p></div><form method="post" onsubmit="return confirm('Supprimer définitivement tout le journal d’audit ? Cette action est irréversible.');"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="action" value="clear_audit"><button class="btn danger" type="submit"><i class="fa-solid fa-trash-can"></i> Vider le journal</button></form></div>
    <form method="get" class="filter-bar ticket-it-filter"><label class="search-field"><i class="fa-solid fa-magnifying-glass"></i><input name="q" value="<?= htmlspecialchars($q) ?>" placeholder="Utilisateur, e-mail, ID, détails…"></label><select name="action"><option value="">Toutes les actions</option><?php foreach ($actions as $a): ?><option <?= $a === $action ? 'selected' : '' ?> value="<?= htmlspecialchars($a) ?>"><?= htmlspecialchars($a) ?></option><?php endforeach; ?></select><button class="btn primary" type="submit"><i class="fa-solid fa-filter"></i> Filtrer</button><a class="btn ghost" href="admin-audit.php"><i class="fa-solid fa-rotate-left"></i> Réinitialiser</a></form>
</section>
<section class="panel audit-list-panel-v0135">
    <div class="table-header"><div><h2>Événements récents</h2><p class="muted">Traçabilité des comptes et opérations sensibles</p></div><span class="queue-page-chip"><i class="fa-solid fa-shield"></i> 300 max.</span></div>
    <?php if (!$logs): ?><div class="empty-state queue-empty"><i class="fa-solid fa-shield-circle-check"></i><strong>Aucune entrée dans le journal.</strong><span>Les prochaines actions sensibles apparaîtront ici.</span></div><?php else: ?>
    <div class="audit-event-list">
        <?php foreach ($logs as $l): ?>
        <?php $eventIcon = str_contains((string)$l['action'],'login') ? 'fa-right-to-bracket' : (str_contains((string)$l['action'],'logout') ? 'fa-right-from-bracket' : (str_contains((string)$l['action'],'delete') ? 'fa-trash' : 'fa-shield')); ?>
        <article class="audit-event-card">
            <span class="audit-event-icon"><i class="fa-solid <?= $eventIcon ?>"></i></span>
            <div class="audit-event-main"><strong><?= htmlspecialchars($l['action']) ?></strong><span><?= htmlspecialchars($l['user_name'] ?: 'Système') ?><?= !empty($l['email']) ? ' · '.htmlspecialchars($l['email']) : '' ?></span></div>
            <div class="audit-event-meta"><span>Date</span><strong><?= htmlspecialchars(date('d/m/Y H:i:s', strtotime($l['created_at']))) ?></strong></div>
            <div class="audit-event-meta"><span>Cible</span><strong><?= htmlspecialchars(trim(($l['entity_type'] ?? '') . ' ' . ($l['entity_id'] ?? ''))) ?: '—' ?></strong></div>
            <div class="audit-event-meta"><span>Adresse IP</span><strong><?= htmlspecialchars($l['ip_address'] ?? '') ?: '—' ?></strong></div>
            <div class="audit-event-details"><?= $l['details'] ? htmlspecialchars($l['details']) : '<span class="muted">Aucun détail</span>' ?></div>
        </article>
        <?php endforeach; ?>
    </div><?php endif; ?>
</section>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
