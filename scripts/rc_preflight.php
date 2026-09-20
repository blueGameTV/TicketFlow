<?php

declare(strict_types=1);

$root=dirname(__DIR__);
$checks=['healthcheck.php'=>'Diagnostic application','security_audit.php'=>'Audit sécurité','route_check.php'=>'Contrôle des routes','release_check.php'=>'Contrôle de release'];
function pfLine(string $prefix,string $message):void{echo $prefix.' '.$message.PHP_EOL;}
$version=trim((string)@file_get_contents(dirname(__DIR__).'/VERSION'));
pfLine('[INFO]','TicketFlow v'.($version!==''?$version:'inconnue').' - préflight release');
$failed=false;
foreach($checks as $script=>$label){
    $path=$root.'/scripts/'.$script;
    if(!is_file($path)){pfLine('[ERREUR]',$label.' : script absent ('.$script.')');$failed=true;continue;}
    pfLine('[INFO]','--- '.$label.' ---');$command=escapeshellarg(PHP_BINARY).' '.escapeshellarg($path).' 2>&1';passthru($command,$code);
    if($code!==0){pfLine('[ERREUR]',$label.' a échoué avec le code '.$code.'.');$failed=true;}else pfLine('[OK]',$label.' validé.');
}
if($failed){pfLine('[RESULTAT]','Release non validée : au moins un contrôle a échoué.');exit(1);}
pfLine('[RESULTAT]','Préflight terminé : aucun contrôle bloquant détecté.');
