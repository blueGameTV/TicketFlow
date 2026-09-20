<?php

declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$auth->requireLogin();
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'À propos';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $csrf->validate($_POST['csrf_token'] ?? null)) {
    $releaseNoteService->markSeen((int)$user['id']);
    $_SESSION['flash_success'] = 'Les nouveautés ont été marquées comme consultées.';
    header('Location: about.php#nouveautes');
    exit;
}
$success = $_SESSION['flash_success'] ?? null;
unset($_SESSION['flash_success']);
$version = $releaseNoteService->currentVersion();
$releases = $releaseNoteService->releases();
$seen = $releaseNoteService->hasSeen((int)$user['id']);
require __DIR__ . '/../templates/shared/header.php';
?>
<section class="v110-about-hero">
    <div class="v110-about-hero-main">
        <div class="v110-about-logo"><i class="fa-solid fa-ticket"></i></div>
        <div>
            <span class="v110-about-eyebrow">TicketFlow</span>
            <h1>À propos & Nouveautés</h1>
            <p>Informations sur votre installation et historique des évolutions de la plateforme.</p>
        </div>
    </div>
    <div class="v110-about-version-card">
        <span>Version installée</span>
        <strong><?= htmlspecialchars($version) ?></strong>
        <small>Version stable actuelle</small>
    </div>
</section>

<?php if ($success): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

<section class="v110-about-facts">
    <article><i class="fa-solid fa-user-gear"></i><div><span>Créateur</span><strong>blueGameTV</strong></div></article>
    <article><i class="fa-solid fa-code"></i><div><span>Technologies</span><strong>PHP · MariaDB · Apache · JavaScript</strong></div></article>
    <article><i class="fa-solid fa-shield-halved"></i><div><span>Version précédente</span><strong>v1.0.0</strong></div></article>
    <article><i class="fa-solid fa-circle-check"></i><div><span>Statut actuel</span><strong>Stable v1.1.0</strong></div></article>
</section>

<section class="panel v110-about-journal" id="nouveautes">
    <div class="v110-about-journal-top">
        <div class="v110-about-journal-title">
            <span class="panel-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></span>
            <div><h2>Journal des nouveautés</h2><p>Retrouvez les changements apportés à chaque version de TicketFlow.</p></div>
        </div>
        <?php if (!$seen): ?>
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
                <button class="btn primary" type="submit"><i class="fa-solid fa-check"></i> J’ai vu les nouveautés</button>
            </form>
        <?php else: ?>
            <span class="v110-about-seen"><i class="fa-solid fa-circle-check"></i> Nouveautés consultées</span>
        <?php endif; ?>
    </div>

    <div class="v110-release-list">
        <?php foreach ($releases as $release): $current = $release['version'] === $version; ?>
            <article class="v110-release-row <?= $current ? 'is-current' : '' ?>">
                <div class="v110-release-rail"><span><i class="fa-solid <?= $current ? 'fa-star' : 'fa-code-commit' ?>"></i></span></div>
                <div class="v110-release-row-body">
                    <div class="v110-release-row-head">
                        <div>
                            <div class="v110-release-kicker">v<?= htmlspecialchars((string)$release['version']) ?></div>
                            <h3><?= htmlspecialchars((string)$release['title']) ?></h3>
                        </div>
                        <div class="v110-release-tags">
                            <span><?= htmlspecialchars((string)$release['date']) ?></span>
                            <?php if ($current): ?><span class="current">Version actuelle</span><?php endif; ?>
                        </div>
                    </div>
                    <ul><?php foreach ($release['items'] as $item): ?><li><i class="fa-solid fa-check"></i><span><?= htmlspecialchars((string)$item) ?></span></li><?php endforeach; ?></ul>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</section>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
