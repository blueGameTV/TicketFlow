<?php
declare(strict_types=1);

use App\Database\Database;

require_once __DIR__ . '/../src/Database/Database.php';

$root = dirname(__DIR__);
$configFile = $root . '/config/config.php';
$tokenFile = $root . '/storage/install-token';
$lockFile = $root . '/storage/installed.lock';
$versionFile = $root . '/VERSION';
$version = is_file($versionFile) ? trim((string)file_get_contents($versionFile)) : '1.2.0';

function h(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

$error = '';
$success = false;
$fatal = '';
$statusCode = 200;
$providedToken = (string)($_GET['token'] ?? $_POST['token'] ?? '');
$pdo = null;
$config = [];

if (is_file($lockFile)) {
    $statusCode = 403;
    $fatal = 'TicketFlow est déjà installé. L’assistant d’installation est maintenant verrouillé.';
} elseif (!is_file($configFile) || !is_file($tokenFile)) {
    $statusCode = 503;
    $fatal = 'Installation incomplète : la configuration locale ou le jeton d’installation est absent.';
} else {
    $expectedToken = trim((string)file_get_contents($tokenFile));
    if ($expectedToken === '' || $providedToken === '' || !hash_equals($expectedToken, $providedToken)) {
        $statusCode = 403;
        $fatal = 'Le lien d’installation est invalide ou a expiré.';
    } else {
        try {
            $config = require $configFile;
            $pdo = (new Database($config['database'] ?? []))->pdo();
        } catch (Throwable $e) {
            $statusCode = 503;
            $fatal = 'TicketFlow ne parvient pas à se connecter à la base de données. Vérifiez la configuration puis réessayez.';
        }
    }
}

if ($statusCode !== 200) {
    http_response_code($statusCode);
}

if (!$fatal && $_SERVER['REQUEST_METHOD'] === 'POST' && $pdo instanceof PDO) {
    $firstname = trim((string)($_POST['firstname'] ?? ''));
    $lastname = trim((string)($_POST['lastname'] ?? ''));
    $username = mb_strtolower(trim((string)($_POST['username'] ?? '')));
    $email = mb_strtolower(trim((string)($_POST['email'] ?? '')));
    $password = (string)($_POST['password'] ?? '');
    $passwordConfirm = (string)($_POST['password_confirm'] ?? '');

    if ($firstname === '' || $lastname === '' || $username === '' || $email === '' || $password === '' || $passwordConfirm === '') {
        $error = 'Tous les champs sont obligatoires.';
    } elseif (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
        $error = 'Identifiant invalide : utilisez 3 à 50 caractères parmi les lettres minuscules, chiffres, point, tiret et underscore.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Adresse e-mail invalide.';
    } elseif (mb_strlen($password) < 12) {
        $error = 'Le mot de passe doit contenir au moins 12 caractères.';
    } elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
        $error = 'Le mot de passe doit contenir au moins une majuscule, une minuscule et un chiffre.';
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

            $existingAdmin = $pdo->prepare("SELECT COUNT(*) FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE r.name = 'Administrateur' AND u.active = 1");
            $existingAdmin->execute();
            if ((int)$existingAdmin->fetchColumn() > 0) {
                throw new RuntimeException('Un compte Administrateur actif existe déjà.');
            }

            $stmt = $pdo->prepare('INSERT INTO users (firstname, lastname, username, email, password_hash, role_id, active, password_changed_at) VALUES (:firstname, :lastname, :username, :email, :password_hash, :role_id, 1, NOW())');
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

$dbReady = !$fatal && $pdo instanceof PDO;
$configReady = is_file($configFile);
$tokenReady = is_file($tokenFile) || $success;
?>
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>Configuration initiale - TicketFlow</title>
<style>
:root{--blue:#3156d9;--blue2:#5878ef;--ink:#142038;--muted:#68758a;--line:#dfe5ef;--soft:#f5f7fb;--ok:#198754;--danger:#c43c47}
*{box-sizing:border-box}body{margin:0;min-height:100vh;font-family:Inter,ui-sans-serif,system-ui,-apple-system,"Segoe UI",sans-serif;background:radial-gradient(circle at 8% 0,#eaf0ff 0,transparent 32%),linear-gradient(180deg,#f7f9fd,#eef2f8);color:var(--ink)}
.shell{width:min(1120px,calc(100% - 34px));margin:34px auto;display:grid;grid-template-columns:360px minmax(0,1fr);min-height:680px;background:#fff;border:1px solid var(--line);border-radius:26px;overflow:hidden;box-shadow:0 28px 80px rgba(25,40,74,.12)}
.side{position:relative;padding:34px;background:linear-gradient(150deg,#192b52 0%,#203d7d 58%,#3156d9 100%);color:#fff;display:flex;flex-direction:column;overflow:hidden}.side:before,.side:after{content:"";position:absolute;border-radius:50%;background:rgba(255,255,255,.07)}.side:before{width:290px;height:290px;right:-135px;top:-95px}.side:after{width:230px;height:230px;left:-130px;bottom:-95px}
.brand{position:relative;z-index:1;display:flex;align-items:center;gap:12px;font-weight:900;font-size:1.08rem}.brand-mark{width:42px;height:42px;border-radius:13px;background:rgba(255,255,255,.14);display:grid;place-items:center;border:1px solid rgba(255,255,255,.18)}
.side-copy{position:relative;z-index:1;margin:auto 0}.eyebrow{display:block;font-size:.73rem;font-weight:900;text-transform:uppercase;letter-spacing:.1em;color:#b9c9ff;margin-bottom:10px}.side h1{margin:0;font-size:2.1rem;line-height:1.1}.side p{margin:15px 0 0;color:#d5def7;line-height:1.65;font-size:.93rem}
.steps{position:relative;z-index:1;display:grid;gap:12px;margin-top:28px}.step{display:grid;grid-template-columns:34px 1fr;gap:11px;align-items:center;color:#cdd8f4}.step-no{width:34px;height:34px;border-radius:11px;display:grid;place-items:center;background:rgba(255,255,255,.09);border:1px solid rgba(255,255,255,.13);font-weight:900}.step strong{display:block;color:#fff;font-size:.84rem}.step span{font-size:.74rem}.step.active .step-no{background:#fff;color:var(--blue)}.side-version{position:relative;z-index:1;margin-top:30px;font-size:.75rem;color:#cbd7f7}.side-version strong{color:#fff}
.main{padding:34px 40px 38px}.main-head{display:flex;align-items:flex-start;justify-content:space-between;gap:20px;padding-bottom:22px;border-bottom:1px solid var(--line)}.main-head h2{margin:4px 0 6px;font-size:1.65rem}.main-head p{margin:0;color:var(--muted);line-height:1.55}.status-pill{flex:0 0 auto;padding:8px 11px;border-radius:999px;background:#eaf7f0;color:#16734a;font-size:.74rem;font-weight:850}.status-pill.error{background:#fff0f1;color:#a92d3a}
.health{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin:20px 0}.health-card{padding:12px 13px;border:1px solid var(--line);border-radius:13px;background:var(--soft)}.health-card span{display:block;color:var(--muted);font-size:.7rem;text-transform:uppercase;letter-spacing:.05em;font-weight:800}.health-card strong{display:block;margin-top:4px;font-size:.83rem}.health-card.ok strong{color:var(--ok)}.health-card.bad strong{color:var(--danger)}
.alert{padding:12px 14px;border-radius:12px;margin:0 0 18px;font-size:.86rem;line-height:1.5}.alert.error{background:#fff0f1;color:#9f2f39;border:1px solid #f2c6ca}.alert.success{background:#eaf8f1;color:#176b47;border:1px solid #bde5d0}
.form-title{margin:22px 0 16px}.form-title h3{margin:0 0 4px;font-size:1.08rem}.form-title p{margin:0;color:var(--muted);font-size:.83rem}.grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}.field{display:grid;gap:7px}.full{grid-column:1/-1}label{font-weight:750;font-size:.83rem}input{width:100%;min-height:46px;padding:11px 13px;border:1px solid #cfd8e6;border-radius:11px;background:#fff;color:var(--ink);font:inherit;outline:none;transition:.15s}input:focus{border-color:var(--blue);box-shadow:0 0 0 3px rgba(49,86,217,.11)}input::placeholder{color:#9aa5b6}.hint{color:var(--muted);font-size:.72rem;line-height:1.45}.actions{display:flex;justify-content:flex-end;margin-top:22px;padding-top:20px;border-top:1px solid var(--line)}button,.btn{min-height:46px;padding:11px 17px;border:0;border-radius:11px;background:linear-gradient(180deg,var(--blue2),var(--blue));color:#fff;font:inherit;font-weight:850;text-decoration:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:8px;box-shadow:0 10px 22px rgba(49,86,217,.2)}button:hover,.btn:hover{filter:brightness(1.04)}
.success-box{text-align:center;padding:42px 22px}.success-icon{width:72px;height:72px;border-radius:22px;margin:0 auto 18px;display:grid;place-items:center;background:#e8f8ef;color:var(--ok);font-size:2rem;font-weight:900}.success-box h3{font-size:1.5rem;margin:0 0 9px}.success-box p{max-width:520px;margin:0 auto 22px;color:var(--muted);line-height:1.65}.fatal-box{padding:34px 0;text-align:center}.fatal-icon{width:64px;height:64px;border-radius:20px;margin:0 auto 16px;display:grid;place-items:center;background:#fff0f1;color:var(--danger);font-size:1.55rem;font-weight:900}.fatal-box h3{margin:0 0 8px}.fatal-box p{max-width:520px;margin:0 auto;color:var(--muted);line-height:1.65}
@media(max-width:860px){.shell{grid-template-columns:1fr}.side{min-height:300px}.side-copy{margin:36px 0 0}.steps{grid-template-columns:repeat(3,1fr)}.step{grid-template-columns:1fr;text-align:center}.main{padding:28px}.side-version{margin-top:24px}}
@media(max-width:620px){.shell{width:min(100% - 20px,720px);margin:10px auto;border-radius:18px}.side{padding:24px}.main{padding:22px}.grid,.health{grid-template-columns:1fr}.steps{grid-template-columns:1fr}.step{grid-template-columns:34px 1fr;text-align:left}.main-head{flex-direction:column}.actions .btn,.actions button{width:100%}}
</style>
</head>
<body>
<div class="shell">
    <aside class="side">
        <div class="brand"><span class="brand-mark">TF</span><span>TicketFlow</span></div>
        <div class="side-copy">
            <span class="eyebrow">Configuration initiale</span>
            <h1>Bienvenue dans TicketFlow.</h1>
            <p>Quelques informations suffisent pour sécuriser l’instance et créer le premier compte Administrateur.</p>
            <div class="steps">
                <div class="step <?= $fatal ? '' : 'active' ?>"><span class="step-no">1</span><div><strong>Environnement</strong><span>Configuration & base</span></div></div>
                <div class="step <?= (!$fatal && !$success) ? 'active' : '' ?>"><span class="step-no">2</span><div><strong>Administrateur</strong><span>Premier compte</span></div></div>
                <div class="step <?= $success ? 'active' : '' ?>"><span class="step-no">3</span><div><strong>Terminé</strong><span>Accès à TicketFlow</span></div></div>
            </div>
        </div>
        <div class="side-version">Version détectée<br><strong>TicketFlow <?= h($version) ?></strong></div>
    </aside>

    <main class="main">
        <div class="main-head">
            <div><span class="eyebrow" style="color:var(--blue)">Assistant d’installation</span><h2><?= $success ? 'Installation terminée' : ($fatal ? 'Installation indisponible' : 'Créer le premier Administrateur') ?></h2><p><?= $success ? 'Votre instance TicketFlow est prête à être utilisée.' : ($fatal ? 'Une vérification empêche la poursuite de l’assistant.' : 'Ce compte disposera des droits d’administration de l’instance.') ?></p></div>
            <span class="status-pill <?= $fatal ? 'error' : '' ?>"><?= $fatal ? 'Action requise' : ($success ? 'Prêt' : 'Configuration') ?></span>
        </div>

        <div class="health">
            <div class="health-card <?= $configReady ? 'ok' : 'bad' ?>"><span>Configuration</span><strong><?= $configReady ? 'Détectée' : 'Absente' ?></strong></div>
            <div class="health-card <?= $dbReady || $success ? 'ok' : 'bad' ?>"><span>Base de données</span><strong><?= $dbReady || $success ? 'Connexion prête' : 'Indisponible' ?></strong></div>
            <div class="health-card <?= $tokenReady ? 'ok' : 'bad' ?>"><span>Jeton d’installation</span><strong><?= $success ? 'Verrouillé' : ($tokenReady ? 'Valide' : 'Absent') ?></strong></div>
        </div>

        <?php if ($fatal): ?>
            <div class="fatal-box"><div class="fatal-icon">!</div><h3>Impossible de continuer</h3><p><?= h($fatal) ?></p></div>
        <?php elseif ($success): ?>
            <div class="success-box"><div class="success-icon">✓</div><h3>TicketFlow est prêt</h3><p>Le premier compte Administrateur a été créé. L’assistant d’installation est maintenant verrouillé et le jeton temporaire a été supprimé.</p><a class="btn" href="/login.php">Accéder à TicketFlow →</a></div>
        <?php else: ?>
            <?php if ($error !== ''): ?><div class="alert error"><strong>Vérification :</strong> <?= h($error) ?></div><?php endif; ?>
            <div class="form-title"><h3>Informations du compte</h3><p>Utilisez un compte nominatif et une adresse e-mail accessible.</p></div>
            <form method="post" autocomplete="off">
                <input type="hidden" name="token" value="<?= h($providedToken) ?>">
                <div class="grid">
                    <div class="field"><label for="firstname">Prénom</label><input id="firstname" name="firstname" required autocomplete="given-name" value="<?= h((string)($_POST['firstname'] ?? '')) ?>" placeholder="Jean"></div>
                    <div class="field"><label for="lastname">Nom</label><input id="lastname" name="lastname" required autocomplete="family-name" value="<?= h((string)($_POST['lastname'] ?? '')) ?>" placeholder="Dupont"></div>
                    <div class="field"><label for="username">Identifiant</label><input id="username" name="username" minlength="3" maxlength="50" required autocomplete="username" value="<?= h((string)($_POST['username'] ?? '')) ?>" placeholder="jdupont"><span class="hint">3–50 caractères : lettres minuscules, chiffres, point, tiret ou underscore.</span></div>
                    <div class="field"><label for="email">Adresse e-mail</label><input id="email" type="email" name="email" required autocomplete="email" value="<?= h((string)($_POST['email'] ?? '')) ?>" placeholder="jean.dupont@entreprise.fr"></div>
                    <div class="field"><label for="password">Mot de passe</label><input id="password" type="password" name="password" minlength="12" required autocomplete="new-password" placeholder="12 caractères minimum"><span class="hint">Majuscule, minuscule et chiffre obligatoires.</span></div>
                    <div class="field"><label for="password_confirm">Confirmation</label><input id="password_confirm" type="password" name="password_confirm" minlength="12" required autocomplete="new-password" placeholder="Confirmez le mot de passe"></div>
                </div>
                <div class="actions"><button type="submit">Créer l’Administrateur et terminer →</button></div>
            </form>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
