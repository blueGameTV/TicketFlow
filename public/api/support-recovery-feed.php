<?php
declare(strict_types=1);
require_once __DIR__ . '/../../src/bootstrap.php';
$auth->requireRole('Administrateur','IT');
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store');
$filter=(string)($_GET['status'] ?? 'pending');
if(!in_array($filter,['pending','approved','rejected','completed','all'],true))$filter='pending';
$where=["prr.method='support_code'"]; $params=[];
if($filter!=='all'){ $where[]='prr.status=:status'; $params['status']=$filter; }
$list=$pdo->prepare("SELECT prr.*,u.firstname,u.lastname,u.username,u.email,r.name role_name,g.name group_name,CONCAT(rv.firstname,' ',rv.lastname) reviewer_name FROM password_recovery_requests prr INNER JOIN users u ON u.id=prr.user_id INNER JOIN roles r ON r.id=u.role_id LEFT JOIN groups_company g ON g.id=u.group_id LEFT JOIN users rv ON rv.id=prr.reviewed_by WHERE ".implode(' AND ',$where)." ORDER BY FIELD(prr.status,'pending','approved','completed','rejected','expired'),prr.requested_at DESC LIMIT 200");
$list->execute($params); $requests=$list->fetchAll();
if(!$requests): ?>
<div class="tf-recovery-live-empty"><span class="tf-recovery-live-empty-icon"><i class="fa-solid fa-key"></i></span><strong><?= htmlspecialchars(t('recovery_support.empty')) ?></strong><span><?= htmlspecialchars(t('recovery_support.empty_help')) ?></span></div>
<?php else: foreach($requests as $r): ?>
<article class="tf-recovery-request-card status-<?= htmlspecialchars((string)$r['status']) ?>">
<div class="tf-recovery-request-icon"><i class="fa-solid fa-key"></i></div>
<div class="tf-recovery-request-main"><div class="tf-recovery-request-top"><span class="tf-recovery-request-number">PWD-<?= str_pad((string)$r['id'],6,'0',STR_PAD_LEFT) ?></span><span class="status-pill <?= htmlspecialchars((string)$r['status']) ?>"><?= htmlspecialchars(t('recovery_request.status.'.(string)$r['status'])) ?></span></div>
<h3><?= htmlspecialchars(t('recovery_support.request_title')) ?></h3><p><?= htmlspecialchars(trim((string)$r['firstname'].' '.(string)$r['lastname'])) ?> · @<?= htmlspecialchars((string)$r['username']) ?> · <?= htmlspecialchars((string)$r['email']) ?></p>
<div class="tf-recovery-request-meta"><span><i class="fa-solid fa-user-tag"></i> <?= htmlspecialchars(t('role.'.(string)$r['role_name'])) ?></span><span><i class="fa-solid fa-users"></i> <?= htmlspecialchars((string)($r['group_name'] ?: t('common.unassigned'))) ?></span><span><i class="fa-regular fa-clock"></i> <?= htmlspecialchars(date('d/m/Y H:i',strtotime((string)$r['requested_at']))) ?></span></div>
<?php if(!empty($r['reviewer_name'])): ?><small><?= htmlspecialchars(t('recovery_support.reviewed_by',['name'=>$r['reviewer_name']])) ?></small><?php endif; ?></div>
<?php if($r['status']==='pending'): ?><div class="tf-recovery-request-actions">
<form method="post" action="support-recovery.php"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="decision" value="approve"><button class="btn primary" type="submit"><i class="fa-solid fa-check"></i> <?= htmlspecialchars(t('recovery_support.approve')) ?></button></form>
<form method="post" action="support-recovery.php" onsubmit="return confirm('<?= htmlspecialchars(t('recovery_support.reject_confirm'),ENT_QUOTES) ?>')"><input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrf->token()) ?>"><input type="hidden" name="request_id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="decision" value="reject"><button class="btn danger" type="submit"><i class="fa-solid fa-xmark"></i> <?= htmlspecialchars(t('recovery_support.reject')) ?></button></form>
</div><?php endif; ?></article>
<?php endforeach; endif; ?>
