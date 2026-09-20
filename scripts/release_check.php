<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$errors = [];
$warnings = [];
$ok = [];

function line(string $prefix, string $message): void { echo $prefix . ' ' . $message . PHP_EOL; }

$versionFile = $root . '/VERSION';
$version = trim((string) @file_get_contents($versionFile));
line('[INFO]', 'TicketFlow - contrôle de release v' . ($version !== '' ? $version : 'inconnue'));

$requiredFiles = [
    'README.md','VERSION','CHANGELOG.md','SECURITY.md','CONTRIBUTING.md','.gitignore',
    'config/config.example.php','database/schema.sql','docs/installation.md','docs/upgrade.md',
    'docs/roles-permissions.md','docs/ticket-workflow.md','deploy/apache/ticketflow.conf.example',
    'docs/release-candidate-checklist.md','docs/release-notes-v110.md',
    'database/migrations/v110_service_alerts_maintenance.sql','scripts/upgrade_v110.php',
    'public/about.php','public/admin-service-alerts.php','public/admin-maintenance.php',
    'public/maintenance.php','scripts/route_check.php','scripts/rc_preflight.php',
];

foreach ($requiredFiles as $file) if (!is_file($root . '/' . $file)) $errors[] = 'Fichier requis manquant : ' . $file;
if (!$errors) $ok[] = 'Documentation et fichiers de release principaux présents';

if ($version !== '1.1.0') $warnings[] = 'VERSION vaut « ' . $version . ' » au lieu de 1.1.0.';
else $ok[] = 'Version : ' . $version;

foreach (['config/config.php','.env'] as $file) if (is_file($root . '/' . $file)) $warnings[] = $file . ' existe localement : vérifiez qu’il reste ignoré par Git.';

$gitignore = (string) @file_get_contents($root . '/.gitignore');
foreach (['/config/config.php','/storage/uploads/*','/storage/logs/*','.env'] as $rule) if (!str_contains($gitignore,$rule)) $errors[] = '.gitignore ne contient pas la règle attendue : ' . $rule;

$phpFiles = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
foreach ($iterator as $file) {
    if (!$file->isFile() || $file->getExtension() !== 'php') continue;
    $relative = str_replace($root . DIRECTORY_SEPARATOR, '', $file->getPathname());
    if (str_starts_with($relative, 'storage' . DIRECTORY_SEPARATOR)) continue;
    $phpFiles[] = $file->getPathname();
}
$lintFailures = [];
foreach ($phpFiles as $file) {
    $output=[];$code=0;exec(PHP_BINARY.' -l '.escapeshellarg($file).' 2>&1',$output,$code);
    if ($code!==0) $lintFailures[] = str_replace($root.DIRECTORY_SEPARATOR,'',$file).': '.implode(' ',$output);
}
if ($lintFailures) foreach ($lintFailures as $failure) $errors[]='PHP lint : '.$failure;
else $ok[]=count($phpFiles).' fichiers PHP passent php -l';

foreach (['storage/logs','storage/uploads'] as $dir) if (!is_dir($root.'/'.$dir)) $errors[]='Dossier requis manquant : '.$dir;

foreach ($ok as $message) line('[OK]',$message);
foreach ($warnings as $message) line('[WARN]',$message);
foreach ($errors as $message) line('[ERREUR]',$message);
if ($errors) { line('[RESULTAT]','Release non prête : corrigez les erreurs ci-dessus.'); exit(1); }
line('[RESULTAT]','Contrôle de release terminé sans erreur bloquante.');
