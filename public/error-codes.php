<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use App\Support\ErrorCatalog;

$auth->requireRole('Administrateur', 'IT');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = 'Codes d’erreur';

$catalog = ErrorCatalog::all();
$englishErrorCatalog = [
'TE-001'=>['area'=>'System','title'=>'Missing TicketFlow configuration','public'=>'TicketFlow cannot load its configuration.','admin'=>'Check config/config.php and its permissions.'],
'TE-002'=>['area'=>'Database','title'=>'Unable to connect to the database','public'=>'TicketFlow cannot access its database.','admin'=>'Check MariaDB/MySQL, host, port, database name and SQL credentials.'],
'TE-003'=>['area'=>'Database','title'=>'SQL query error','public'=>'A database operation failed.','admin'=>'Review technical-errors.log and verify the database schema.'],
'TE-004'=>['area'=>'Database','title'=>'Incompatible migration or schema','public'=>'The TicketFlow database structure must be checked.','admin'=>'Verify migrations and the installed schema version.'],
'TE-005'=>['area'=>'System','title'=>'Unable to write to storage','public'=>'TicketFlow cannot write a required file.','admin'=>'Check permissions for storage/, logs/, cache/, sessions/ and uploads/.'],
'TE-101'=>['area'=>'Security','title'=>'Invalid session or CSRF token','public'=>'Your session or form is no longer valid.','admin'=>'Check PHP sessions and CSRF validation.'],
'TE-102'=>['area'=>'Authentication','title'=>'Authentication failed','public'=>'Authentication could not be verified.','admin'=>'Check the account and authentication logs.'],
'TE-103'=>['area'=>'Authentication','title'=>'Incompatible account state','public'=>'The account does not allow this operation.','admin'=>'Check activation, lock state and forced password change.'],
'TE-104'=>['area'=>'Security','title'=>'Insufficient permissions','public'=>'You are not allowed to perform this operation.','admin'=>'Check the user role and permission scope.'],
'TE-105'=>['area'=>'Authentication','title'=>'Password reset failed','public'=>'The password procedure could not be completed.','admin'=>'Check the reset token, expiration and account state.'],
'TE-201'=>['area'=>'Tickets','title'=>'Ticket creation failed','public'=>'The ticket could not be created.','admin'=>'Check TicketService, type, category, priority and database state.'],
'TE-202'=>['area'=>'Tickets','title'=>'Ticket update failed','public'=>'The ticket could not be updated.','admin'=>'Check the current status, workflow and update permissions.'],
'TE-203'=>['area'=>'Tickets','title'=>'Assignment or transfer failed','public'=>'The ticket could not be assigned or transferred.','admin'=>'Check the target IT Support technician and assignment rules.'],
'TE-204'=>['area'=>'Tickets','title'=>'Ticket message failed','public'=>'The message could not be added to the ticket.','admin'=>'Check ticket_messages and ticket permissions.'],
'TE-205'=>['area'=>'Workflow','title'=>'Manager approval failed','public'=>'The Manager approval could not be processed.','admin'=>'Check the N+1 Manager, final Manager, stage and manager_approvals.'],
'TE-206'=>['area'=>'Workflow','title'=>'Ticket resolution failed','public'=>'The resolution could not be saved.','admin'=>'Check ticket status and requester confirmation workflow.'],
'TE-301'=>['area'=>'Administration','title'=>'User operation failed','public'=>'The user account operation failed.','admin'=>'Check account dependencies and database constraints.'],
'TE-302'=>['area'=>'Administration','title'=>'Group operation failed','public'=>'The group operation failed.','admin'=>'Check the responsible Manager, members and group constraints.'],
'TE-303'=>['area'=>'Administration','title'=>'User import failed','public'=>'The users file could not be imported.','admin'=>'Check CSV/XLSX format, required columns and file data.'],
'TE-401'=>['area'=>'Files','title'=>'File upload failed','public'=>'The file could not be uploaded.','admin'=>'Check file size, MIME type and storage/uploads/.'],
'TE-402'=>['area'=>'Files','title'=>'Attachment unavailable','public'=>'The attachment cannot be opened.','admin'=>'Check the physical file, database record and permissions.'],
'TE-403'=>['area'=>'Exports','title'=>'Excel generation failed','public'=>'The Excel file could not be generated.','admin'=>'Check ExcelExportService, exported data and unexpected PHP output.'],
'TE-501'=>['area'=>'Email','title'=>'Email sending failed','public'=>'The TicketFlow email could not be sent.','admin'=>'Check SMTP/log transport, email queue and mail.log.'],
'TE-502'=>['area'=>'Notifications','title'=>'Notification failed','public'=>'The notification could not be created or updated.','admin'=>'Check NotificationService and user preferences.'],
'TE-601'=>['area'=>'Service','title'=>'Service alert failed','public'=>'The service alert could not be processed.','admin'=>'Check ServiceAlertService and publication dates.'],
'TE-602'=>['area'=>'Service','title'=>'Maintenance operation failed','public'=>'The maintenance operation could not be processed.','admin'=>'Check MaintenanceService, dates and maintenance configuration.'],
'TE-701'=>['area'=>'API','title'=>'TicketFlow API error','public'=>'The TicketFlow request could not be processed.','admin'=>'Check the API endpoint, session and technical-errors.log.'],
'TE-702'=>['area'=>'Live updates','title'=>'Live update failed','public'=>'Live synchronization encountered a problem.','admin'=>'Check live-* endpoints, browser requests and HTTP responses.'],
'TE-801'=>['area'=>'Installation','title'=>'Installation or setup failed','public'=>'TicketFlow installation could not be completed.','admin'=>'Check setup.php, prerequisites, database and permissions.'],
'TE-802'=>['area'=>'Update','title'=>'Update or migration failed','public'=>'The TicketFlow update could not be completed.','admin'=>'Check update.sh, source version, migrations and backup.'],
'TE-999'=>['area'=>'System','title'=>'Unexpected technical error','public'=>'An unexpected technical error occurred.','admin'=>'Search TE-999 and its reference in technical-errors.log.'],
];
if (($currentLanguage ?? 'fr') === 'en') {
    foreach ($catalog as $errorCode => &$errorRow) {
        if (isset($englishErrorCatalog[$errorCode])) {
            $errorRow = array_merge($errorRow, $englishErrorCatalog[$errorCode]);
        }
    }
    unset($errorRow);
}
$q = trim((string)($_GET['q'] ?? ''));

