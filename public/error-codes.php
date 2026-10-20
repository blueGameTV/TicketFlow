<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use App\Support\ErrorCatalog;

$auth->requireRole('Administrateur', 'IT');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$pageTitle = t('nav.error_codes');

$catalog = ErrorCatalog::all();

// The error catalogue's canonical explanations are stored in French.
// These translations are presentation-only and do not alter technical error identifiers.
$englishErrors = [
 'TE-001'=>['System','Missing TicketFlow configuration','TicketFlow cannot load its configuration.','Check config/config.php and its permissions.'],
 'TE-002'=>['Database','Database connection unavailable','TicketFlow cannot access its database.','Check MariaDB/MySQL, host, port, database name and SQL credentials.'],
 'TE-003'=>['Database','SQL query error','A database operation failed.','Check technical-errors.log and the database schema.'],
 'TE-004'=>['Database','Incompatible database schema','The TicketFlow database structure needs to be checked.','Check migrations and schema version.'],
 'TE-005'=>['System','Unable to write to storage','TicketFlow cannot write a required file.','Check storage/, logs/, cache/, sessions/ and uploads/.'],
 'TE-101'=>['Security','Invalid session or CSRF token','Your session or form is no longer valid.','Check PHP sessions and CSRF validation.'],
 'TE-102'=>['Authentication','Authentication failed','Your sign-in could not be verified.','Check the account and authentication logs.'],
 'TE-103'=>['Authentication','Account state incompatible','This account cannot perform the requested operation.','Check activation, lock status and forced password change.'],
 'TE-104'=>['Security','Insufficient permissions','You are not authorized to perform this operation.','Check user role and access scope.'],
 'TE-105'=>['Authentication','Password reset failed','The password reset could not be completed.','Check token, expiration and account status.'],
 'TE-201'=>['Tickets','Unable to create ticket','The ticket could not be created.','Check TicketService, type, category, priority and database.'],
 'TE-202'=>['Tickets','Unable to update ticket','The ticket could not be updated.','Check workflow, status and permissions.'],
 'TE-203'=>['Tickets','Unable to assign or transfer ticket','The ticket could not be assigned or transferred.','Check target IT support user and assignment rules.'],
 'TE-204'=>['Tickets','Unable to add ticket message','The message could not be added.','Check ticket_messages and access permissions.'],
 'TE-205'=>['Workflow','Manager approval failed','Manager approval could not be processed.','Check N+1, final manager, approval stage and manager_approvals.'],
 'TE-206'=>['Workflow','Ticket resolution failed','The resolution could not be recorded.','Check ticket status and requester confirmation.'],
 'TE-301'=>['Administration','User operation failed','The user account operation failed.','Check account dependencies and SQL constraints.'],
 'TE-302'=>['Administration','Group operation failed','The group operation failed.','Check manager, group members and constraints.'],
 'TE-303'=>['Administration','User import failed','The users file could not be imported.','Check CSV/XLSX format, columns and values.'],
 'TE-401'=>['Files','Upload failed','The file could not be uploaded.','Check file size, MIME type and storage/uploads/.'],
 'TE-402'=>['Files','Attachment unavailable','The attachment cannot be opened.','Check the file, database record and permissions.'],
 'TE-403'=>['Exports','Excel export failed','The Excel file could not be generated.','Check ExcelExportService and unexpected PHP output.'],
 'TE-501'=>['Email','Unable to send email','TicketFlow could not send the email.','Check SMTP, email queue and mail.log.'],
 'TE-502'=>['Notifications','Notification failed','The notification could not be created.','Check NotificationService and preferences.'],
 'TE-601'=>['Service','Service alert failed','The service alert could not be processed.','Check ServiceAlertService and dates.'],
 'TE-602'=>['Service','Maintenance operation failed','The maintenance could not be processed.','Check MaintenanceService and dates.'],
 'TE-701'=>['API','TicketFlow API error','The TicketFlow request could not be processed.','Check API endpoint, session and technical-errors.log.'],
 'TE-702'=>['Live updates','Live synchronization failed','Live synchronization encountered a problem.','Check live-* endpoints and HTTP responses.'],
 'TE-801'=>['Installation','Installation or setup failed','TicketFlow installation could not be completed.','Check setup.php, requirements, database and permissions.'],
 'TE-802'=>['Update','Update failed','The TicketFlow update could not be completed.','Check update.sh, migrations and backups.'],
 'TE-999'=>['System','Unexpected technical error','An unexpected technical error occurred.','Search for TE-999 and its reference in technical-errors.log.'],
];
if ($translator->locale() === 'en') {
    foreach ($catalog as $code => &$row) {
        if (!isset($englishErrors[$code])) continue;
        [$row['area'], $row['title'], $row['public'], $row['admin']] = $englishErrors[$code];
    }
    unset($row);
}

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
