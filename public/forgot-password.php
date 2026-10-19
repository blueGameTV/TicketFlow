<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
if ($auth->check()) { header('Location: dashboard.php'); exit; }

$appName=$config['app']['name'] ?? 'TicketFlow';
$method=(string)($_GET['method'] ?? $_POST['method'] ?? '');
if(!in_array($method,['','email','support'],true)) $method='';
$error=$success=null;
$identifier=mb_strtolower(trim((string)($_POST['identifier'] ?? $_GET['identifier'] ?? '')));
$showCodeForm=false;

$recoveryText = static function(string $key, string $fallback): string {
    $value = t($key);
    return ($value === $key || trim((string)$value) === '') ? $fallback : (string)$value;
};

$findAccount=static function(PDO $pdo,string $identifier): ?array {
    $s=$pdo->prepare('SELECT u.id,u.firstname,u.lastname,u.username,u.email,u.active,r.name role_name
        FROM users u INNER JOIN roles r ON r.id=u.role_id
        WHERE u.active=1 AND (LOWER(u.email)=:email OR LOWER(u.username)=:username) LIMIT 1');
    $s->execute(['email'=>$identifier,'username'=>$identifier]);
    return $s->fetch() ?: null;
};

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$csrf->validate($_POST['csrf_token'] ?? null)){
        $error=t('recovery.error_expired');
    } elseif($method==='email'){
        if($identifier===''){
            $error=t('recovery.error_identifier_required');
        } else {
            $account=$findAccount($pdo,$identifier);
            if($account){
                try{
                    $pdo->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=:uid AND used_at IS NULL')
                        ->execute(['uid'=>(int)$account['id']]);
                    $raw=bin2hex(random_bytes(32));
                    $pdo->prepare('INSERT INTO password_reset_tokens(user_id,token_hash,expires_at)
                        VALUES(:uid,:hash,DATE_ADD(NOW(),INTERVAL 30 MINUTE))')
                        ->execute(['uid'=>(int)$account['id'],'hash'=>hash('sha256',$raw)]);
                    $base=rtrim((string)($config['app']['base_url'] ?? ''),'/');
                    $url=($base!==''?$base.'/':'').'reset-password.php?token='.urlencode($raw);
                    $safeUrl=htmlspecialchars($url,ENT_QUOTES,'UTF-8');
                    $safeName=htmlspecialchars(trim((string)$account['firstname'].' '.(string)$account['lastname']),ENT_QUOTES,'UTF-8');
                    $body='<!doctype html><html><body style="font-family:Arial,sans-serif;background:#f3f6fb;color:#172033">'
                        .'<div style="max-width:620px;margin:30px auto;background:white;border:1px solid #dfe6f0;border-radius:14px;padding:26px">'
                        .'<h2 style="color:#3157d5">TicketFlow</h2><p>'.htmlspecialchars(t('recovery.email_hello',['name'=>$safeName]),ENT_QUOTES,'UTF-8').'</p>'
                        .'<p>'.htmlspecialchars(t('recovery.email_body'),ENT_QUOTES,'UTF-8').'</p>'
                        .'<p><a href="'.$safeUrl.'" style="display:inline-block;padding:12px 18px;border-radius:9px;background:#3157d5;color:white;text-decoration:none;font-weight:700">'
                        .htmlspecialchars(t('recovery.email_button'),ENT_QUOTES,'UTF-8').'</a></p>'
                        .'<p style="font-size:12px;color:#738099">'.htmlspecialchars(t('recovery.email_expire'),ENT_QUOTES,'UTF-8').'</p></div></body></html>';
                    $mailQueueService->queueRaw((int)$account['id'],(string)$account['email'],trim((string)$account['firstname'].' '.(string)$account['lastname']),
                        'password_reset',t('recovery.email_subject'),$body);
                    try{$mailQueueService->process(10);}catch(Throwable){}
                    $auditService->log((int)$account['id'],'password_reset_email_requested','user',(int)$account['id']);
                }catch(Throwable){}
            }
            $success=t('recovery.email_generic_success');
            $identifier='';
        }
    } elseif($method==='support'){
        $action=(string)($_POST['support_action'] ?? 'request');
        if($action==='request'){
            if($identifier===''){
                $error=t('recovery.error_identifier_required');
            } else {
                $account=$findAccount($pdo,$identifier);
                if(!$account){
                    $error='TE-103 — '.$recoveryText('recovery.support_user_not_found', 'Aucun utilisateur TicketFlow actif ne correspond à cet identifiant ou à cette adresse e-mail.');
                    $showCodeForm=false;
                } elseif(!in_array((string)$account['role_name'],['Collaborateur','Manager'],true)){
                    $error='TE-104 — '.$recoveryText('recovery.support_role_not_allowed', 'La récupération par code Support est disponible uniquement pour les Collaborateurs et Managers.');
                    $showCodeForm=false;
                } else {
                    try{
                        $q=$pdo->prepare("SELECT id FROM password_recovery_requests
                            WHERE user_id=:uid AND method='support_code' AND status IN ('pending','approved')
                            AND requested_at>=DATE_SUB(NOW(),INTERVAL 30 MINUTE) ORDER BY id DESC LIMIT 1");
                        $q->execute(['uid'=>(int)$account['id']]);
                        if(!$q->fetchColumn()){
                            $i=$pdo->prepare("INSERT INTO password_recovery_requests(user_id,method,status,request_ip,requested_at)
                                VALUES(:uid,'support_code','pending',:ip,NOW())");
                            $i->execute(['uid'=>(int)$account['id'],'ip'=>$auditService->clientIp()]);
                            $auditService->log((int)$account['id'],'password_recovery_support_requested','password_recovery',(int)$pdo->lastInsertId());
                        }
                        $success=t('recovery.support_request_sent');
                        $showCodeForm=true;
                    }catch(Throwable){
                        $error=t('recovery.error_failed');
                        $showCodeForm=false;
                    }
                }
            }
        } elseif($action==='verify'){
            $digits=preg_replace('/\D+/','',(string)($_POST['recovery_code'] ?? '')) ?? '';
            $account=$identifier!=='' ? $findAccount($pdo,$identifier) : null;
            if(strlen($digits)!==6 || !$account || !in_array((string)$account['role_name'],['Collaborateur','Manager'],true)){
                $error=t('recovery.support_code_invalid'); $showCodeForm=true;
            } else {
                $q=$pdo->prepare("SELECT * FROM password_recovery_requests
                    WHERE user_id=:uid AND method='support_code' AND status='approved'
                    AND code_expires_at>NOW() AND code_used_at IS NULL ORDER BY id DESC LIMIT 1");
                $q->execute(['uid'=>(int)$account['id']]); $request=$q->fetch();
                $valid=$request && (int)$request['code_attempts']<5 && password_verify($digits,(string)$request['code_hash']);
                if(!$valid){
                    if($request){
                        $attempts=(int)$request['code_attempts']+1;
                        $pdo->prepare("UPDATE password_recovery_requests SET code_attempts=:a,
                            status=CASE WHEN :a2>=5 THEN 'expired' ELSE status END WHERE id=:id")
                            ->execute(['a'=>$attempts,'a2'=>$attempts,'id'=>(int)$request['id']]);
                    }
                    usleep(300000);
                    $error=t('recovery.support_code_invalid'); $showCodeForm=true;
                } else {
                    session_regenerate_id(true);
                    $_SESSION['password_recovery_verified']=['user_id'=>(int)$account['id'],'request_id'=>(int)$request['id'],'expires_at'=>time()+600];
                    $auditService->log((int)$account['id'],'password_recovery_code_verified','password_recovery',(int)$request['id']);
                    header('Location: reset-password-code.php'); exit;
                }
            }
        }
    }
}
?>
<!doctype html><html lang="<?= htmlspecialchars($translator->locale()) ?>"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light dark">
<title><?= htmlspecialchars(t('recovery.page_title')) ?> - <?= htmlspecialchars($appName) ?></title>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<link rel="stylesheet" href="assets/css/app.css?v=1.3.0-dev"></head>
<body class="auth-page tf-recovery-page"><main class="tf-recovery-layout"><section class="tf-recovery-card">
<a class="auth-back" href="login.php"><i class="fa-solid fa-arrow-left"></i> <?= htmlspecialchars(t('recovery.back_login')) ?></a>
<header class="tf-recovery-heading"><span class="tf-recovery-icon"><i class="fa-solid fa-key"></i></span><div>
<span class="eyebrow"><?= htmlspecialchars(t('recovery.security')) ?></span><h1><?= htmlspecialchars(t('recovery.title')) ?></h1>
<p><?= htmlspecialchars(t('recovery.choose_help')) ?></p></div></header>
<?php if($error): ?><div class="alert error"><i class="fa-solid fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div><?php endif; ?>
<?php if($success): ?><div class="alert success"><i class="fa-solid fa-circle-check"></i> <?= htmlspecialchars($success) ?></div><?php endif; ?>

<?php if($method===''): ?>
<div class="tf-recovery-choice-grid">
<a class="tf-recovery-choice" href="forgot-password.php?method=email"><span class="tf-recovery-choice-icon"><i class="fa-solid fa-envelope"></i></span><strong><?= htmlspecialchars(t('recovery.choice_email')) ?></strong><span><?= htmlspecialchars(t('recovery.choice_email_help')) ?></span><i class="fa-solid fa-arrow-right"></i></a>
<a class="tf-recovery-choice" href="forgot-password.php?method=support"><span class="tf-recovery-choice-icon"><i class="fa-solid fa-headset"></i></span><strong><?= htmlspecialchars(t('recovery.choice_support')) ?></strong><span><?= htmlspecialchars(t('recovery.choice_support_help')) ?></span><i class="fa-solid fa-arrow-right"></i></a>
</div>
<?php elseif($method==='email'): ?>
<div class="tf-recovery-method-head"><a class="btn secondary small" href="forgot-password.php"><i class="fa-solid fa-chevron-left"></i></a><div><h2><?= htmlspecialchars(t('recovery.email_title')) ?></h2><p><?= htmlspecialchars(t('recovery.email_help')) ?></p></div></div>
<?php if(!$success): ?><form method="post" class="auth-form-v013">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="method" value="email">
<div class="field-v013"><label for="identifier"><?= htmlspecialchars(t('recovery.identifier')) ?></label><div class="input-icon"><i class="fa-regular fa-user"></i><input id="identifier" name="identifier" required maxlength="190" value="<?= htmlspecialchars($identifier) ?>" placeholder="<?= htmlspecialchars(t('recovery.identifier_placeholder')) ?>"></div></div>
<button class="btn primary full" type="submit"><i class="fa-solid fa-paper-plane"></i> <?= htmlspecialchars(t('recovery.email_send')) ?></button></form><?php endif; ?>
<?php else: ?>
<div class="tf-recovery-method-head"><a class="btn secondary small" href="forgot-password.php"><i class="fa-solid fa-chevron-left"></i></a><div><h2><?= htmlspecialchars(t('recovery.support_title')) ?></h2><p><?= htmlspecialchars(t('recovery.support_help')) ?></p></div></div>
<?php if(!$showCodeForm): ?><form method="post" class="auth-form-v013">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="method" value="support"><input type="hidden" name="support_action" value="request">
<div class="field-v013"><label for="identifier"><?= htmlspecialchars(t('recovery.identifier')) ?></label><div class="input-icon"><i class="fa-regular fa-user"></i><input id="identifier" name="identifier" required maxlength="190" value="<?= htmlspecialchars($identifier) ?>" placeholder="<?= htmlspecialchars(t('recovery.identifier_placeholder')) ?>"></div></div>
<button class="btn primary full" type="submit"><i class="fa-solid fa-headset"></i> <?= htmlspecialchars(t('recovery.support_request')) ?></button></form>
<?php else: ?><form method="post" class="auth-form-v013 tf-code-verify-form">
<input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="method" value="support"><input type="hidden" name="support_action" value="verify">
<div class="field-v013"><label for="identifier"><?= htmlspecialchars(t('recovery.identifier')) ?></label><div class="input-icon"><i class="fa-regular fa-user"></i><input id="identifier" name="identifier" required maxlength="190" value="<?= htmlspecialchars($identifier) ?>"></div></div>
<div class="field-v013"><label for="recovery_code"><?= htmlspecialchars(t('recovery.support_code')) ?></label><input class="tf-six-code-input" id="recovery_code" name="recovery_code" inputmode="numeric" autocomplete="one-time-code" maxlength="7" placeholder="000-000" required><small><?= htmlspecialchars(t('recovery.support_wait_help')) ?></small></div>
<button class="btn primary full" type="submit"><i class="fa-solid fa-shield-halved"></i> <?= htmlspecialchars(t('recovery.support_verify')) ?></button></form><?php endif; ?>
<?php endif; ?>
</section></main><script>
document.addEventListener('input',e=>{if(e.target.id!=='recovery_code')return;const d=e.target.value.replace(/\D/g,'').slice(0,6);e.target.value=d.length>3?d.slice(0,3)+'-'+d.slice(3):d});
</script></body></html>
