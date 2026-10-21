<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
if($auth->check()){header('Location: dashboard.php');exit;}
$appName=$config['app']['name'] ?? 'TicketFlow'; $v=$_SESSION['password_recovery_verified'] ?? null;
if(!is_array($v)||empty($v['user_id'])||empty($v['request_id'])||(int)($v['expires_at']??0)<time()){unset($_SESSION['password_recovery_verified']);header('Location: forgot-password.php?method=support');exit;}
$uid=(int)$v['user_id'];$rid=(int)$v['request_id'];$error=null;
$q=$pdo->prepare("SELECT id FROM password_recovery_requests WHERE id=:id AND user_id=:uid AND status='approved' AND code_expires_at>NOW() AND code_used_at IS NULL LIMIT 1");
$q->execute(['id'=>$rid,'uid'=>$uid]);if(!$q->fetchColumn()){unset($_SESSION['password_recovery_verified']);header('Location: forgot-password.php?method=support');exit;}
if($_SERVER['REQUEST_METHOD']==='POST'){
 $p=(string)($_POST['password']??'');$c=(string)($_POST['password_confirm']??'');
 if(!$csrf->validate($_POST['csrf_token']??null))$error=t('recovery.error_expired');
 elseif(strlen($p)<12)$error=t('password.error_length');
 elseif(!preg_match('/[A-Z]/',$p)||!preg_match('/[a-z]/',$p)||!preg_match('/\d/',$p))$error=t('password.error_complexity');
 elseif($p!==$c)$error=t('password.error_match');
 else{
  $pdo->beginTransaction();try{
   $pdo->prepare('UPDATE users SET password_hash=:h,password_changed_at=NOW(),must_change_password=0,locked_until=NULL WHERE id=:uid')->execute(['h'=>password_hash($p,PASSWORD_DEFAULT),'uid'=>$uid]);
   $pdo->prepare("UPDATE password_recovery_requests SET status='completed',code_used_at=NOW(),completed_at=NOW() WHERE id=:id AND user_id=:uid AND status='approved'")->execute(['id'=>$rid,'uid'=>$uid]);
   $pdo->prepare('UPDATE password_reset_tokens SET used_at=NOW() WHERE user_id=:uid AND used_at IS NULL')->execute(['uid'=>$uid]);
   $pdo->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=:uid AND revoked_at IS NULL')->execute(['uid'=>$uid]);
   $pdo->commit();$auditService->log($uid,'password_recovery_completed','password_recovery',$rid,['method'=>'support_code']);
   unset($_SESSION['password_recovery_verified']);$_SESSION['login_notice']=t('recovery.password_changed_login');header('Location: login.php?password_reset=1');exit;
  }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$error=t('recovery.error_failed');}
 }
}
?>
<!doctype html><html lang="<?= htmlspecialchars($translator->locale()) ?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="color-scheme" content="light dark"><title><?= htmlspecialchars(t('recovery.new_password_title')) ?> - <?= htmlspecialchars($appName) ?></title><link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css"><link rel="stylesheet" href="assets/css/app.css?v=1.3.0-dev.6-rework"></head>
<body class="auth-page tf-recovery-page"><main class="tf-recovery-layout"><section class="tf-recovery-card"><header class="tf-recovery-heading"><span class="tf-recovery-icon"><i class="fa-solid fa-lock"></i></span><div><span class="eyebrow"><?= htmlspecialchars(t('recovery.code_verified')) ?></span><h1><?= htmlspecialchars(t('recovery.new_password_title')) ?></h1><p><?= htmlspecialchars(t('recovery.new_password_help')) ?></p></div></header>
<?php if($error): ?><div class="alert error"><?= htmlspecialchars($error) ?></div><?php endif; ?>
<form method="post" class="auth-form-v013"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><div class="tf-recovery-password-grid">
<div class="field-v013"><label for="password"><?= htmlspecialchars(t('password.new')) ?></label><div class="input-icon password-input-wrap"><i class="fa-solid fa-lock"></i><input id="password" name="password" type="password" minlength="12" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle="password"><i class="fa-regular fa-eye"></i></button></div></div>
<div class="field-v013"><label for="password_confirm"><?= htmlspecialchars(t('password.confirm')) ?></label><div class="input-icon password-input-wrap"><i class="fa-solid fa-lock"></i><input id="password_confirm" name="password_confirm" type="password" minlength="12" required autocomplete="new-password"><button class="password-toggle" type="button" data-password-toggle="password_confirm"><i class="fa-regular fa-eye"></i></button></div></div></div>
<div class="tf-password-rules"><span>✓ <?= htmlspecialchars(t('password.rule_length')) ?></span><span>✓ <?= htmlspecialchars(t('password.rule_upper')) ?></span><span>✓ <?= htmlspecialchars(t('password.rule_lower')) ?></span><span>✓ <?= htmlspecialchars(t('password.rule_digit')) ?></span></div>
<button class="btn primary full" type="submit"><i class="fa-solid fa-check"></i> <?= htmlspecialchars(t('recovery.save_password')) ?></button></form></section></main><script src="assets/js/ui.js?v=1.3.0-dev.6-rework" defer></script></body></html>