if ($q !== '') {
    $needle = mb_strtolower($q);
    $catalog = array_filter(
        $catalog,
        static function (array $row, string $code) use ($needle): bool {
            return str_contains(mb_strtolower($code), $needle)
                || str_contains(mb_strtolower((string)$row['title']), $needle)
                || str_contains(mb_strtolower((string)$row['area']), $needle)
                || str_contains(mb_strtolower((string)$row['admin']), $needle);
        },
        ARRAY_FILTER_USE_BOTH
    );
}

$total = count(ErrorCatalog::all());
$visible = count($catalog);

require __DIR__ . '/../templates/shared/header.php';
?>

<section class="page-heading page-heading-enhanced te-heading-v2">
    <div>
        <span class="badge"><i class="fa-solid fa-circle-question"></i> <?= htmlspecialchars(t('errors.badge')) ?></span>
        <h1><?= htmlspecialchars(t('errors.title')) ?></h1>
        <p><?= t('errors.subtitle') ?></p>
    </div>

    <div class="te-heading-stat">
        <span class="te-heading-stat-icon"><i class="fa-solid fa-code"></i></span>
        <div><strong><?= $total ?></strong><span><?= htmlspecialchars(t('errors.documented')) ?></span></div>
    </div>
</section>

<section class="panel te-intro-v2">
    <div class="te-intro-icon"><i class="fa-solid fa-circle-info"></i></div>
    <div class="te-intro-copy">
        <h2><?= htmlspecialchars(t('errors.how_title')) ?></h2>
        <p><?= t('errors.how_text') ?></p>
    </div>
    <div class="te-format-example"><strong>TE-201</strong><span><?= htmlspecialchars(t('errors.example')) ?></span></div>
