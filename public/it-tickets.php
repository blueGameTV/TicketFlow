<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireRole('IT', 'Administrateur');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'Tickets Support IT';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validate($_POST['csrf_token'] ?? null)) {
        $_SESSION['flash_error'] = 'La session du formulaire a expiré.';
    } else {
        $viewAction = (string) ($_POST['view_action'] ?? '');
        $bulkAction = (string) ($_POST['bulk_action'] ?? '');
        try {
            if ($bulkAction !== '') {
                $ticketIds = array_values(array_unique(array_filter(
                    array_map('intval', (array) ($_POST['ticket_ids'] ?? [])),
                    static fn(int $id): bool => $id > 0
                )));
                if (!$ticketIds) {
                    throw new RuntimeException('Sélectionnez au moins un ticket.');
                }
                if (count($ticketIds) > 50) {
                    throw new RuntimeException('Vous pouvez modifier au maximum 50 tickets à la fois.');
                }
                if (!in_array($bulkAction, ['priority', 'status', 'assign'], true)) {
                    throw new RuntimeException('Action multiple invalide.');
                }

                $placeholders = implode(',', array_fill(0, count($ticketIds), '?'));
                $accessStmt = $pdo->prepare(
                    "SELECT t.id, t.assigned_it_id, ts.code AS status_code, assigned_it.group_id AS assigned_it_group_id
                     FROM tickets t
                     INNER JOIN ticket_statuses ts ON ts.id = t.status_id
                     LEFT JOIN users assigned_it ON assigned_it.id = t.assigned_it_id
                     WHERE t.id IN ($placeholders) AND t.deleted_at IS NULL"
                );
                $accessStmt->execute($ticketIds);
                $selectedTickets = $accessStmt->fetchAll();
                $selectedById = [];
                foreach ($selectedTickets as $selectedTicket) {
                    $selectedById[(int) $selectedTicket['id']] = $selectedTicket;
                }

                $successCount = 0;
                $skippedCount = 0;
                $firstError = null;
                foreach ($ticketIds as $ticketId) {
                    $selectedTicket = $selectedById[$ticketId] ?? null;
                    if (!$selectedTicket || in_array((string) $selectedTicket['status_code'], ['resolved', 'closed', 'cancelled'], true)) {
                        $skippedCount++;
                        continue;
                    }

                    if ($user['role'] === 'IT') {
                        $allowed = $selectedTicket['assigned_it_id'] === null
                            || (int) $selectedTicket['assigned_it_id'] === (int) $user['id']
                            || ($user['group_id'] !== null
                                && $selectedTicket['assigned_it_group_id'] !== null
                                && (int) $selectedTicket['assigned_it_group_id'] === (int) $user['group_id']);
                        if (!$allowed) {
                            $skippedCount++;
                            continue;
                        }
                    }

                    try {
                        if ($bulkAction === 'priority') {
                            $priorityId = (int) ($_POST['bulk_priority_id'] ?? 0);
                            if ($priorityId <= 0) {
                                throw new RuntimeException('Sélectionnez une importance.');
                            }
                            $ticketService->updatePriority($ticketId, $priorityId, $user);
                        } elseif ($bulkAction === 'status') {
                            $statusCode = trim((string) ($_POST['bulk_status_code'] ?? ''));
                            if ($statusCode === '') {
                                throw new RuntimeException('Sélectionnez un statut.');
                            }
                            $ticketService->updateStatus($ticketId, $statusCode, $user);
                        } elseif ($bulkAction === 'assign') {
                            $itId = (int) ($_POST['bulk_it_id'] ?? 0);
                            if ($itId <= 0) {
                                throw new RuntimeException('Sélectionnez un technicien Support IT.');
                            }
                            $ticketService->transfer($ticketId, $itId, $user);
                        }
                        $successCount++;
                    } catch (RuntimeException $e) {
                        $skippedCount++;
                        $firstError ??= $e->getMessage();
                    }
                }

                if ($successCount === 0) {
                    throw new RuntimeException($firstError ?? 'Aucun ticket sélectionné n’a pu être modifié.');
                }
                $_SESSION['flash_success'] = $successCount . ' ticket' . ($successCount > 1 ? 's' : '') . ' modifié' . ($successCount > 1 ? 's' : '') . ' avec succès.'
                    . ($skippedCount > 0 ? ' ' . $skippedCount . ' ticket' . ($skippedCount > 1 ? 's ont' : ' a') . ' été ignoré' . ($skippedCount > 1 ? 's' : '') . '.' : '');
            } elseif ($viewAction === 'save') {
                $savedTicketViewService->create(
                    (int) $user['id'],
                    (string) ($_POST['view_name'] ?? ''),
                    $_POST,
                    (string) $user['role']
                );
                $_SESSION['flash_success'] = 'Vue enregistrée avec succès.';
            } elseif ($viewAction === 'delete') {
                $savedTicketViewService->delete((int) $user['id'], (int) ($_POST['view_id'] ?? 0));
                $_SESSION['flash_success'] = 'Vue enregistrée supprimée.';
            }
        } catch (RuntimeException $e) {
            $_SESSION['flash_error'] = $e->getMessage();
        }
    }
    $returnQuery = trim((string) ($_POST['return_query'] ?? ''));
    header('Location: it-tickets.php' . ($returnQuery !== '' ? '?' . $returnQuery : ''));
    exit;
}

