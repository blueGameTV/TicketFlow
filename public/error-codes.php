<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use App\Support\ErrorCatalog;

$auth->requireRole('Administrateur', 'IT');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = t('nav.error_codes');

$catalog = ErrorCatalog::all();
$q = trim((string)($_GET['q'] ?? ''));

if ($q !== '') {
    $needle = mb_strtolower($q);
    $catalog = array_filter(
        $catalog,
        static fn(array $row, string $code): bool =>
            str_contains(mb_strtolower($code), $needle)
            || str_contains(mb_strtolower((string)$row['title']), $needle)
            || str_contains(mb_strtolower((string)$row['area']), $needle)
            || str_contains(mb_strtolower((string)$row['admin']), $needle),
        ARRAY_FILTER_USE_BOTH
    );
}

require __DIR__ . '/../templates/shared/header.php';
?>
<section class="page-heading page-heading-enhanced te-heading">
    <div>
        <span class="badge"><i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars(t('error_codes.diagnostic')) ?></span>
        <h1><?= htmlspecialchars(t('error_codes.title')) ?></h1>
        <p><strong>TE</strong> = <strong>Technical Error</strong>. <?= htmlspecialchars(t('error_codes.description')) ?></p>
    </div>
</section>

<section class="panel te-search-panel">
    <form method="get" class="te-search-form">
        <div class="search-field">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="TE-201, ticket, database…">
        </div>
        <button class="btn primary"><?= htmlspecialchars(t('common.search')) ?></button>
        <?php if ($q !== ''): ?><a class="btn secondary" href="error-codes.php"><?= htmlspecialchars(t('common.reset')) ?></a><?php endif; ?>
    </form>
</section>

<section class="te-code-list">
<?php foreach ($catalog as $code => $row): ?>
    <article class="panel te-code-card">
        <div class="te-code-id"><?= htmlspecialchars($code) ?></div>
        <div class="te-code-copy">
            <span class="te-area"><?= htmlspecialchars((string)$row['area']) ?></span>
            <h2><?= htmlspecialchars((string)$row['title']) ?></h2>
            <div class="te-code-grid">
                <div>
                    <span><?= htmlspecialchars(t('error_codes.user_message')) ?></span>
                    <p><?= htmlspecialchars((string)$row['public']) ?></p>
                </div>
                <div>
                    <span><?= htmlspecialchars(t('error_codes.admin_diagnostic')) ?></span>
                    <p><?= htmlspecialchars((string)$row['admin']) ?></p>
                </div>
            </div>
        </div>
    </article>
<?php endforeach; ?>
</section>

<section class="panel te-log-hint">
    <i class="fa-solid fa-file-lines"></i>
    <div>
        <strong><?= htmlspecialchars(t('error_codes.technical_log')) ?></strong>
        <p><code>storage/logs/technical-errors.log</code></p>
    </div>
</section>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