</section>

<section class="panel te-search-panel-v2">
    <form method="get" class="te-search-form-v2">
        <label class="te-search-field-v2">
            <i class="fa-solid fa-magnifying-glass"></i>
            <input type="search" name="q" value="<?= htmlspecialchars($q) ?>" placeholder="<?= htmlspecialchars(t('errors.search_placeholder')) ?>" autocomplete="off">
        </label>
        <button class="btn primary" type="submit"><i class="fa-solid fa-magnifying-glass"></i> <?= htmlspecialchars(t('errors.search')) ?></button>
        <?php if ($q !== ''): ?>
            <a class="btn secondary" href="error-codes.php"><i class="fa-solid fa-arrow-rotate-left"></i> <?= htmlspecialchars(t('errors.reset')) ?></a>
        <?php endif; ?>
    </form>
    <div class="te-search-meta">
        <span><i class="fa-solid fa-list"></i> <?= $visible ?> résultat<?= $visible > 1 ? 's' : '' ?></span>
        <?php if ($q !== ''): ?><span>Recherche : <strong><?= htmlspecialchars($q) ?></strong></span><?php endif; ?>
    </div>
</section>

<section class="te-range-strip">
    <span><strong>0xx</strong>Système / BDD</span>
    <span><strong>1xx</strong>Sécurité</span>
    <span><strong>2xx</strong>Tickets</span>
    <span><strong>3xx</strong>Administration</span>
    <span><strong>4xx</strong>Fichiers</span>
    <span><strong>5xx</strong>Notifications</span>
    <span><strong>6xx</strong>Service</span>
    <span><strong>7xx</strong>API / Live</span>
    <span><strong>8xx</strong>Installation</span>
    <span><strong>9xx</strong>Inattendu</span>
</section>

<section class="te-code-list-v2">
    <?php if (!$catalog): ?>
        <article class="panel te-empty-v2">
            <i class="fa-solid fa-magnifying-glass"></i>
            <div><h2><?= htmlspecialchars(t('errors.empty')) ?></h2><p><?= htmlspecialchars(t('errors.empty_help')) ?></p></div>
        </article>
    <?php endif; ?>

    <?php foreach ($catalog as $code => $row): ?>
        <article class="panel te-code-card-v2">
            <div class="te-code-side">
                <span class="te-code-area"><?= htmlspecialchars((string)$row['area']) ?></span>
                <strong class="te-code-badge"><?= htmlspecialchars($code) ?></strong>
            </div>

            <div class="te-code-body">
                <div class="te-code-header"><h2><?= htmlspecialchars((string)$row['title']) ?></h2></div>
                <div class="te-code-info-grid">
                    <div class="te-code-info te-user-info">
                        <span class="te-code-info-label"><i class="fa-solid fa-user"></i> <?= htmlspecialchars(t('errors.user_message')) ?></span>
                        <p><?= htmlspecialchars((string)$row['public']) ?></p>
                    </div>
                    <div class="te-code-info te-admin-info">
                        <span class="te-code-info-label"><i class="fa-solid fa-screwdriver-wrench"></i> <?= htmlspecialchars(t('errors.admin_diagnostic')) ?></span>
                        <p><?= htmlspecialchars((string)$row['admin']) ?></p>
                    </div>
                </div>
            </div>
        </article>
    <?php endforeach; ?>
</section>

<section class="panel te-log-card-v2">
    <span class="te-log-icon"><i class="fa-solid fa-file-lines"></i></span>
    <div><h2><?= htmlspecialchars(t('errors.log_title')) ?></h2><p><?= t('errors.log_help') ?></p></div>
</section>

<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
