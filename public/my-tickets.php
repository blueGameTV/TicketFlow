<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
$auth->requireRole('Collaborateur', 'Manager');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'Mes tickets';
$status = trim((string) ($_GET['status'] ?? ''));
$search = trim((string) ($_GET['q'] ?? ''));
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;

$where = ['t.requester_id = :requester_id', 't.deleted_at IS NULL', "ts.code NOT IN ('resolved','closed','cancelled')"];
$params = ['requester_id' => $user['id']];
if ($status !== '') { $where[] = 'ts.code = :status'; $params['status'] = $status; }
if ($search !== '') {
    $term = '%' . $search . '%';
    $where[] = '(t.ticket_number LIKE :search_number OR t.title LIKE :search_title)';
    $params['search_number'] = $term;
    $params['search_title'] = $term;
}
$from = ' FROM tickets t
    INNER JOIN ticket_statuses ts ON ts.id=t.status_id
    INNER JOIN ticket_types tt ON tt.id=t.type_id
    INNER JOIN priorities p ON p.id=t.priority_id
    LEFT JOIN ticket_categories tc ON tc.id=t.category_id
    LEFT JOIN users ai ON ai.id=t.assigned_it_id
    WHERE ' . implode(' AND ', $where);
$countStmt = $pdo->prepare('SELECT COUNT(*)' . $from);
$countStmt->execute($params);
$totalTickets = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalTickets / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;
$sql = 'SELECT t.ticket_number,t.title,t.created_at,t.updated_at,
               ts.code status_code,ts.name status_name,
               tt.code type_code,tt.name type_name,
               p.name priority_name,p.level priority_level,
               tc.name category_name,
               CONCAT(ai.firstname," ",ai.lastname) assigned_it'
      . $from . ' ORDER BY t.created_at DESC LIMIT ' . $perPage . ' OFFSET ' . $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();
$statuses = $pdo->query("SELECT code,name FROM ticket_statuses WHERE code NOT IN ('resolved','closed','cancelled') ORDER BY id")->fetchAll();
$paginationUrl = static function(int $target) use($status,$search): string {
    $q=['page'=>$target];
    if($status!=='') $q['status']=$status;
    if($search!=='') $q['q']=$search;
    return 'my-tickets.php?' . http_build_query($q);
};
$flash = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
$typeIcons = [
    'Incident' => 'fa-triangle-exclamation',
    'Requête' => 'fa-clipboard-list',
    "Demande d'accès" => 'fa-key',
    'Changement' => 'fa-arrows-rotate',
];
require __DIR__ . '/../templates/shared/header.php';
?>
<section class="page-heading ticket-queue-heading my-tickets-heading">
    <div>
        <span class="badge"><i class="fa-solid <?= $user['role'] === 'Manager' ? 'fa-user-tie' : 'fa-user' ?>"></i> <?= htmlspecialchars($user['role']) ?></span>
        <h1>Mes tickets</h1>
        <p>Suivez vos demandes en cours, leur priorité et l’état de leur prise en charge.</p>
    </div>
    <div class="queue-summary">
        <i class="fa-solid fa-ticket"></i>
        <div>
            <strong><?= $totalTickets ?></strong>
            <span>ticket<?= $totalTickets > 1 ? 's' : '' ?> en cours</span>
        </div>
    </div>
</section>
<?php if($flash):?><div class="alert success"><?= htmlspecialchars($flash) ?></div><?php endif; ?>
<section class="panel queue-filter-panel my-ticket-filter-panel">
    <div class="panel-heading-inline queue-filter-heading">
        <div>
            <h2><i class="fa-solid fa-sliders"></i> Filtres</h2>
            <p>Retrouvez rapidement un ticket grâce au numéro, au titre ou au statut.</p>
        </div>
        <a class="btn primary" href="ticket-create.php"><i class="fa-solid fa-plus"></i> Nouveau ticket</a>
    </div>
    <form method="get" class="filter-bar ticket-it-filter my-ticket-filter-form">
        <label class="search-field"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" placeholder="Numéro ou titre…" value="<?= htmlspecialchars($search) ?>"></label>
        <select name="status"><option value="">Tous les statuts</option><?php foreach($statuses as $row): ?><option value="<?= htmlspecialchars($row['code']) ?>" <?= $status === $row['code'] ? 'selected' : '' ?>><?= htmlspecialchars($row['name']) ?></option><?php endforeach; ?></select>
        <button class="btn primary" type="submit"><i class="fa-solid fa-filter"></i> Filtrer</button>
        <a class="btn ghost" href="my-tickets.php"><i class="fa-solid fa-rotate-left"></i> Réinitialiser</a>
    </form>
