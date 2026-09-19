<?php

declare(strict_types=1);

use App\Database\Database;

require_once __DIR__ . '/../src/Database/Database.php';

$root = dirname(__DIR__);
$configFile = $root . '/config/config.php';
$errors = [];
$warnings = [];
$success = [];

function result(string $prefix, string $message): void
{
    echo $prefix . ' ' . $message . PHP_EOL;
}

$versionFile = $root . '/VERSION';
$version = is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : 'inconnue';
result('[INFO]', 'TicketFlow - diagnostic v' . $version);

if (PHP_VERSION_ID < 80100) {
    $errors[] = 'PHP 8.1 minimum est requis. Version détectée : ' . PHP_VERSION;
} else {
    $success[] = 'PHP ' . PHP_VERSION;
}


function iniBytes(string $value): int
{
    $value = trim($value);
    if ($value === '') {
        return 0;
    }
    $unit = strtolower(substr($value, -1));
    $number = (float) $value;
    return match ($unit) {
        'g' => (int) ($number * 1024 * 1024 * 1024),
        'm' => (int) ($number * 1024 * 1024),
        'k' => (int) ($number * 1024),
        default => (int) $number,
    };
}

foreach (['pdo', 'pdo_mysql', 'mbstring', 'fileinfo', 'zip'] as $extension) {
    if (!extension_loaded($extension)) {
        $errors[] = 'Extension PHP manquante : ' . $extension;
    } else {
        $success[] = 'Extension ' . $extension . ' chargée';
    }
}


$uploadMax = iniBytes((string) ini_get('upload_max_filesize'));
$postMax = iniBytes((string) ini_get('post_max_size'));
if ($uploadMax > 0 && $uploadMax < 10 * 1024 * 1024) {
    $warnings[] = 'upload_max_filesize vaut ' . ini_get('upload_max_filesize') . ' : augmentez-le à au moins 10M pour utiliser la limite V7 par défaut.';
} else {
    $success[] = 'upload_max_filesize : ' . ini_get('upload_max_filesize');
}
if ($postMax > 0 && $postMax < 52 * 1024 * 1024) {
    $warnings[] = 'post_max_size vaut ' . ini_get('post_max_size') . ' : 52M ou plus est conseillé pour envoyer jusqu’à 5 fichiers de 10 Mo.';
} else {
    $success[] = 'post_max_size : ' . ini_get('post_max_size');
}

foreach ([$root . '/storage/logs', $root . '/storage/uploads'] as $directory) {
    if (!is_dir($directory)) {
        $errors[] = 'Dossier manquant : ' . $directory;
        continue;
    }
    if (!is_writable($directory)) {
        $warnings[] = 'Dossier non inscriptible par cet utilisateur : ' . $directory;
    } else {
        $success[] = 'Dossier inscriptible : ' . $directory;
    }
}

if (!is_file($configFile)) {
    $errors[] = 'Configuration manquante : config/config.php';
} else {
    $success[] = 'config/config.php présent';
}

if (is_file($configFile)) {
    $perms = fileperms($configFile);
    if (($perms & 0x0004) !== 0) {
        $warnings[] = 'config/config.php est lisible par tous les utilisateurs. Préférez chmod 640.';
    } else {
        $success[] = 'Permissions config/config.php restreintes';
    }
}