$scope = (string) ($_GET['scope'] ?? 'mine');
$allowedScopes = ['mine', 'unassigned', 'team', 'all', 'archive'];
if (!in_array($scope, $allowedScopes, true)) $scope = 'mine';
if ($user['role'] === 'IT' && $scope === 'all') $scope = 'mine';

$q = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$priority = trim((string) ($_GET['priority'] ?? ''));
$typeId = max(0, (int) ($_GET['type_id'] ?? 0));
$categoryId = max(0, (int) ($_GET['category_id'] ?? 0));
$groupId = max(0, (int) ($_GET['group_id'] ?? 0));
$managerId = max(0, (int) ($_GET['manager_id'] ?? 0));
$assignedIt = trim((string) ($_GET['assigned_it'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$sla = $user['role'] === 'Administrateur' ? trim((string) ($_GET['sla'] ?? '')) : '';
if (!in_array($sla, ['', 'response', 'resolution', 'ok'], true)) $sla = '';
if ($scope === 'archive') $sla = '';

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = (int) ($_GET['per_page'] ?? 15);
if (!in_array($perPage, [15, 30, 50], true)) $perPage = 15;
$where = ['t.deleted_at IS NULL'];
$params = [];

if ($scope === 'archive') {
    $where[] = "ts.code IN ('resolved','closed','cancelled')";
    if ($user['role'] === 'IT') {
        if ($user['group_id'] !== null) {
            $where[] = '(assigned_it.group_id = :archive_group_id OR t.assigned_it_id = :archive_user_id)';
            $params['archive_group_id'] = $user['group_id'];
            $params['archive_user_id'] = $user['id'];
        } else {
            $where[] = 't.assigned_it_id = :archive_user_id';
            $params['archive_user_id'] = $user['id'];
        }
    }
} elseif ($scope === 'mine') {
    $where[] = 't.assigned_it_id = :current_user_id';
    $where[] = "ts.code NOT IN ('resolved','closed','cancelled')";
    $params['current_user_id'] = $user['id'];
} elseif ($scope === 'unassigned') {
    $where[] = 't.assigned_it_id IS NULL';
    $where[] = "ts.code NOT IN ('resolved','closed','cancelled')";
} elseif ($scope === 'team') {
    $where[] = "ts.code NOT IN ('resolved','closed','cancelled')";
    if ($user['role'] === 'Administrateur') {
        $where[] = 't.assigned_it_id IS NOT NULL';
    } elseif ($user['group_id'] !== null) {
        $where[] = 'assigned_it.group_id = :team_group_id';
        $params['team_group_id'] = $user['group_id'];
    } else {
        $where[] = 't.assigned_it_id = :team_user_id';
        $params['team_user_id'] = $user['id'];
    }
} elseif ($scope === 'all') {
    $where[] = "ts.code NOT IN ('resolved','closed','cancelled')";
}
if ($q !== '') {
    $term = '%' . $q . '%';
    $where[] = '(t.ticket_number LIKE :q_number OR t.title LIKE :q_title OR CONCAT(requester.firstname, " ", requester.lastname) LIKE :q_requester)';
    $params['q_number'] = $term;
    $params['q_title'] = $term;
    $params['q_requester'] = $term;
}
if ($status !== '') { $where[] = 'ts.code = :status'; $params['status'] = $status; }
if ($priority !== '') { $where[] = 'p.id = :priority'; $params['priority'] = (int) $priority; }
if ($typeId > 0) { $where[] = 'tt.id = :type_id'; $params['type_id'] = $typeId; }
if ($categoryId > 0) { $where[] = 't.category_id = :category_id'; $params['category_id'] = $categoryId; }
if ($groupId > 0) { $where[] = 'requester.group_id = :group_id'; $params['group_id'] = $groupId; }
if ($managerId > 0) { $where[] = 'requester_group.manager_id = :manager_id'; $params['manager_id'] = $managerId; }
if ($assignedIt === 'unassigned') {
    $where[] = 't.assigned_it_id IS NULL';
} elseif (ctype_digit($assignedIt) && (int) $assignedIt > 0) {
    $where[] = 't.assigned_it_id = :assigned_it_filter';
    $params['assigned_it_filter'] = (int) $assignedIt;
}
if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $where[] = 't.created_at >= :date_from';
    $params['date_from'] = $dateFrom . ' 00:00:00';
}
if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $where[] = 't.created_at <= :date_to';
    $params['date_to'] = $dateTo . ' 23:59:59';
}
if ($user['role'] === 'Administrateur') {
    if ($sla === 'response') $where[] = 't.sla_response_due_at IS NOT NULL AND t.assigned_at IS NULL AND t.sla_response_due_at < NOW()';
    elseif ($sla === 'resolution') $where[] = "t.sla_resolution_due_at IS NOT NULL AND t.resolved_at IS NULL AND ts.code NOT IN ('resolved','closed','cancelled') AND t.sla_resolution_due_at < NOW()";
    elseif ($sla === 'ok') $where[] = "NOT ((t.sla_response_due_at IS NOT NULL AND t.assigned_at IS NULL AND t.sla_response_due_at < NOW()) OR (t.sla_resolution_due_at IS NOT NULL AND t.resolved_at IS NULL AND ts.code NOT IN ('resolved','closed','cancelled') AND t.sla_resolution_due_at < NOW()))";
}

$from = ' FROM tickets t
          INNER JOIN ticket_statuses ts ON ts.id = t.status_id
          INNER JOIN priorities p ON p.id = t.priority_id
          INNER JOIN ticket_types tt ON tt.id = t.type_id
          INNER JOIN users requester ON requester.id = t.requester_id
          LEFT JOIN groups_company requester_group ON requester_group.id = requester.group_id
          LEFT JOIN ticket_categories tc ON tc.id = t.category_id
          LEFT JOIN users assigned_it ON assigned_it.id = t.assigned_it_id
          WHERE ' . implode(' AND ', $where);

$countStmt = $pdo->prepare('SELECT COUNT(*)' . $from);
$countStmt->execute($params);
$totalTickets = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalTickets / $perPage));
if ($page > $totalPages) $page = $totalPages;
$offset = ($page - 1) * $perPage;

