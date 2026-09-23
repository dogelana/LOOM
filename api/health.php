<?php
// @loom-file release=0.12.16 revision=5 policy=package-priority
require __DIR__.'/_common.php';
require __DIR__.'/_html_framer.php';
$project=safe_slug((string)($_GET['project']??''));$checks=[];
function loom_health_add(array &$checks,string $id,bool $ok,string $message,array $meta=[]): void {$checks[]=['id'=>$id,'ok'=>$ok,'status'=>$ok?'healthy':'attention','message'=>$message,'meta'=>$meta];}
loom_health_add($checks,'release-manifest',is_file(root_dir().'/.loom-deployment.json'),'Canonical release manifest '.(is_file(root_dir().'/.loom-deployment.json')?'available':'missing'),['version'=>loom_release_version()]);
$instanceRoot=loom_instance_root();$instanceMarker=$instanceRoot.'/.loom-instance.json';
loom_health_add($checks,'instance-vault',is_dir($instanceRoot)&&is_writable($instanceRoot)&&is_file($instanceMarker),'Clean Instance Vault available',['protocol'=>'clean-instance','root'=>'instance/','marker'=>basename($instanceMarker)]);
loom_health_add($checks,'html-framer',loom_html_framer_zip_supported(),'HTML Framer ZIP support '.(loom_html_framer_zip_supported()?'available':'unavailable'),['zipArchive'=>class_exists('ZipArchive'),'pharData'=>class_exists('PharData')]);
$db=loom_db_status();$dbReady=(bool)($db['initialized']??false);loom_health_add($checks,'database',$dbReady,$dbReady?'Persistent SQL storage connected':'Running with durable-local fallback',['mode'=>$dbReady?'database':'durable-local','connected'=>(bool)($db['connected']??false),'initialized'=>$dbReady]);
loom_health_add($checks,'identity-store',is_dir(loom_data_dir().'/identity'),'Identity store available');
$ledger=loom_migration_bootstrap();loom_health_add($checks,'migration-ledger',is_file(loom_migration_ledger_file()),'Schema evolution ledger available',['count'=>count($ledger['migrations']??[])]);
loom_health_add($checks,'guest-profile-store',function_exists('loom_guest_profiles_file')&&is_file(loom_guest_profiles_file())||function_exists('loom_guest_profiles_store'),'Explicit guest-profile lineage foundation available');
loom_health_add($checks,'replay-capture',is_file(root_dir().'/engine/interaction-capture.js')&&is_file(__DIR__.'/replay.php'),'Masked session replay capture foundation available');
loom_health_add($checks,'capability-contracts',function_exists('loom_capability_contract_status'),'Capability contract system available');
loom_health_add($checks,'sandbox-guardrails',function_exists('loom_project_sandbox_descriptor'),'Project sandbox guardrails available');
if($project!=='')loom_health_add($checks,'project',project_dir($project)!==null,project_dir($project)?'Project sandbox available':'Project not found',['project'=>$project]);
$hardFailures=array_filter($checks,fn($c)=>!$c['ok']&&!in_array($c['id'],['database'],true));$ok=!$hardFailures;json_out(['ok'=>true,'status'=>$ok?'healthy':'degraded','degradationMode'=>$ok?'normal':'graceful','checks'=>$checks,'serverTimestamp'=>server_timestamp()]);
