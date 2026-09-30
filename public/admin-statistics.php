<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireRole('Administrateur');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = t('nav.statistics');

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
        echo '<p class="empty-state">'.htmlspecialchars(t('stats.no_data')).'</p>';
        return;
    }
    echo '<div class="stat-bars">';
    foreach ($items as $item) {
        $total = (int) $item['total'];
        $width = max(3, (int) round(($total / $max) * 100));
        echo '<div class="stat-bar-row">';
        
        $rawLabel = (string)$item['label'];
        $labelMap = ['Nouveau'=>'status.new','Attribué'=>'status.assigned','En cours'=>'status.in_progress','Attente validation Manager'=>'status.waiting_manager','Attente confirmation utilisateur'=>'status.waiting_confirmation','Résolu'=>'status.resolved','Fermé'=>'status.closed','Annulé'=>'status.cancelled','Faible'=>'priority.faible','Normale'=>'priority.normale','Haute'=>'priority.haute','Critique'=>'priority.critique'];
        $displayLabel = isset($labelMap[$rawLabel]) ? t($labelMap[$rawLabel]) : $rawLabel;
        echo '<div class="stat-bar-label"><span>' . htmlspecialchars($displayLabel) . '</span><strong>' . $total . '</strong></div>';
        echo '<div class="stat-bar-track"><span style="width:' . $width . '%"></span></div>';
        echo '</div>';
    }
    echo '</div>';
}

require __DIR__ . '/../templates/shared/header.php';
?>
<section class="dashboard-heading statistics-heading statistics-hero page-heading-enhanced">
    <div>
        <span class="badge"><i class="fa-solid fa-chart-pie"></i> <?= htmlspecialchars(t('common.administration')) ?></span>
        <h1><?= htmlspecialchars(t('stats.title')) ?></h1>
        <p><?= htmlspecialchars(t('stats.subtitle')) ?></p>
    </div>
    <form method="get" class="period-selector">
        <label for="days"><?= htmlspecialchars(t('stats.period')) ?></label>
        <select name="days" id="days" onchange="this.form.submit()">
            <option value="7" <?= $days === 7 ? 'selected' : '' ?>><?= htmlspecialchars(t('stats.days',['count'=>7])) ?></option>
            <option value="30" <?= $days === 30 ? 'selected' : '' ?>><?= htmlspecialchars(t('stats.days',['count'=>30])) ?></option>
            <option value="90" <?= $days === 90 ? 'selected' : '' ?>><?= htmlspecialchars(t('stats.days',['count'=>90])) ?></option>
            <option value="180" <?= $days === 180 ? 'selected' : '' ?>><?= htmlspecialchars(t('stats.months6')) ?></option>
        </select>
    </form>
</section>

<p class="statistics-period"><?= htmlspecialchars(t('stats.range',['from'=>date('d/m/Y', strtotime($stats['start'])),'to'=>date('d/m/Y', strtotime($stats['end']))])) ?></p>

<section class="stats-kpi-grid stats-kpi-grid-v013">
    <article class="stat-kpi"><div class="stat-kpi-icon"><i class="fa-solid fa-plus"></i></div><span><?= $stats['summary']['created'] ?></span><h2><?= htmlspecialchars(t('stats.created')) ?></h2><p><?= htmlspecialchars(t('stats.created_help')) ?></p></article>
    <article class="stat-kpi"><div class="stat-kpi-icon"><i class="fa-solid fa-circle-check"></i></div><span><?= $stats['summary']['resolved'] ?></span><h2><?= htmlspecialchars(t('stats.resolved')) ?></h2><p><?= htmlspecialchars(t('stats.resolved_help')) ?></p></article>
    <article class="stat-kpi"><div class="stat-kpi-icon"><i class="fa-solid fa-ticket"></i></div><span><?= $stats['summary']['open'] ?></span><h2><?= htmlspecialchars(t('stats.open')) ?></h2><p><?= htmlspecialchars(t('stats.open_help')) ?></p></article>
    <article class="stat-kpi"><div class="stat-kpi-icon"><i class="fa-solid fa-clock"></i></div><span><?= htmlspecialchars(formatDuration($stats['summary']['avg_resolution_minutes'])) ?></span><h2><?= htmlspecialchars(t('stats.average')) ?></h2><p><?= htmlspecialchars(t('stats.average_help')) ?></p></article>
</section>

<section class="stats-alert-grid">
    <a href="it-tickets.php?scope=unassigned" class="stats-alert"><strong><?= $stats['summary']['unassigned'] ?></strong><span><?= htmlspecialchars(t('stats.unassigned')) ?></span></a>
    <a href="it-tickets.php?scope=all" class="stats-alert"><strong><?= $stats['summary']['critical_open'] ?></strong><span><?= htmlspecialchars(t('stats.critical_open')) ?></span></a>
    <a href="it-tickets.php?scope=all" class="stats-alert"><strong><?= $stats['summary']['pending_manager'] ?></strong><span><?= htmlspecialchars(t('stats.pending_manager')) ?></span></a>
    <a href="it-tickets.php?scope=all&sla=response" class="stats-alert"><strong><?= $stats['summary']['sla_response_breached'] ?></strong><span><?= htmlspecialchars(t('stats.sla_response')) ?></span></a>
    <a href="it-tickets.php?scope=all&sla=resolution" class="stats-alert"><strong><?= $stats['summary']['sla_resolution_breached'] ?></strong><span><?= htmlspecialchars(t('stats.sla_resolution')) ?></span></a>
    <a href="admin-users.php" class="stats-alert"><strong><?= $stats['summary']['active_users'] ?></strong><span><?= htmlspecialchars(t('stats.active_users')) ?></span></a>
</section>

<section class="panel chart-panel">
    <div class="panel-heading-inline">
        <div><h2><?= htmlspecialchars(t('stats.trend')) ?></h2><p><?= htmlspecialchars(t('stats.trend_help')) ?></p></div>
        <div class="chart-legend"><span><i class="legend-created"></i><?= htmlspecialchars(t('stats.created_short')) ?></span><span><i class="legend-resolved"></i><?= htmlspecialchars(t('stats.resolved_short')) ?></span></div>
    </div>
    <div class="chart-wrap"><canvas id="ticketTrendChart" height="300"></canvas></div>
</section>

<section class="statistics-grid">
    <article class="panel distribution-panel"><h2><i class="fa-solid fa-list-check"></i> <?= htmlspecialchars(t('stats.by_status')) ?></h2><?php renderDistribution($stats['statuses']); ?></article>
    <article class="panel distribution-panel"><h2><i class="fa-solid fa-flag"></i> <?= htmlspecialchars(t('stats.by_priority')) ?></h2><?php renderDistribution($stats['priorities']); ?></article>
    <article class="panel distribution-panel"><h2><i class="fa-solid fa-shapes"></i> <?= htmlspecialchars(t('stats.by_type')) ?></h2><?php renderDistribution($stats['types']); ?></article>
    <article class="panel distribution-panel"><h2><i class="fa-solid fa-people-group"></i> <?= htmlspecialchars(t('stats.by_group')) ?></h2><?php renderDistribution($stats['groups']); ?></article>
</section>

<script id="ticketTrendData" type="application/json"><?= json_encode($stats['timeline'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="assets/js/admin-dashboard.js?v=0.15.2.1"></script>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
