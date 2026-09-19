<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

$auth->requireRole('Administrateur');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';

$id = isset($_GET['id']) ? (int) $_GET['id'] : (int) ($_POST['id'] ?? 0);
$isEdit = $id > 0;
$pageTitle = $isEdit ? 'Modifier un utilisateur' : 'Ajouter un utilisateur';

$roles = $pdo->query('SELECT id, name FROM roles ORDER BY id')->fetchAll();
$groups = $pdo->query('SELECT id, name, active FROM groups_company ORDER BY active DESC, name')->fetchAll();

$form = [
    'firstname' => '', 'lastname' => '', 'username' => '', 'email' => '', 'role_id' => '', 'group_id' => '',
    'arrival_date' => '', 'active' => 1, 'must_change_password' => 1,
];
$errors = [];

if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare('SELECT id, firstname, lastname, username, email, role_id, group_id, arrival_date, active, must_change_password FROM users WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();
    if (!$existing) {
        http_response_code(404);
        exit('Utilisateur introuvable.');
    }
    $form = $existing;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!$csrf->validate($_POST['csrf_token'] ?? null)) {
        $errors[] = 'La session du formulaire a expiré. Rechargez la page.';
    }

    $form['firstname'] = trim((string) ($_POST['firstname'] ?? ''));
    $form['lastname'] = trim((string) ($_POST['lastname'] ?? ''));
    $form['username'] = mb_strtolower(trim((string) ($_POST['username'] ?? '')));
    $form['email'] = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $form['role_id'] = (int) ($_POST['role_id'] ?? 0);
    $form['group_id'] = ($_POST['group_id'] ?? '') !== '' ? (int) $_POST['group_id'] : null;
    $form['arrival_date'] = trim((string) ($_POST['arrival_date'] ?? ''));
    $form['active'] = isset($_POST['active']) ? 1 : 0;
    $form['must_change_password'] = isset($_POST['must_change_password']) ? 1 : 0;
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

    if (mb_strlen($form['firstname']) < 2 || mb_strlen($form['firstname']) > 80) $errors[] = 'Le prénom doit contenir entre 2 et 80 caractères.';
    if (mb_strlen($form['lastname']) < 2 || mb_strlen($form['lastname']) > 80) $errors[] = 'Le nom doit contenir entre 2 et 80 caractères.';
    if (!preg_match('/^[a-z0-9._-]{3,50}$/', (string) $form['username'])) $errors[] = 'L’identifiant doit contenir 3 à 50 caractères : lettres minuscules, chiffres, point, tiret ou underscore.';
    if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'L’adresse e-mail n’est pas valide.';

    $roleIds = array_map(static fn($r) => (int) $r['id'], $roles);
    if (!in_array((int) $form['role_id'], $roleIds, true)) $errors[] = 'Le rôle sélectionné n’est pas valide.';

    if ($form['group_id'] !== null) {
        $groupIds = array_map(static fn($g) => (int) $g['id'], $groups);
        if (!in_array((int) $form['group_id'], $groupIds, true)) $errors[] = 'Le groupe sélectionné n’est pas valide.';
    }

    if ($form['arrival_date'] !== '') {
        $date = DateTime::createFromFormat('Y-m-d', $form['arrival_date']);
        if (!$date || $date->format('Y-m-d') !== $form['arrival_date']) $errors[] = 'La date d’arrivée n’est pas valide.';
    } else {
        $form['arrival_date'] = null;
    }

    if (!$isEdit && mb_strlen($password) < 12) $errors[] = 'Le mot de passe doit contenir au minimum 12 caractères.';
    if ($password !== '' && mb_strlen($password) < 12) $errors[] = 'Le nouveau mot de passe doit contenir au minimum 12 caractères.';
    if ($password !== $passwordConfirm) $errors[] = 'Les deux mots de passe ne correspondent pas.';

    $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = :email AND id <> :id LIMIT 1');
    $emailCheck->execute(['email' => $form['email'], 'id' => $id]);
    if ($emailCheck->fetch()) $errors[] = 'Cette adresse e-mail est déjà utilisée.';
    $usernameCheck = $pdo->prepare('SELECT id FROM users WHERE username = :username AND id <> :id LIMIT 1');
    $usernameCheck->execute(['username' => $form['username'], 'id' => $id]);
    if ($usernameCheck->fetch()) $errors[] = 'Cet identifiant est déjà utilisé.';

    if ($isEdit && $id === $auth->id()) {
        $adminRoleId = null;
        foreach ($roles as $role) if ($role['name'] === 'Administrateur') $adminRoleId = (int) $role['id'];
        if ((int) $form['role_id'] !== $adminRoleId) $errors[] = 'Vous ne pouvez pas retirer votre propre rôle Administrateur.';
        if (!$form['active']) $errors[] = 'Vous ne pouvez pas désactiver votre propre compte.';
    }

    if (!$errors) {
        if ($isEdit) {
            $beforeStmt=$pdo->prepare('SELECT firstname,lastname,username,email,role_id,group_id,active,must_change_password FROM users WHERE id=:id'); $beforeStmt->execute(['id'=>$id]); $before=$beforeStmt->fetch();
            $sql = 'UPDATE users SET firstname=:firstname, lastname=:lastname, username=:username, email=:email, role_id=:role_id, group_id=:group_id, arrival_date=:arrival_date, active=:active, must_change_password=:must_change_password';
            $params = [
                'firstname' => $form['firstname'], 'lastname' => $form['lastname'], 'username' => $form['username'], 'email' => $form['email'],
                'role_id' => $form['role_id'], 'group_id' => $form['group_id'], 'arrival_date' => $form['arrival_date'],
                'active' => $form['active'], 'must_change_password'=>$form['must_change_password'], 'id' => $id,
            ];
            if ($password !== '') {
                $sql .= ', password_hash=:password_hash';
                $params['password_hash'] = password_hash($password, PASSWORD_DEFAULT);
                $sql .= ', password_changed_at=NOW()';
            }
            $sql .= ' WHERE id=:id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $auditService->log((int)$user['id'],'user_updated','user',$id,['before'=>$before,'after'=>$form,'password_reset'=>$password!=='' ]);
            $_SESSION['flash_success'] = 'Utilisateur modifié avec succès.';
        } else {
            $stmt = $pdo->prepare('INSERT INTO users (firstname, lastname, username, email, password_hash, role_id, group_id, arrival_date, active, must_change_password, password_changed_at) VALUES (:firstname,:lastname,:username,:email,:password_hash,:role_id,:group_id,:arrival_date,:active,:must_change_password,NOW())');
            $stmt->execute([
                'firstname' => $form['firstname'], 'lastname' => $form['lastname'], 'username' => $form['username'], 'email' => $form['email'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT), 'role_id' => $form['role_id'],
                'group_id' => $form['group_id'], 'arrival_date' => $form['arrival_date'], 'active' => $form['active'], 'must_change_password'=>$form['must_change_password'],
            ]);
            $newId=(int)$pdo->lastInsertId(); $auditService->log((int)$user['id'],'user_created','user',$newId,['email'=>$form['email'],'role_id'=>$form['role_id']]);
            $_SESSION['flash_success'] = 'Utilisateur créé avec succès.';
        }
        header('Location: admin-users.php');
        exit;
    }
}

