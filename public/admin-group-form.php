<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';
$auth->requireRole('Administrateur');
$user = $auth->user();
$appName = $config['app']['name'] ?? 'TicketFlow';
$id = isset($_GET['id']) ? (int)$_GET['id'] : (int)($_POST['id'] ?? 0);
$isEdit = $id > 0;
$pageTitle = $isEdit ? 'Modifier un groupe' : 'Créer un groupe';
$managers = $pdo->query("SELECT u.id, u.firstname, u.lastname, u.email FROM users u INNER JOIN roles r ON r.id=u.role_id WHERE r.name='Manager' AND u.active=1 ORDER BY u.lastname,u.firstname")->fetchAll();
$form = ['name'=>'','manager_id'=>'','active'=>1]; $errors=[];
if ($isEdit && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt=$pdo->prepare('SELECT id,name,manager_id,active FROM groups_company WHERE id=:id'); $stmt->execute(['id'=>$id]); $existing=$stmt->fetch();
    if(!$existing){http_response_code(404);exit('Groupe introuvable.');} $form=$existing;
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    if(!$csrf->validate($_POST['csrf_token']??null))$errors[]='La session du formulaire a expiré. Rechargez la page.';
    $form['name']=trim((string)($_POST['name']??'')); $form['manager_id']=(int)($_POST['manager_id']??0); $form['active']=isset($_POST['active'])?1:0;
    if(mb_strlen($form['name'])<2||mb_strlen($form['name'])>100)$errors[]='Le nom du groupe doit contenir entre 2 et 100 caractères.';
    $managerIds=array_map(static fn($m)=>(int)$m['id'],$managers); if(!in_array((int)$form['manager_id'],$managerIds,true))$errors[]='Vous devez sélectionner un Manager actif.';
    $check=$pdo->prepare('SELECT id FROM groups_company WHERE name=:name AND id<>:id LIMIT 1');$check->execute(['name'=>$form['name'],'id'=>$id]);if($check->fetch())$errors[]='Un groupe porte déjà ce nom.';
    if(!$errors){
        if($isEdit){$stmt=$pdo->prepare('UPDATE groups_company SET name=:name,manager_id=:manager_id,active=:active WHERE id=:id');$stmt->execute(['name'=>$form['name'],'manager_id'=>$form['manager_id'],'active'=>$form['active'],'id'=>$id]);$_SESSION['flash_success']='Groupe modifié avec succès.';}
        else{$stmt=$pdo->prepare('INSERT INTO groups_company(name,manager_id,active) VALUES(:name,:manager_id,:active)');$stmt->execute(['name'=>$form['name'],'manager_id'=>$form['manager_id'],'active'=>$form['active']]);$_SESSION['flash_success']='Groupe créé avec succès.';}
        header('Location: admin-groups.php');exit;
    }
}
require __DIR__.'/../templates/shared/header.php';
?>
<section class="page-heading group-form-heading page-heading-enhanced"><div><span class="badge"><i class="fa-solid fa-people-group"></i> Administration</span><h1><?= $isEdit ? 'Modifier un groupe' : 'Créer un groupe' ?></h1><p>Le responsable doit posséder le rôle Manager et avoir un compte actif.</p></div><a class="btn secondary" href="admin-groups.php"><i class="fa-solid fa-arrow-left"></i> Retour</a></section>
<?php if(!$managers): ?><div class="alert warning"><strong>Aucun Manager actif disponible.</strong> Créez d’abord un utilisateur avec le rôle Manager depuis la gestion des utilisateurs.</div><?php endif; ?>
<?php if($errors): ?><div class="alert error"><strong>Le formulaire contient des erreurs :</strong><ul><?php foreach($errors as $e):?><li><?=htmlspecialchars($e)?></li><?php endforeach;?></ul></div><?php endif;?>
<section class="panel form-panel group-editor-card group-editor-card-v0133"><div class="form-section-heading"><span class="form-section-icon"><i class="fa-solid fa-people-group"></i></span><div><h2>Informations du groupe</h2><p>Définissez le service, son responsable et son état.</p></div></div><form method="post" class="form-grid group-editor-grid"><input type="hidden" name="csrf_token" value="<?=htmlspecialchars($csrf->token())?>"><?php if($isEdit):?><input type="hidden" name="id" value="<?=$id?>"><?php endif;?>
<div class="field span-2"><label for="name">Nom du groupe *</label><input id="name" name="name" maxlength="100" required value="<?=htmlspecialchars((string)$form['name'])?>" placeholder="Exemple : Commerce"></div>
<div class="field span-2"><label for="manager_id">Manager responsable *</label><select id="manager_id" name="manager_id" required><option value="">Choisir un Manager…</option><?php foreach($managers as $manager):?><option value="<?=(int)$manager['id']?>" <?=(int)$form['manager_id']===(int)$manager['id']?'selected':''?>><?=htmlspecialchars($manager['firstname'].' '.$manager['lastname'].' — '.$manager['email'])?></option><?php endforeach;?></select><small>Pour créer un groupe, il faut donc d’abord disposer d’au moins un compte Manager.</small></div>
<div class="field checkbox-field span-2"><label><input type="checkbox" name="active" value="1" <?=$form['active']?'checked':''?>> Groupe actif</label></div>
<div class="form-actions span-2"><a class="btn ghost" href="admin-groups.php">Annuler</a><button class="btn primary" type="submit" <?=!$managers?'disabled':''?>><?=$isEdit?'Enregistrer les modifications':'Créer le groupe'?></button></div>
</form></section>
<?php require __DIR__.'/../templates/shared/footer.php';?>
