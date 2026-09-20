<?php

declare(strict_types=1);

$root=dirname(__DIR__);$errors=[];$ok=[];
function rcLine(string $prefix,string $message):void{echo $prefix.' '.$message.PHP_EOL;}
$version=trim((string)@file_get_contents($root.'/VERSION'));
rcLine('[INFO]','TicketFlow - contrôle des routes v'.($version!==''?$version:'inconnue'));

$requiredPublicFiles=[
'index.php','login.php','logout.php','forgot-password.php','reset-password.php','dashboard.php','settings.php','change-password.php',
'ticket-create.php','ticket.php','my-tickets.php','it-tickets.php','manager-approvals.php','notifications.php','notification-open.php',
'attachment-download.php','avatar.php','admin-users.php','admin-user-form.php','admin-groups.php','admin-group-form.php',
'admin-exports.php','admin-statistics.php','admin-audit.php','admin-configuration.php','export-users.php','export-tickets.php',
'export-operations.php','about.php','admin-service-alerts.php','admin-maintenance.php','maintenance.php',
];
foreach($requiredPublicFiles as $file) if(!is_file($root.'/public/'.$file)) $errors[]='Route/page manquante : public/'.$file;

$apiFiles=[];$apiDir=$root.'/public/api';
if(is_dir($apiDir)) foreach(new DirectoryIterator($apiDir) as $item) if($item->isFile()&&$item->getExtension()==='php') $apiFiles[]=$item->getFilename();
if(!$apiFiles)$errors[]='Aucune route API PHP détectée dans public/api.';else{$ok[]=count($apiFiles).' route(s) API détectée(s)';}

$criticalAssets=['assets/css/app.css','assets/css/v013.css','assets/css/v110.css','assets/js/ui.js','assets/js/live-ticket.js','assets/js/live-system-state.js','assets/js/custom-selects.js'];
foreach($criticalAssets as $asset) if(!is_file($root.'/public/'.$asset)) $errors[]='Ressource critique manquante : public/'.$asset;
if(!$errors){$ok[]=count($requiredPublicFiles).' pages/routes publiques principales présentes';$ok[]='Ressources CSS/JS critiques présentes';}
foreach($ok as $message)rcLine('[OK]',$message);foreach($errors as $message)rcLine('[ERREUR]',$message);
if($errors){rcLine('[RESULTAT]','Contrôle des routes en échec.');exit(1);}
rcLine('[RESULTAT]','Contrôle des routes terminé sans erreur bloquante.');
