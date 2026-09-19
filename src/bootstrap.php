<?php

declare(strict_types=1);

use App\Database\Database;
use App\Security\Auth;
use App\Security\SecurityHeaders;
use App\Support\Runtime;
use App\Security\Csrf;
use App\Services\TicketService;
use App\Services\AttachmentService;
use App\Services\ExcelExportService;
use App\Services\AdminStatisticsService;
use App\Services\NotificationService;
use App\Services\SlaService;
use App\Services\AuditService;
use App\Services\AppSettingService;
use App\Services\NotificationPreferenceService;
use App\Services\MailQueueService;
use App\Services\AutomationService;
use App\Services\SavedTicketViewService;

require_once __DIR__ . '/Database/Database.php';
require_once __DIR__ . '/Security/Csrf.php';
require_once __DIR__ . '/Security/Auth.php';
require_once __DIR__ . '/Security/SecurityHeaders.php';
require_once __DIR__ . '/Support/Runtime.php';
require_once __DIR__ . '/Services/TicketService.php';
require_once __DIR__ . '/Services/AttachmentService.php';
require_once __DIR__ . '/Services/ExcelExportService.php';
require_once __DIR__ . '/Services/AdminStatisticsService.php';
require_once __DIR__ . '/Services/NotificationService.php';
require_once __DIR__ . '/Services/SlaService.php';
require_once __DIR__ . '/Services/AuditService.php';
require_once __DIR__ . '/Services/AppSettingService.php';
require_once __DIR__ . '/Services/NotificationPreferenceService.php';
require_once __DIR__ . '/Services/SmtpMailer.php';
require_once __DIR__ . '/Services/MailQueueService.php';
require_once __DIR__ . '/Services/AutomationService.php';
require_once __DIR__ . '/Services/SavedTicketViewService.php';

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    http_response_code(500);
    exit('Configuration manquante : copiez config/config.example.php vers config/config.php.');
}

$config = require $configFile;

Runtime::configure($config);
SecurityHeaders::apply();

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.cookie_httponly', '1');

    session_name('ticketflow_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

$db = new Database($config['database']);
$pdo = $db->pdo();
$auditService = new AuditService($pdo);
$auth = new Auth($pdo, $auditService);
$csrf = new Csrf();
$appSettingService = new AppSettingService($pdo);
$notificationPreferenceService = new NotificationPreferenceService($pdo);
$mailConfig = $config['mail'] ?? [];
$mailConfig['base_url'] = (string) ($config['app']['base_url'] ?? '');
$mailQueueService = new MailQueueService($pdo, $appSettingService, $notificationPreferenceService, $mailConfig);
$notificationService = new NotificationService($pdo, $mailQueueService);
if (!isset($_SESSION['notification_cleanup_at']) || (time() - (int) $_SESSION['notification_cleanup_at']) >= 3600) {
    $notificationService->purgeReadOlderThanDays(1);
    $_SESSION['notification_cleanup_at'] = time();
}
$slaService = new SlaService($pdo, $notificationService);
$ticketService = new TicketService($pdo, $notificationService, $slaService);
$attachmentService = new AttachmentService($pdo, $ticketService, $config['uploads'] ?? []);
$excelExportService = new ExcelExportService();
$adminStatisticsService = new AdminStatisticsService($pdo);
$automationService = new AutomationService($pdo, $appSettingService, $notificationService, $mailQueueService);
$savedTicketViewService = new SavedTicketViewService($pdo);
