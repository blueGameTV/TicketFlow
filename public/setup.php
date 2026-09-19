<?php
declare(strict_types=1);

use App\Database\Database;

require_once __DIR__ . '/../src/Database/Database.php';

$root = dirname(__DIR__);
$configFile = $root . '/config/config.php';
$tokenFile = $root . '/storage/install-token';
$lockFile = $root . '/storage/installed.lock';

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

if (is_file($lockFile)) {
    http_response_code(403);
    exit('TicketFlow est déjà installé. L\'assistant d\'installation est verrouillé.');
}

if (!is_file($configFile) || !is_file($tokenFile)) {
    http_response_code(503);
    exit('Installation incomplète : configuration ou jeton d\'installation absent.');
}

$expectedToken = trim((string) file_get_contents($tokenFile));
$providedToken = (string)($_GET['token'] ?? $_POST['token'] ?? '');

if ($expectedToken === '' || $providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
    http_response_code(403);
    exit('Jeton d\'installation invalide.');
}

$config = require $configFile;
$pdo = (new Database($config['database'] ?? []))->pdo();

$error = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $firstname = trim((string)($_POST['firstname'] ?? ''));
    $lastname = trim((string)($_POST['lastname'] ?? ''));
    $username = mb_strtolower(trim((string)($_POST['username'] ?? '')));
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if ($firstname === '' || $lastname === '' || $username === '' || $email === '') {
        $error = 'Tous les champs sont obligatoires.';
    } elseif (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
        $error = 'Identifiant invalide : 3 à 50 caractères (lettres minuscules, chiffres, point, tiret ou underscore).';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse e-mail invalide.';
    } elseif (mb_strlen($password) < 12) {
        $error = 'Le mot de passe doit contenir au moins 12 caractères.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Les mots de passe ne correspondent pas.';
    } else {
        try {
            $pdo->beginTransaction();

            $roleStmt = $pdo->prepare("SELECT id FROM roles WHERE name = 'Administrateur' LIMIT 1");
            $roleStmt->execute();
            $roleId = $roleStmt->fetchColumn();

            if (!$roleId) {
                throw new RuntimeException('Le rôle Administrateur est introuvable dans la base.');
            }

            $existingAdmin = $pdo->prepare(
                "SELECT COUNT(*) FROM users u
                 INNER JOIN roles r ON r.id = u.role_id
                 WHERE r.name = 'Administrateur' AND u.active = 1"
            );
            $existingAdmin->execute();

            if ((int)$existingAdmin->fetchColumn() > 0) {
                throw new RuntimeException('Un compte Administrateur actif existe déjà.');
            }

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

            $pdo->commit();

            file_put_contents($lockFile, date(DATE_ATOM) . PHP_EOL, LOCK_EX);
            @unlink($tokenFile);
            @chmod($lockFile, 0660);
            $success = true;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $e instanceof PDOException && (($e->errorInfo[1] ?? null) === 1062)
                ? 'Cet identifiant ou cette adresse e-mail est déjà utilisé.'
                : $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Installation TicketFlow</title>
<style>
body{margin:0;font-family:system-ui,-apple-system,Segoe UI,Roboto,sans-serif;background:#f4f6fb;color:#111827}
.wrap{max-width:760px;margin:48px auto;padding:0 20px}
.card{background:#fff;border:1px solid #e5e7eb;border-radius:18px;padding:28px;box-shadow:0 16px 45px rgba(15,23,42,.08)}
h1{margin:0 0 8px;font-size:30px} p{color:#64748b}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.full{grid-column:1/-1}
label{display:block;font-weight:600;margin-bottom:6px}
input{width:100%;box-sizing:border-box;padding:12px 14px;border:1px solid #cbd5e1;border-radius:10px;font-size:15px}
button,.btn{display:inline-block;background:#3156d9;color:white;border:0;border-radius:10px;padding:12px 18px;font-weight:700;text-decoration:none;cursor:pointer}
.error{background:#fee2e2;color:#991b1b;padding:12px 14px;border-radius:10px;margin:16px 0}
.ok{background:#dcfce7;color:#166534;padding:16px;border-radius:10px;margin:16px 0}
@media(max-width:650px){.grid{grid-template-columns:1fr}}
</style>
</head>
<body><div class="wrap"><div class="card">
<h1>Installation TicketFlow</h1>
<p>Créez le premier compte Administrateur pour terminer l'installation.</p>
<?php if ($success): ?>
<div class="ok"><strong>Installation terminée.</strong><br>Le compte Administrateur a été créé et l'assistant est maintenant verrouillé.</div>
<a class="btn" href="/login.php">Accéder à TicketFlow</a>
<?php else: ?>
<?php if ($error !== ''): ?><div class="error"><?=h($error)?></div><?php endif; ?>
<form method="post">
<input type="hidden" name="token" value="<?=h($providedToken)?>">
<div class="grid">
<div><label>Prénom</label><input name="firstname" required></div>
<div><label>Nom</label><input name="lastname" required></div>
<div><label>Identifiant</label><input name="username" minlength="3" maxlength="50" required></div>
<div><label>E-mail</label><input type="email" name="email" required></div>
<div><label>Mot de passe</label><input type="password" name="password" minlength="12" required></div>
<div><label>Confirmation</label><input type="password" name="password_confirm" minlength="12" required></div>
<div class="full"><button type="submit">Créer l'Administrateur et terminer</button></div>
</div>
</form>
<?php endif; ?>
</div></div></body></html>
