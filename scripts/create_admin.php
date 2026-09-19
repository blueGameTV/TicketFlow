<?php

declare(strict_types=1);

use App\Database\Database;

require_once __DIR__ . '/../src/Database/Database.php';

if (PHP_SAPI !== 'cli') {
    exit("Ce script doit être exécuté en ligne de commande.\n");
}

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    exit("Configuration absente : créez config/config.php avant de continuer.\n");
}

$config = require $configFile;
$pdo = (new Database($config['database']))->pdo();

function ask(string $label): string
{
    do {
        $value = trim((string) readline($label));
    } while ($value === '');
    return $value;
}

function askPassword(string $label): string
{
    fwrite(STDOUT, $label);
    if (stripos(PHP_OS_FAMILY, 'Windows') === false && function_exists('shell_exec')) {
        shell_exec('stty -echo');
        $value = trim((string) fgets(STDIN));
        shell_exec('stty echo');
        fwrite(STDOUT, PHP_EOL);
        return $value;
    }
    return trim((string) fgets(STDIN));
}

$firstname = ask('Prénom : ');
$lastname = ask('Nom : ');
$username = mb_strtolower(ask('Identifiant : '));
$email = mb_strtolower(ask('E-mail : '));

if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
    exit("Identifiant invalide. Utilisez 3 à 50 caractères : lettres minuscules, chiffres, point, tiret ou underscore.\n");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    exit("Adresse e-mail invalide.\n");
}

$password = askPassword('Mot de passe (12 caractères minimum) : ');
if (mb_strlen($password) < 12) {
    exit("Le mot de passe doit contenir au moins 12 caractères.\n");
}

$roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Administrateur' LIMIT 1");
$roleStmt->execute();
$roleId = $roleStmt->fetchColumn();
if (!$roleId) {
    exit("Le rôle Administrateur n'existe pas. Avez-vous importé database/schema.sql ?\n");
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO users (firstname, lastname, username, email, password_hash, role_id, active)
         VALUES (:firstname, :lastname, :username, :email, :password_hash, :role_id, 1)'
    );
    $stmt->execute([
        'firstname' => $firstname,
        'lastname' => $lastname,
        'username' => $username,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
        'role_id' => $roleId,
    ]);
} catch (PDOException $e) {
    if (($e->errorInfo[1] ?? null) === 1062) {
        exit("Un compte utilise déjà cet identifiant ou cette adresse e-mail.\n");
    }
    throw $e;
}

fwrite(STDOUT, "\nCompte Administrateur créé avec succès.\n");