$sql = 'SELECT t.id, t.ticket_number, t.title, t.created_at, t.assigned_at, t.resolved_at,
               t.sla_response_due_at, t.sla_resolution_due_at,
               ts.code AS status_code, ts.name AS status_name,
               p.id AS priority_id, p.name AS priority_name, p.level AS priority_level,
               tt.name AS type_name, tt.id AS type_id,
               tc.name AS category_name,
               CONCAT(requester.firstname, " ", requester.lastname) AS requester_name,
               requester_group.name AS requester_group_name,
               CONCAT(assigned_it.firstname, " ", assigned_it.lastname) AS assigned_it_name' .
        $from . ' ORDER BY p.level DESC, t.created_at ASC LIMIT ' . $perPage . ' OFFSET ' . $offset;
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$statuses = $scope === 'archive'
    ? $pdo->query("SELECT code, name FROM ticket_statuses WHERE code IN ('resolved','closed','cancelled') ORDER BY id")->fetchAll()
    : $pdo->query("SELECT code, name FROM ticket_statuses WHERE code NOT IN ('resolved','closed','cancelled') ORDER BY id")->fetchAll();
$priorities = $pdo->query('SELECT id, name FROM priorities ORDER BY level')->fetchAll();
$types = $pdo->query('SELECT id, code, name FROM ticket_types ORDER BY id')->fetchAll();
$categories = $pdo->query('SELECT id, name FROM ticket_categories WHERE active = 1 ORDER BY name')->fetchAll();
$groups = $pdo->query('SELECT id, name FROM groups_company WHERE active = 1 ORDER BY name')->fetchAll();
$managers = $pdo->query("SELECT u.id, u.firstname, u.lastname FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE r.name='Manager' AND u.active=1 ORDER BY u.lastname,u.firstname")->fetchAll();
$itUsers = $pdo->query("SELECT u.id, u.firstname, u.lastname FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE r.name='IT' AND u.active=1 ORDER BY u.lastname,u.firstname")->fetchAll();