</section>
<section class="panel table-panel ticket-queue-panel my-ticket-list-panel">
    <div class="table-header">
        <div>
            <h2>Mes tickets</h2>
            <p class="muted"><?= $totalTickets ?> résultat<?= $totalTickets > 1 ? 's' : '' ?></p>
        </div>
        <span class="queue-page-chip"><i class="fa-regular fa-file-lines"></i> Page <?= $page ?> / <?= $totalPages ?></span>
    </div>
    <?php if (!$tickets): ?>
        <div class="empty-state queue-empty"><i class="fa-regular fa-folder-open"></i><strong>Aucun ticket correspondant.</strong><span>Essayez d’élargir votre recherche ou créez un nouveau ticket.</span></div>
    <?php else: ?>
        <div class="ticket-queue-list my-ticket-queue-list">
        <?php foreach($tickets as $ticket): $typeIcon = $typeIcons[$ticket['type_name']] ?? 'fa-ticket'; ?>
            <article class="ticket-queue-item my-ticket-item priority-border-<?= (int) $ticket['priority_level'] ?>">
                <div class="queue-type-icon"><i class="fa-solid <?= htmlspecialchars($typeIcon) ?>"></i></div>
                <div class="queue-ticket-main">
                    <div class="queue-ticket-topline"><strong><?= htmlspecialchars($ticket['ticket_number']) ?></strong><span><?= htmlspecialchars($ticket['type_name']) ?></span></div>
                    <h3><?= htmlspecialchars($ticket['title']) ?></h3>
                    <div class="queue-ticket-meta">
                        <span><i class="fa-solid fa-layer-group"></i> <?= htmlspecialchars($ticket['category_name'] ?? 'Sans catégorie') ?></span>
                        <span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['created_at']))) ?></span>
                        <span><i class="fa-regular fa-pen-to-square"></i> Mis à jour <?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['updated_at']))) ?></span>
                    </div>
                </div>
                <div class="queue-ticket-badges">
                    <span class="priority-pill priority-level-<?= (int) $ticket['priority_level'] ?>"><i class="fa-solid fa-flag"></i> <?= htmlspecialchars($ticket['priority_name']) ?></span>
                    <span class="ticket-status status-<?= htmlspecialchars($ticket['status_code']) ?>"><i class="fa-solid fa-circle-dot"></i> <?= htmlspecialchars($ticket['status_name']) ?></span>
                </div>
                <div class="queue-assignee"><span>IT assigné</span><strong><i class="fa-solid fa-headset"></i> <?= htmlspecialchars($ticket['assigned_it'] ?: 'Non assigné') ?></strong></div>
                <a class="btn secondary queue-open-btn" href="ticket.php?number=<?= urlencode($ticket['ticket_number']) ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i> Ouvrir</a>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
    <?php if($totalPages > 1): ?><nav class="pagination" aria-label="Pagination des tickets"><a class="pagination-link <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page > 1 ? htmlspecialchars($paginationUrl($page-1)) : '#' ?>"><i class="fa-solid fa-chevron-left"></i></a><?php for($i=1;$i<=$totalPages;$i++): ?><?php if($i===1||$i===$totalPages||abs($i-$page)<=2): ?><a class="pagination-link <?= $i === $page ? 'active' : '' ?>" href="<?= htmlspecialchars($paginationUrl($i)) ?>"><?= $i ?></a><?php elseif(($i===2&&$page>4)||($i===$totalPages-1&&$page<$totalPages-3)): ?><span class="pagination-ellipsis">…</span><?php endif; ?><?php endfor; ?><a class="pagination-link <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= $page < $totalPages ? htmlspecialchars($paginationUrl($page+1)) : '#' ?>"><i class="fa-solid fa-chevron-right"></i></a></nav><?php endif; ?>
</section>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
