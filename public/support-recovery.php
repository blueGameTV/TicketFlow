<?php
declare(strict_types=1);
require_once __DIR__ . '/../src/bootstrap.php';
$auth->requireRole('Administrateur','IT');
$user=$auth->user(); $appName=$config['app']['name'] ?? 'TicketFlow'; $pageTitle=t('recovery_support.title');
$flashError=$_SESSION['flash_error'] ?? null; unset($_SESSION['flash_error']);
$generatedCode=null; $generatedRequest=null;

if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!$csrf->validate($_POST['csrf_token'] ?? null)){
        $_SESSION['flash_error']=t('recovery.error_expired'); header('Location: support-recovery.php'); exit;
    }
    $id=(int)($_POST['request_id'] ?? 0); $decision=(string)($_POST['decision'] ?? '');
    $q=$pdo->prepare("SELECT prr.*,u.firstname,u.lastname,u.username,u.email,r.name role_name
        FROM password_recovery_requests prr INNER JOIN users u ON u.id=prr.user_id INNER JOIN roles r ON r.id=u.role_id
        WHERE prr.id=:id AND prr.method='support_code' LIMIT 1");
    $q->execute(['id'=>$id]); $request=$q->fetch();
    if(!$request || $request['status']!=='pending'){
        $_SESSION['flash_error']=t('recovery_support.invalid_request'); header('Location: support-recovery.php'); exit;
    }
    if($decision==='reject'){
        $pdo->prepare("UPDATE password_recovery_requests SET status='rejected',reviewed_by=:r,reviewed_at=NOW() WHERE id=:id AND status='pending'")
            ->execute(['r'=>(int)$user['id'],'id'=>$id]);
        $auditService->log((int)$user['id'],'password_recovery_rejected','password_recovery',$id,['requester_username'=>$request['username']]);
        header('Location: support-recovery.php?result=rejected'); exit;
    }
    if($decision==='approve'){
        $digits=str_pad((string)random_int(0,999999),6,'0',STR_PAD_LEFT);
        $formatted=substr($digits,0,3).'-'.substr($digits,3);
        $hash=password_hash($digits,PASSWORD_DEFAULT);
        $u=$pdo->prepare("UPDATE password_recovery_requests SET status='approved',reviewed_by=:r,reviewed_at=NOW(),
            code_hash=:h,code_expires_at=DATE_ADD(NOW(),INTERVAL 15 MINUTE),code_attempts=0 WHERE id=:id AND status='pending'");
        $u->execute(['r'=>(int)$user['id'],'h'=>$hash,'id'=>$id]);
        if($u->rowCount()!==1){$_SESSION['flash_error']=t('recovery_support.invalid_request');header('Location: support-recovery.php');exit;}
        $auditService->log((int)$user['id'],'password_recovery_approved','password_recovery',$id,['requester_username'=>$request['username'],'valid_minutes'=>15]);
        $generatedCode=$formatted; $generatedRequest=$request;
    }
}

$filter=(string)($_GET['status'] ?? 'pending');
if(!in_array($filter,['pending','approved','rejected','completed','all'],true))$filter='pending';
$where=["prr.method='support_code'"]; $params=[];
if($filter!=='all'){ $where[]='prr.status=:status'; $params['status']=$filter; }
$list=$pdo->prepare("SELECT prr.*,u.firstname,u.lastname,u.username,u.email,r.name role_name,g.name group_name,
    CONCAT(rv.firstname,' ',rv.lastname) reviewer_name
    FROM password_recovery_requests prr INNER JOIN users u ON u.id=prr.user_id INNER JOIN roles r ON r.id=u.role_id
    LEFT JOIN groups_company g ON g.id=u.group_id LEFT JOIN users rv ON rv.id=prr.reviewed_by
    WHERE ".implode(' AND ',$where)." ORDER BY FIELD(prr.status,'pending','approved','completed','rejected','expired'),prr.requested_at DESC LIMIT 200");
$list->execute($params); $requests=$list->fetchAll();
$counts=$pdo->query("SELECT SUM(status='pending') pending_count,SUM(status='approved') approved_count FROM password_recovery_requests WHERE method='support_code'")->fetch() ?: [];

require __DIR__ . '/../templates/shared/header.php';
?>
<section class="page-heading tf-support-recovery-heading"><div><span class="badge"><i class="fa-solid fa-headset"></i> <?= htmlspecialchars(t('recovery_support.badge')) ?></span><h1><?= htmlspecialchars(t('recovery_support.title')) ?></h1><p><?= htmlspecialchars(t('recovery_support.subtitle')) ?></p></div>
<div class="tf-support-summary"><div><strong><?= (int)($counts['pending_count'] ?? 0) ?></strong><span><?= htmlspecialchars(t('recovery_support.pending')) ?></span></div><div><strong><?= (int)($counts['approved_count'] ?? 0) ?></strong><span><?= htmlspecialchars(t('recovery_support.approved')) ?></span></div></div></section>
<?php if($flashError): ?><div class="alert error"><?= htmlspecialchars($flashError) ?></div><?php endif; ?>
<?php if(($_GET['result'] ?? '')==='rejected'): ?><div class="alert success"><?= htmlspecialchars(t('recovery_support.rejected_done')) ?></div><?php endif; ?>
<section class="panel tf-support-recovery-filter">
<?php foreach(['pending','approved','completed','rejected','all'] as $f): ?><a class="btn <?= $filter===$f?'primary':'secondary' ?>" href="support-recovery.php?status=<?= urlencode($f) ?>"><?= htmlspecialchars($f==='all'?t('common.all'):t('recovery_support.'.$f)) ?></a><?php endforeach; ?>
</section>
<section class="panel tf-support-recovery-list" id="tf-recovery-feed" data-status="<?= htmlspecialchars($filter) ?>" data-feed-url="api/support-recovery-feed.php">
<div class="tf-recovery-live-loading"><i class="fa-solid fa-circle-notch fa-spin"></i><span><?= htmlspecialchars(t('recovery_support.loading')) ?></span></div>
</section>
<?php if($generatedCode && $generatedRequest): ?>
<div class="tf-code-modal" id="tf-code-modal"><div class="tf-code-modal-backdrop" data-code-modal-close></div><section class="tf-code-modal-card" role="dialog" aria-modal="true"><button class="tf-code-modal-close" data-code-modal-close type="button"><i class="fa-solid fa-xmark"></i></button>
<span class="tf-code-modal-icon"><i class="fa-solid fa-key"></i></span><h2><?= htmlspecialchars(t('recovery_support.code_ready')) ?></h2><p><?= htmlspecialchars(t('recovery_support.code_for',['name'=>trim((string)$generatedRequest['firstname'].' '.(string)$generatedRequest['lastname'])])) ?></p>
<div class="tf-six-code-display" id="tf-six-code"><?= htmlspecialchars($generatedCode) ?></div><button class="btn primary full" type="button" data-copy-six-code><i class="fa-regular fa-copy"></i> <span><?= htmlspecialchars(t('recovery_support.copy_code')) ?></span></button>
<div class="tf-recovery-warning"><i class="fa-solid fa-triangle-exclamation"></i><span><?= htmlspecialchars(t('recovery_support.code_warning')) ?></span></div></section></div>
<script>document.addEventListener('click',async e=>{if(e.target.closest('[data-code-modal-close]'))document.getElementById('tf-code-modal')?.remove();const b=e.target.closest('[data-copy-six-code]');if(!b)return;const v=document.getElementById('tf-six-code')?.textContent?.trim();if(!v)return;try{await navigator.clipboard.writeText(v);b.querySelector('span').textContent=<?= json_encode(t('recovery_support.copied')) ?>}catch(_){}});</script>
<?php endif; ?>
<script>
(() => {
 const feed=document.getElementById('tf-recovery-feed'); if(!feed)return; let lastHtml='',busy=false;
 async function refreshRecoveryRequests(){
  if(busy||document.hidden)return; busy=true;
  try{
   const url=new URL(feed.dataset.feedUrl,window.location.href);
   url.searchParams.set('status',feed.dataset.status||'pending');
   url.searchParams.set('_',Date.now().toString());
   const r=await fetch(url,{credentials:'same-origin',cache:'no-store',headers:{'X-Requested-With':'XMLHttpRequest'}});
   if(r.ok){const html=await r.text();if(html!==lastHtml){lastHtml=html;feed.innerHTML=html;}}
  }catch(e){}finally{busy=false;}
 }
 refreshRecoveryRequests(); setInterval(refreshRecoveryRequests,2500);
 document.addEventListener('visibilitychange',()=>{if(!document.hidden)refreshRecoveryRequests();});
})();
</script>
<?php require __DIR__ . '/../templates/shared/footer.php'; ?>