$bulkStatusCodes = $user['role'] === 'Administrateur'
    ? ['new','assigned','in_progress','waiting_user','waiting_manager','waiting_confirmation','closed','cancelled']
    : ['assigned','in_progress','waiting_user','cancelled'];
$bulkStatusPlaceholders = implode(',', array_fill(0, count($bulkStatusCodes), '?'));
$bulkStatusStmt = $pdo->prepare("SELECT code, name FROM ticket_statuses WHERE code IN ($bulkStatusPlaceholders) ORDER BY id");
$bulkStatusStmt->execute($bulkStatusCodes);
$bulkStatuses = $bulkStatusStmt->fetchAll();

$paginationUrl = static function (int $targetPage) use ($scope, $q, $status, $priority, $sla, $user, $typeId, $categoryId, $groupId, $managerId, $assignedIt, $dateFrom, $dateTo, $perPage): string {
    $query = ['scope' => $scope, 'page' => $targetPage, 'per_page' => $perPage];
    if ($q !== '') $query['q'] = $q;
    if ($status !== '') $query['status'] = $status;
    if ($priority !== '') $query['priority'] = $priority;
    if ($typeId > 0) $query['type_id'] = $typeId;
    if ($categoryId > 0) $query['category_id'] = $categoryId;
    if ($groupId > 0) $query['group_id'] = $groupId;
    if ($managerId > 0) $query['manager_id'] = $managerId;
    if ($assignedIt !== '') $query['assigned_it'] = $assignedIt;
    if ($dateFrom !== '') $query['date_from'] = $dateFrom;
    if ($dateTo !== '') $query['date_to'] = $dateTo;
    if ($user['role'] === 'Administrateur' && $sla !== '') $query['sla'] = $sla;
    return 'it-tickets.php?' . http_build_query($query);
};


$savedViews = $savedTicketViewService->listForUser((int) $user['id']);
$flashSuccess = $_SESSION['flash_success'] ?? null;
$flashError = $_SESSION['flash_error'] ?? null;
unset($_SESSION['flash_success'], $_SESSION['flash_error']);
$currentQueryForReturn = http_build_query(array_filter([
    'scope' => $scope,
    'q' => $q,
    'status' => $status,
    'priority' => $priority,
    'type_id' => $typeId ?: null,
    'category_id' => $categoryId ?: null,
    'group_id' => $groupId ?: null,
    'manager_id' => $managerId ?: null,
    'assigned_it' => $assignedIt,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'sla' => $sla,
    'per_page' => $perPage,
    'page' => $page > 1 ? $page : null,
], static fn($v) => $v !== null && $v !== ''));

require __DIR__ . '/../templates/shared/header.php';

$typeIcons = [
    'Incident' => 'fa-triangle-exclamation',
    'Requête' => 'fa-clipboard-list',
    "Demande d'accès" => 'fa-key',
    'Changement' => 'fa-arrows-rotate',
];
?>
<section class="page-heading ticket-queue-heading">
    <div>
        <span class="badge"><i class="fa-solid <?= $user['role'] === 'Administrateur' ? 'fa-shield-halved' : 'fa-screwdriver-wrench' ?>"></i> <?= htmlspecialchars($user['role'] === 'Administrateur' ? t('common.administration') : t('role.IT')) ?></span>
        <h1><?= htmlspecialchars($scope === 'archive' ? t('queue.archive_title') : t('queue.title')) ?></h1>
        <p><?= htmlspecialchars($scope === 'archive' ? t('queue.archive_subtitle') : t('queue.subtitle')) ?></p>
    </div>
    <div class="queue-summary"><i class="fa-solid fa-ticket"></i><div><strong><?= $totalTickets ?></strong><span><?= htmlspecialchars(t('queue.ticket_count',['count'=>$totalTickets])) ?></span></div></div>
</section>
<?php if ($flashSuccess): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($flashSuccess) ?></div><?php endif; ?>
<?php if ($flashError): ?><div class="alert error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($flashError) ?></div><?php endif; ?>

