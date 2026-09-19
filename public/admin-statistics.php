<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireRole('Administrateur');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'Statistiques';

$days = filter_input(INPUT_GET, 'days', FILTER_VALIDATE_INT) ?: 30;
if (!in_array($days, [7, 30, 90, 180], true)) {
    $days = 30;
}

$stats = $adminStatisticsService->dashboard($days);

function formatDuration(?int $minutes): string
{
    if ($minutes === null) {
        return '—';
    }
    if ($minutes < 60) {
        return $minutes . ' min';
    }
    $hours = intdiv($minutes, 60);
    $remaining = $minutes % 60;
    if ($hours < 24) {
        return $hours . ' h' . ($remaining > 0 ? ' ' . $remaining . ' min' : '');
    }
    $days = intdiv($hours, 24);
    $hours = $hours % 24;
    return $days . ' j' . ($hours > 0 ? ' ' . $hours . ' h' : '');
}

function renderDistribution(array $items): void
{
    $max = 0;
    foreach ($items as $item) {
        $max = max($max, (int) $item['total']);
    }
    if ($max === 0) {
        echo '<p class="empty-state">Aucune donnée sur cette période.</p>';
        return;
    }
    echo '<div class="stat-bars">';
    foreach ($items as $item) {
        $total = (int) $item['total'];
        $width = max(3, (int) round(($total / $max) * 100));
        echo '<div class="stat-bar-row">';
        echo '<div class="stat-bar-label"><span>' . htmlspecialchars((string) $item['label']) . '</span><strong>' . $total . '</strong></div>';
        echo '<div class="stat-bar-track"><span style="width:' . $width . '%"></span></div>';
        echo '</div>';
    }
    echo '</div>';
}

require __DIR__ . '/../templates/shared/header.php';
?>
<section class="dashboard-heading statistics-heading statistics-hero page-heading-enhanced">
    <div>
        <span class="badge"><i class="fa-solid fa-chart-pie"></i> Administration</span>
        <h1>Statistiques TicketFlow</h1>
        <p>Suivi de l'activité et de la charge du support.</p>
    </div>
    <form method="get" class="period-selector">
        <label for="days">Période</label>
        <select name="days" id="days" onchange="this.form.submit()">
            <option value="7" <?= $days === 7 ? 'selected' : '' ?>>7 jours</option>
            <option value="30" <?= $days === 30 ? 'selected' : '' ?>>30 jours</option>
            <option value="90" <?= $days === 90 ? 'selected' : '' ?>>90 jours</option>
            <option value="180" <?= $days === 180 ? 'selected' : '' ?>>6 mois</option>
        </select>
    </form>
</section>

<p class="statistics-period">Du <strong><?= htmlspecialchars(date('d/m/Y', strtotime($stats['start']))) ?></strong> au <strong><?= htmlspecialchars(date('d/m/Y', strtotime($stats['end']))) ?></strong>.</p>

<section class="stats-kpi-grid stats-kpi-grid-v013">
    <article class="stat-kpi"><div class="stat-kpi-icon"><i class="fa-solid fa-plus"></i></div><span><?= $stats['summary']['created'] ?></span><h2>Tickets créés</h2><p>Sur la période sélectionnée.</p></article>
    <article class="stat-kpi"><div class="stat-kpi-icon"><i class="fa-solid fa-circle-check"></i></div><span><?= $stats['summary']['resolved'] ?></span><h2>Tickets résolus</h2><p>Confirmés par les demandeurs.</p></article>
    <article class="stat-kpi"><div class="stat-kpi-icon"><i class="fa-solid fa-ticket"></i></div><span><?= $stats['summary']['open'] ?></span><h2>Tickets ouverts</h2><p>État actuel de la plateforme.</p></article>
    <article class="stat-kpi"><div class="stat-kpi-icon"><i class="fa-solid fa-clock"></i></div><span><?= htmlspecialchars(formatDuration($stats['summary']['avg_resolution_minutes'])) ?></span><h2>Temps moyen</h2><p>Création → confirmation de résolution.</p></article>
</section>

<section class="stats-alert-grid">
    <a href="it-tickets.php?scope=unassigned" class="stats-alert"><strong><?= $stats['summary']['unassigned'] ?></strong><span>Tickets non attribués</span></a>
    <a href="it-tickets.php?scope=all" class="stats-alert"><strong><?= $stats['summary']['critical_open'] ?></strong><span>Tickets critiques ouverts</span></a>
    <a href="it-tickets.php?scope=all" class="stats-alert"><strong><?= $stats['summary']['pending_manager'] ?></strong><span>Validations Manager en attente</span></a>
    <a href="it-tickets.php?scope=all&sla=response" class="stats-alert"><strong><?= $stats['summary']['sla_response_breached'] ?></strong><span>SLA prise en charge dépassé</span></a>
    <a href="it-tickets.php?scope=all&sla=resolution" class="stats-alert"><strong><?= $stats['summary']['sla_resolution_breached'] ?></strong><span>SLA résolution dépassé</span></a>
    <a href="admin-users.php" class="stats-alert"><strong><?= $stats['summary']['active_users'] ?></strong><span>Utilisateurs actifs</span></a>
</section>

<section class="panel chart-panel">
    <div class="panel-heading-inline">
        <div><h2>Évolution des tickets</h2><p>Tickets créés et résolus chaque jour.</p></div>
        <div class="chart-legend"><span><i class="legend-created"></i>Créés</span><span><i class="legend-resolved"></i>Résolus</span></div>
    </div>
    <div class="chart-wrap"><canvas id="ticketTrendChart" height="300"></canvas></div>
</section>

<section class="statistics-grid">
    <article class="panel distribution-panel"><h2><i class="fa-solid fa-list-check"></i> Par statut</h2><?php renderDistribution($stats['statuses']); ?></article>
    <article class="panel distribution-panel"><h2><i class="fa-solid fa-flag"></i> Par importance</h2><?php renderDistribution($stats['priorities']); ?></article>
    <article class="panel distribution-panel"><h2><i class="fa-solid fa-shapes"></i> Par type</h2><?php renderDistribution($stats['types']); ?></article>
    <article class="panel distribution-panel"><h2><i class="fa-solid fa-people-group"></i> Par groupe</h2><?php renderDistribution($stats['groups']); ?></article>
</section>

<script id="ticketTrendData" type="application/json"><?= json_encode($stats['timeline'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="assets/js/admin-dashboard.js?v=0.15.2.1"></script>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
