<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$errors = [];
$warnings = [];
$ok = [];

$root = dirname(__DIR__);
$configFile = $root . '/config/config.php';

$check = static function (bool $condition, string $success, string $failure, bool $warningOnly = false) use (&$errors, &$warnings, &$ok): void {
    if ($condition) {
        $ok[] = $success;
        return;
    }
    if ($warningOnly) {
        $warnings[] = $failure;
    } else {
        $errors[] = $failure;
    }
};

$check((bool) ini_get('session.use_strict_mode'), 'Sessions strictes activées', 'session.use_strict_mode doit être activé.');
$check((bool) ini_get('session.use_only_cookies'), 'Sessions limitées aux cookies', 'session.use_only_cookies doit être activé.');
$check(!(bool) ini_get('session.use_trans_sid'), 'Identifiants de session absents des URL', 'session.use_trans_sid doit être désactivé.');

if (is_file($configFile)) {
    $perms = fileperms($configFile);
    $worldReadable = ($perms & 0x0004) !== 0;
    $check(!$worldReadable, 'config/config.php non lisible par tous les utilisateurs', 'config/config.php est lisible par tous les utilisateurs du système. Préférez chmod 640.', true);
}

$storage = $root . '/storage';
$check(is_dir($storage) && is_writable($storage), 'storage/ est présent et inscriptible', 'storage/ est absent ou non inscriptible.');
$check(!is_dir($root . '/public/storage'), 'Aucun stockage sensible sous public/', 'Un dossier public/storage existe : vérifiez qu’il ne contient aucun fichier sensible.');

// Vérifie les sessions actives rattachées à des comptes désactivés.
$disabledSessions = (int) $pdo->query(
    'SELECT COUNT(*)
     FROM user_sessions us
     INNER JOIN users u ON u.id = us.user_id
     WHERE us.revoked_at IS NULL AND us.expires_at > NOW() AND u.active = 0'
)->fetchColumn();
$check($disabledSessions === 0, 'Aucune session active pour un compte désactivé', $disabledSessions . ' session(s) active(s) appartiennent à des comptes désactivés.');

// Vérifie les reset tokens encore actifs au-delà de leur expiration.
$expiredResetTokens = (int) $pdo->query(
    'SELECT COUNT(*) FROM password_reset_tokens WHERE used_at IS NULL AND expires_at < NOW()'
)->fetchColumn();
if ($expiredResetTokens > 0) {
    $warnings[] = $expiredResetTokens . ' jeton(s) de réinitialisation expiré(s) pourront être nettoyés par le cron.';
} else {
    $ok[] = 'Aucun jeton de réinitialisation expiré en attente';
}

// Contrôle les fichiers uploadés référencés en base.
$attachments = $pdo->query('SELECT id, ticket_id, stored_name FROM ticket_attachments')->fetchAll();
$missingFiles = 0;
foreach ($attachments as $attachment) {
    $path = $root . '/storage/uploads/tickets/' . (int) $attachment['ticket_id'] . '/' . basename((string) $attachment['stored_name']);
    if (!is_file($path)) {
        $missingFiles++;
    }
}
$check($missingFiles === 0, 'Toutes les pièces jointes référencées sont présentes', $missingFiles . ' pièce(s) jointe(s) sont référencées en base mais absentes du disque.', true);

foreach ($ok as $message) {
    echo '[OK] ' . $message . PHP_EOL;
}
foreach ($warnings as $message) {
    echo '[WARN] ' . $message . PHP_EOL;
}
foreach ($errors as $message) {
    echo '[ERREUR] ' . $message . PHP_EOL;
}

if ($errors !== []) {
    echo '[RESULTAT] Audit sécurité : erreurs à corriger.' . PHP_EOL;
    exit(1);
}

echo '[RESULTAT] Audit sécurité terminé : aucun problème bloquant détecté.' . PHP_EOL;