<section class="panel saved-views-panel-v0151">
    <div class="saved-views-header-v0151">
        <div><h2><i class="fa-solid fa-bookmark"></i> <?= htmlspecialchars(t('queue.saved_views')) ?></h2><p><?= htmlspecialchars(t('queue.saved_views_help')) ?></p></div>
        <form method="post" class="saved-view-create-v0151">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
            <input type="hidden" name="view_action" value="save">
            <input type="hidden" name="return_query" value="<?= htmlspecialchars($currentQueryForReturn) ?>">
            <input type="hidden" name="scope" value="<?= htmlspecialchars($scope) ?>">
            <input type="hidden" name="q" value="<?= htmlspecialchars($q) ?>">
            <input type="hidden" name="status" value="<?= htmlspecialchars($status) ?>">
            <input type="hidden" name="priority" value="<?= htmlspecialchars($priority) ?>">
            <input type="hidden" name="type_id" value="<?= (int) $typeId ?>">
            <input type="hidden" name="category_id" value="<?= (int) $categoryId ?>">
            <input type="hidden" name="group_id" value="<?= (int) $groupId ?>">
            <input type="hidden" name="manager_id" value="<?= (int) $managerId ?>">
            <input type="hidden" name="assigned_it" value="<?= htmlspecialchars($assignedIt) ?>">
            <input type="hidden" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
            <input type="hidden" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
            <input type="hidden" name="sla" value="<?= htmlspecialchars($sla) ?>">
            <input type="hidden" name="per_page" value="<?= (int) $perPage ?>">
            <input type="text" name="view_name" maxlength="60" placeholder="<?= htmlspecialchars(t('queue.save_view_placeholder')) ?>" required>
            <button class="btn primary small" type="submit"><i class="fa-solid fa-bookmark"></i> <?= htmlspecialchars(t('queue.save_view')) ?></button>
        </form>
    </div>
    <div class="saved-view-list-v0151">
        <?php if (!$savedViews): ?>
            <div class="saved-view-empty-v0151"><i class="fa-regular fa-bookmark"></i><span><?= htmlspecialchars(t('queue.no_saved_views')) ?></span></div>
        <?php else: ?>
            <?php foreach ($savedViews as $view): ?>
                <div class="saved-view-chip-v0151">
                    <a href="<?= htmlspecialchars((string) $view['url']) ?>"><i class="fa-solid fa-bookmark"></i><span><?= htmlspecialchars((string) $view['name']) ?></span></a>
                    <form method="post" onsubmit="return confirm('<?= htmlspecialchars(addslashes(t('queue.delete_saved_confirm'))) ?>');">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
                        <input type="hidden" name="view_action" value="delete">
                        <input type="hidden" name="view_id" value="<?= (int) $view['id'] ?>">
                        <input type="hidden" name="return_query" value="<?= htmlspecialchars($currentQueryForReturn) ?>">
                        <button type="submit" aria-label="<?= htmlspecialchars(t('queue.delete_saved_aria',['name'=>(string)$view['name']])) ?>"><i class="fa-solid fa-xmark"></i></button>
                    </form>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</section>

<div class="ticket-scope-tabs queue-tabs">
    <a class="<?= $scope === 'mine' ? 'active' : '' ?>" href="it-tickets.php?scope=mine"><i class="fa-solid fa-user-check"></i> <?= htmlspecialchars(t('queue.my_tickets')) ?></a>
    <a class="<?= $scope === 'unassigned' ? 'active' : '' ?>" href="it-tickets.php?scope=unassigned"><i class="fa-solid fa-inbox"></i> <?= htmlspecialchars(t('queue.unassigned')) ?></a>
    <a class="<?= $scope === 'team' ? 'active' : '' ?>" href="it-tickets.php?scope=team"><i class="fa-solid fa-users-gear"></i> <?= htmlspecialchars(t('queue.team')) ?></a>
    <?php if ($user['role'] === 'Administrateur'): ?><a class="<?= $scope === 'all' ? 'active' : '' ?>" href="it-tickets.php?scope=all"><i class="fa-solid fa-layer-group"></i> <?= htmlspecialchars(t('queue.all')) ?></a><?php endif; ?>
    <a class="<?= $scope === 'archive' ? 'active' : '' ?>" href="it-tickets.php?scope=archive"><i class="fa-solid fa-box-archive"></i> <?= htmlspecialchars(t('queue.archives')) ?></a>