require __DIR__ . '/../templates/shared/header.php';
?>
<section class="page-heading user-form-heading">
    <div><span class="badge">Administration</span><h1><?= $isEdit ? 'Modifier un utilisateur' : 'Ajouter un utilisateur' ?></h1><p>Gérez l’identité, l’organisation et la sécurité du compte depuis un formulaire structuré.</p></div>
    <a class="btn secondary" href="admin-users.php"><i class="fa-solid fa-arrow-left"></i> Retour</a>
</section>
<?php if ($errors): ?><div class="alert error"><strong>Le formulaire contient des erreurs :</strong><ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form method="post" class="user-admin-form" autocomplete="off">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>">
    <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= $id ?>"><?php endif; ?>

    <section class="panel user-form-section">
        <div class="form-section-heading"><span class="form-section-icon"><i class="fa-solid fa-address-card"></i></span><div><h2>Identité</h2><p>Informations utilisées pour identifier l’utilisateur dans TicketFlow.</p></div></div>
        <div class="form-grid user-form-grid">
            <div class="field"><label for="firstname">Prénom *</label><input id="firstname" name="firstname" maxlength="80" required value="<?= htmlspecialchars((string)$form['firstname']) ?>"></div>
            <div class="field"><label for="lastname">Nom *</label><input id="lastname" name="lastname" maxlength="80" required value="<?= htmlspecialchars((string)$form['lastname']) ?>"></div>
            <div class="field"><label for="username">Identifiant *</label><input id="username" name="username" maxlength="50" required pattern="[a-z0-9._-]{3,50}" value="<?= htmlspecialchars((string)$form['username']) ?>"><small>Exemple : jdupont</small></div>
            <div class="field"><label for="email">Adresse e-mail *</label><input id="email" name="email" type="email" maxlength="190" required value="<?= htmlspecialchars((string)$form['email']) ?>"></div>
        </div>
    </section>

    <section class="panel user-form-section">
        <div class="form-section-heading"><span class="form-section-icon"><i class="fa-solid fa-sitemap"></i></span><div><h2>Organisation</h2><p>Rôle, groupe et informations d’arrivée dans l’entreprise.</p></div></div>
        <div class="form-grid user-form-grid">
            <div class="field"><label for="role_id">Rôle *</label><select id="role_id" name="role_id" required><option value="">Choisir…</option><?php foreach($roles as $role):?><option value="<?=(int)$role['id']?>" <?=(int)$form['role_id']===(int)$role['id']?'selected':''?>><?=htmlspecialchars($role['name'])?></option><?php endforeach;?></select></div>
            <div class="field"><label for="group_id">Groupe</label><select id="group_id" name="group_id"><option value="">Non attribué</option><?php foreach($groups as $group):?><option value="<?=(int)$group['id']?>" <?=(int)($form['group_id']??0)===(int)$group['id']?'selected':''?>><?=htmlspecialchars($group['name'])?><?=!$group['active']?' (désactivé)':''?></option><?php endforeach;?></select></div>
            <div class="field"><label for="arrival_date">Date d’arrivée</label><input id="arrival_date" name="arrival_date" type="date" value="<?=htmlspecialchars((string)($form['arrival_date']??''))?>"></div>
            <div class="field switch-field"><label class="switch-row"><input type="checkbox" name="active" value="1" <?=$form['active']?'checked':''?>><span class="switch-copy"><strong>Compte actif</strong><small>L’utilisateur peut se connecter à TicketFlow.</small></span></label></div>
        </div>
    </section>

    <section class="panel user-form-section">
        <div class="form-section-heading"><span class="form-section-icon"><i class="fa-solid fa-shield-halved"></i></span><div><h2>Sécurité</h2><p>Mot de passe et obligations de sécurité lors de la prochaine connexion.</p></div></div>
        <div class="form-grid user-form-grid">
            <div class="field"><label for="password"><?= $isEdit ? 'Nouveau mot de passe' : 'Mot de passe *' ?></label><input id="password" name="password" type="password" minlength="12" <?=$isEdit?'':'required'?>> <small><?= $isEdit ? 'Laisser vide pour conserver le mot de passe actuel.' : '12 caractères minimum.' ?></small></div>
            <div class="field"><label for="password_confirm">Confirmation<?= $isEdit ? '' : ' *' ?></label><input id="password_confirm" name="password_confirm" type="password" minlength="12" <?=$isEdit?'':'required'?>></div>
            <div class="field switch-field span-2"><label class="switch-row"><input type="checkbox" name="must_change_password" value="1" <?=!empty($form['must_change_password'])?'checked':''?>><span class="switch-copy"><strong>Forcer le changement de mot de passe</strong><small>L’utilisateur devra définir un nouveau mot de passe à sa prochaine connexion.</small></span></label></div>
        </div>
    </section>

    <div class="user-form-actions"><a class="btn ghost" href="admin-users.php">Annuler</a><button class="btn primary" type="submit"><i class="fa-solid fa-floppy-disk"></i> <?= $isEdit ? 'Enregistrer les modifications' : 'Créer le compte' ?></button></div>
</form>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