if (!$errors && is_file($configFile)) {
    try {
        $config = require $configFile;
        $db = new Database($config['database'] ?? []);
        $pdo = $db->pdo();
        $success[] = 'Connexion MySQL réussie';

        $mail = $config['mail'] ?? [];
        $transport = strtolower((string) ($mail['transport'] ?? 'disabled'));
        if (!in_array($transport, ['disabled', 'log', 'smtp'], true)) {
            $warnings[] = 'Transport e-mail inconnu : ' . $transport;
        } else {
            $success[] = 'Transport e-mail : ' . $transport;
        }
        if ($transport === 'smtp') {
            if (empty($mail['host']) || empty($mail['from_email'])) {
                $warnings[] = 'SMTP activé mais host/from_email incomplets dans config/config.php.';
            } else {
                $success[] = 'Configuration SMTP de base présente';
            }
        }

        $requiredTables = [
            'roles', 'groups_company', 'users', 'applications', 'user_applications',
            'ticket_types', 'ticket_categories', 'priorities', 'sla_policies', 'audit_logs', 'login_attempts', 'user_sessions', 'user_preferences', 'password_reset_tokens', 'ticket_statuses',
            'tickets', 'ticket_messages', 'ticket_history', 'manager_approvals', 'ticket_attachments', 'notifications',
            'app_settings', 'notification_preferences', 'email_queue', 'automation_events', 'saved_ticket_views',
        ];

        $tableStmt = $pdo->query('SHOW TABLES');
        $tables = array_map('strval', $tableStmt->fetchAll(PDO::FETCH_COLUMN));
        foreach ($requiredTables as $table) {
            if (!in_array($table, $tables, true)) {
                $errors[] = 'Table manquante : ' . $table;
            }
        }
        if (!$errors) {
            $success[] = 'Toutes les tables principales sont présentes';
        }

        $requiredStatuses = [
            'new', 'assigned', 'in_progress', 'waiting_user', 'waiting_manager',
            'resolved', 'waiting_confirmation', 'closed', 'cancelled',
        ];
        $statusStmt = $pdo->query('SELECT code FROM ticket_statuses');
        $statuses = array_map('strval', $statusStmt->fetchAll(PDO::FETCH_COLUMN));
        foreach ($requiredStatuses as $status) {
            if (!in_array($status, $statuses, true)) {
                $errors[] = 'Statut manquant dans ticket_statuses : ' . $status;
            }
        }

        $requiredRoles = ['Administrateur', 'IT', 'Manager', 'Collaborateur'];
        $roleStmt = $pdo->query('SELECT name FROM roles');
        $roles = array_map('strval', $roleStmt->fetchAll(PDO::FETCH_COLUMN));
        foreach ($requiredRoles as $role) {
            if (!in_array($role, $roles, true)) {
                $errors[] = 'Rôle manquant : ' . $role;
            }
        }

        $requiredColumns = [
            'tickets' => ['id','ticket_number','requester_id','assigned_it_id','type_id','category_id','priority_id','status_id','title','description','assigned_at','sla_response_due_at','sla_resolution_due_at','sla_response_alerted_at','sla_resolution_alerted_at','resolution','created_at','updated_at','resolved_at','closed_at','deleted_at'],
            'users' => ['id','firstname','lastname','username','email','profile_photo','password_hash','role_id','group_id','arrival_date','active','last_login_at','must_change_password','password_changed_at','locked_until'],
            'ticket_attachments' => ['id','ticket_id','uploaded_by','original_name','stored_name','mime_type','size_bytes','created_at'],
            'notifications' => ['id','user_id','ticket_id','type','title','message','link_url','read_at','created_at'],
        ];
        foreach ($requiredColumns as $table => $columns) {
            $columnStmt = $pdo->query('SHOW COLUMNS FROM `' . $table . '`');
            $present = array_map(static fn(array $row): string => (string) $row['Field'], $columnStmt->fetchAll(PDO::FETCH_ASSOC));
            foreach ($columns as $column) {
                if (!in_array($column, $present, true)) {
                    $errors[] = 'Colonne manquante : ' . $table . '.' . $column;
                }
            }
        }

        if (in_array('sla_policies', $tables, true)) {
            $policyCount = (int) $pdo->query('SELECT COUNT(*) FROM sla_policies WHERE active = 1')->fetchColumn();
            if ($policyCount < 4) {
                $warnings[] = 'Moins de 4 politiques SLA actives sont configurées.';
            } else {
                $success[] = 'Politiques SLA actives : ' . $policyCount;
            }
        }
    } catch (Throwable $e) {
        $errors[] = 'Erreur de diagnostic base de données : ' . $e->getMessage();
    }
}

foreach ($success as $message) {
    result('[OK]', $message);
}
foreach ($warnings as $message) {
    result('[WARN]', $message);
}
foreach ($errors as $message) {
    result('[ERREUR]', $message);
}

if ($errors) {
    result('[RESULTAT]', 'Des erreurs doivent être corrigées.');
    exit(1);
}

result('[RESULTAT]', 'Diagnostic terminé : aucun problème bloquant détecté.');