</div>
<section class="panel queue-filter-panel queue-filter-panel-v015">
    <div class="panel-heading-inline queue-filter-heading">
        <div><h2><i class="fa-solid fa-sliders"></i> <?= htmlspecialchars(t('queue.filters')) ?></h2><p><?= htmlspecialchars(t('queue.filters_help')) ?></p></div>
        <button class="btn secondary small" type="button" data-advanced-filter-toggle><i class="fa-solid fa-sliders"></i> <?= htmlspecialchars(t('queue.advanced')) ?></button>
    </div>
    <form class="ticket-filter-form-v015" method="get">
        <input type="hidden" name="scope" value="<?= htmlspecialchars($scope) ?>">
        <div class="ticket-filter-main-v015">
            <label class="search-field"><i class="fa-solid fa-magnifying-glass"></i><input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="<?= htmlspecialchars(t('queue.search_placeholder')) ?>"></label>
            <select name="status"><option value=""><?= htmlspecialchars(t('queue.all_statuses')) ?></option><?php foreach ($statuses as $item): ?><option value="<?= htmlspecialchars($item['code']) ?>" <?= $status === $item['code'] ? 'selected' : '' ?>><?= htmlspecialchars(t('status.'.$item['code'])) ?></option><?php endforeach; ?></select>
            <select name="priority"><option value=""><?= htmlspecialchars(t('queue.all_priorities')) ?></option><?php foreach ($priorities as $item): ?><option value="<?= (int)$item['id'] ?>" <?= (string)$item['id'] === $priority ? 'selected' : '' ?>><?= htmlspecialchars(t('priority.'.strtolower($item['name']))) ?></option><?php endforeach; ?></select>
            <button class="btn primary" type="submit"><i class="fa-solid fa-filter"></i> <?= htmlspecialchars(t('common.filter')) ?></button>
            <a class="btn ghost" href="it-tickets.php?scope=<?= urlencode($scope) ?>"><i class="fa-solid fa-rotate-left"></i> <?= htmlspecialchars(t('common.reset')) ?></a>
        </div>
        <div class="advanced-filter-grid-v015" data-advanced-filter-panel <?= ($typeId || $categoryId || $groupId || $managerId || $assignedIt !== '' || $dateFrom !== '' || $dateTo !== '' || $sla !== '') ? '' : 'hidden' ?>>
            <div class="field"><label><?= htmlspecialchars(t('queue.type')) ?></label><select name="type_id"><option value="0"><?= htmlspecialchars(t('queue.all_types')) ?></option><?php foreach ($types as $item): ?><option value="<?= (int)$item['id'] ?>" <?= $typeId === (int)$item['id'] ? 'selected' : '' ?>><?= htmlspecialchars($item['code'].' — '.$item['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label><?= htmlspecialchars(t('queue.category')) ?></label><select name="category_id"><option value="0"><?= htmlspecialchars(t('queue.all_categories')) ?></option><?php foreach ($categories as $item): ?><option value="<?= (int)$item['id'] ?>" <?= $categoryId === (int)$item['id'] ? 'selected' : '' ?>><?= htmlspecialchars($item['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label><?= htmlspecialchars(t('queue.requester_group')) ?></label><select name="group_id"><option value="0"><?= htmlspecialchars(t('queue.all_groups')) ?></option><?php foreach ($groups as $item): ?><option value="<?= (int)$item['id'] ?>" <?= $groupId === (int)$item['id'] ? 'selected' : '' ?>><?= htmlspecialchars($item['name']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label><?= htmlspecialchars(t('common.manager')) ?></label><select name="manager_id"><option value="0"><?= htmlspecialchars(t('queue.all_managers')) ?></option><?php foreach ($managers as $item): ?><option value="<?= (int)$item['id'] ?>" <?= $managerId === (int)$item['id'] ? 'selected' : '' ?>><?= htmlspecialchars($item['firstname'].' '.$item['lastname']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label><?= htmlspecialchars(t('queue.assigned_it')) ?></label><select name="assigned_it"><option value=""><?= htmlspecialchars(t('queue.all_it')) ?></option><option value="unassigned" <?= $assignedIt === 'unassigned' ? 'selected' : '' ?>><?= htmlspecialchars(t('queue.not_assigned')) ?></option><?php foreach ($itUsers as $item): ?><option value="<?= (int)$item['id'] ?>" <?= $assignedIt === (string)$item['id'] ? 'selected' : '' ?>><?= htmlspecialchars($item['firstname'].' '.$item['lastname']) ?></option><?php endforeach; ?></select></div>
            <?php if ($user['role'] === 'Administrateur' && $scope !== 'archive'): ?><div class="field"><label>SLA</label><select name="sla"><option value=""><?= htmlspecialchars(t('queue.sla_all')) ?></option><option value="response" <?= $sla === 'response' ? 'selected' : '' ?>><?= htmlspecialchars(t('queue.sla_response')) ?></option><option value="resolution" <?= $sla === 'resolution' ? 'selected' : '' ?>><?= htmlspecialchars(t('queue.sla_resolution')) ?></option><option value="ok" <?= $sla === 'ok' ? 'selected' : '' ?>><?= htmlspecialchars(t('queue.sla_ok')) ?></option></select></div><?php endif; ?>
            <div class="field"><label><?= htmlspecialchars(t('queue.created_from')) ?></label><input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>"></div>
            <div class="field"><label><?= htmlspecialchars(t('queue.created_to')) ?></label><input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>"></div>
            <div class="field"><label><?= htmlspecialchars(t('queue.per_page')) ?></label><select name="per_page"><option value="15" <?= $perPage === 15 ? 'selected' : '' ?>>15</option><option value="30" <?= $perPage === 30 ? 'selected' : '' ?>>30</option><option value="50" <?= $perPage === 50 ? 'selected' : '' ?>>50</option></select></div>
        </div>
    </form>
</section>
<section class="panel table-panel ticket-queue-panel">
    <div class="table-header"><div><h2><?= htmlspecialchars(t('queue.tickets')) ?></h2><p class="muted"><?= htmlspecialchars(t('queue.results',['count'=>$totalTickets])) ?></p></div><span class="queue-page-chip"><i class="fa-regular fa-file-lines"></i> <?= htmlspecialchars(t('queue.page',['page'=>$page,'pages'=>$totalPages])) ?></span></div>
    <?php if (!$tickets): ?>
        <div class="empty-state queue-empty"><i class="fa-regular fa-folder-open"></i><strong><?= htmlspecialchars(t('queue.no_ticket')) ?></strong><span><?= htmlspecialchars(t('queue.no_ticket_help')) ?></span></div>
    <?php else: ?>
        <?php if ($scope !== 'archive'): ?>
        <form method="post" class="bulk-ticket-form-v0152" data-bulk-ticket-form>
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
            <input type="hidden" name="return_query" value="<?= htmlspecialchars($currentQueryForReturn) ?>">
            <div class="bulk-toolbar-v0152">
                <label class="bulk-select-all-v0152"><input type="checkbox" data-select-all-tickets> <span><?= htmlspecialchars(t('queue.select_page')) ?></span></label>
                <span class="bulk-count-v0152" data-bulk-count><?= htmlspecialchars(t('queue.selected',['count'=>0])) ?></span>
                <div class="bulk-actions-v0152">
                    <select name="bulk_action" data-bulk-action required>
                        <option value=""><?= htmlspecialchars(t('queue.bulk_action')) ?></option>
                        <option value="priority"><?= htmlspecialchars(t('queue.change_priority')) ?></option>
                        <option value="status"><?= htmlspecialchars(t('queue.change_status')) ?></option>
                        <option value="assign"><?= htmlspecialchars(t('queue.assign_transfer')) ?></option>
                    </select>
                    <select name="bulk_priority_id" data-bulk-field="priority" hidden>
                        <option value=""><?= htmlspecialchars(t('queue.choose_priority')) ?></option>
                        <?php foreach ($priorities as $item): ?><option value="<?= (int) $item['id'] ?>"><?= htmlspecialchars(t('priority.'.strtolower($item['name']))) ?></option><?php endforeach; ?>
                    </select>
                    <select name="bulk_status_code" data-bulk-field="status" hidden>
                        <option value=""><?= htmlspecialchars(t('queue.choose_status')) ?></option>
                        <?php foreach ($bulkStatuses as $item): ?><option value="<?= htmlspecialchars($item['code']) ?>"><?= htmlspecialchars(t('status.'.$item['code'])) ?></option><?php endforeach; ?>
                    </select>
                    <select name="bulk_it_id" data-bulk-field="assign" hidden>
                        <option value=""><?= htmlspecialchars(t('queue.choose_it')) ?></option>
                        <?php foreach ($itUsers as $item): ?><option value="<?= (int) $item['id'] ?>"><?= htmlspecialchars($item['firstname'].' '.$item['lastname']) ?></option><?php endforeach; ?>
                    </select>
                    <button class="btn primary small" type="submit" data-bulk-submit disabled><i class="fa-solid fa-wand-magic-sparkles"></i> <?= htmlspecialchars(t('queue.apply')) ?></button>
                </div>
            </div>
            <div class="ticket-queue-list ticket-queue-list-selectable-v0152">
        <?php else: ?>
            <div class="ticket-queue-list">
        <?php endif; ?>
        <?php foreach ($tickets as $ticket):
            $typeIcon = $typeIcons[$ticket['type_name']] ?? 'fa-ticket';
            $responseOver = $ticket['sla_response_due_at'] !== null && $ticket['assigned_at'] === null && strtotime((string)$ticket['sla_response_due_at']) < time();
            $resolutionOver = $ticket['sla_resolution_due_at'] !== null && $ticket['resolved_at'] === null && !in_array($ticket['status_code'], ['resolved','closed','cancelled'], true) && strtotime((string)$ticket['sla_resolution_due_at']) < time();
        ?>
            <article class="ticket-queue-item <?= $scope !== 'archive' ? 'has-selection-v0152' : '' ?> priority-border-<?= (int)$ticket['priority_level'] ?>">
                <?php if ($scope !== 'archive'): ?><label class="ticket-select-v0152" title="<?= htmlspecialchars(t('queue.select_ticket')) ?>"><input type="checkbox" name="ticket_ids[]" value="<?= (int) $ticket['id'] ?>" data-ticket-checkbox><span></span></label><?php endif; ?>
                <div class="queue-type-icon"><i class="fa-solid <?= htmlspecialchars($typeIcon) ?>"></i></div>
                <div class="queue-ticket-main">
                    <div class="queue-ticket-topline"><strong><?= htmlspecialchars($ticket['ticket_number']) ?></strong><span><?= htmlspecialchars($ticket['type_name']) ?></span></div>
                    <h3><?= htmlspecialchars($ticket['title']) ?></h3>
                    <div class="queue-ticket-meta">
                        <span><i class="fa-regular fa-user"></i> <?= htmlspecialchars($ticket['requester_name']) ?></span>
                        <span><i class="fa-solid fa-people-group"></i> <?= htmlspecialchars($ticket['requester_group_name'] ?: t('queue.no_group')) ?></span>
                        <span><i class="fa-solid fa-tag"></i> <?= htmlspecialchars($ticket['category_name'] ?: t('queue.no_category')) ?></span>
                        <span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars(date('d/m/Y H:i', strtotime($ticket['created_at']))) ?></span>
                    </div>
                </div>
                <div class="queue-ticket-badges">
                    <span class="priority-pill priority-level-<?= (int)$ticket['priority_level'] ?>"><i class="fa-solid fa-flag"></i> <?= htmlspecialchars(t('priority.'.strtolower($ticket['priority_name']))) ?></span>
                    <span class="ticket-status status-<?= htmlspecialchars($ticket['status_code']) ?>"><i class="fa-solid fa-circle-dot"></i> <?= htmlspecialchars(t('status.'.$ticket['status_code'])) ?></span>
                    <?php if ($user['role'] === 'Administrateur' && $scope !== 'archive'): ?>
                        <span class="sla-pill <?= ($resolutionOver || $responseOver) ? 'sla-breached' : 'sla-ok' ?>"><i class="fa-solid <?= ($resolutionOver || $responseOver) ? 'fa-clock-rotate-left' : 'fa-clock' ?>"></i> <?= htmlspecialchars($resolutionOver ? t('queue.sla_resolution') : ($responseOver ? t('queue.sla_response') : t('queue.sla_ok'))) ?></span>
                    <?php endif; ?>
                </div>
                <div class="queue-assignee"><span><?= htmlspecialchars(t('queue.assigned_it')) ?></span><strong><i class="fa-solid fa-headset"></i> <?= htmlspecialchars($ticket['assigned_it_name'] ?: t('queue.not_assigned')) ?></strong></div>
                <a class="btn secondary queue-open-btn" href="ticket.php?number=<?= urlencode($ticket['ticket_number']) ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i> <?= htmlspecialchars(t('queue.open')) ?></a>
            </article>
        <?php endforeach; ?>
        </div>
        <?php if ($scope !== 'archive'): ?></form><?php endif; ?>
    <?php endif; ?>
    <?php if ($totalPages > 1): ?><nav class="pagination" aria-label="<?= htmlspecialchars(t('queue.pagination')) ?>">
        <a class="pagination-link <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page > 1 ? htmlspecialchars($paginationUrl($page - 1)) : '#' ?>"><i class="fa-solid fa-chevron-left"></i></a>
        <?php for ($i=1; $i <= $totalPages; $i++): ?><?php if ($i === 1 || $i === $totalPages || abs($i-$page) <= 2): ?><a class="pagination-link <?= $i === $page ? 'active' : '' ?>" href="<?= htmlspecialchars($paginationUrl($i)) ?>"><?= $i ?></a><?php elseif (($i === 2 && $page > 4) || ($i === $totalPages-1 && $page < $totalPages-3)): ?><span class="pagination-ellipsis">…</span><?php endif; ?><?php endfor; ?>
        <a class="pagination-link <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= $page < $totalPages ? htmlspecialchars($paginationUrl($page + 1)) : '#' ?>"><i class="fa-solid fa-chevron-right"></i></a>
    </nav><?php endif; ?>
</section>
<script src="assets/js/bulk-tickets.js?v=0.15.2.1" defer></script>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
