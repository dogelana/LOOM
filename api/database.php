<?php
// @loom-file release=0.12.08 revision=2 policy=package-priority
require __DIR__.'/_common.php';

if(($_SERVER['REQUEST_METHOD']??'POST')!=='POST')json_out(['ok'=>false,'error'=>'POST required'],405);
$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['ok'=>false,'error'=>'Invalid JSON'],400);
$clientId=safe_token((string)($body['clientId']??''));loom_require_admin($clientId);
$action=(string)($body['action']??'status');

if($action==='status')json_out(['ok'=>true,'database'=>loom_db_status()]);
if($action==='configure'){
  try{$status=loom_db_save_config($body['config']??[]);json_out(['ok'=>true,'database'=>$status,'message'=>'Database connection saved and verified.']);}
  catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage(),'database'=>loom_db_status()],400);}
}
if($action==='initialize'){
  try{$status=loom_db_initialize();json_out(['ok'=>true,'database'=>$status,'message'=>'LOOM schema initialized.']);}
  catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage(),'database'=>loom_db_status()],500);}
}
if($action==='migrate'){
  try{$counts=loom_db_migrate_local();json_out(['ok'=>true,'database'=>loom_db_status(),'migrated'=>$counts,'message'=>'Temporary LOOM data was copied into SQL. Local files remain as a safety cache.']);}
  catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage(),'database'=>loom_db_status()],500);}
}

if($action==='integrity-scan'){
  try{json_out(['ok'=>true,'integrity'=>loom_db_integrity_scan($clientId),'database'=>loom_db_status()]);}
  catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage(),'database'=>loom_db_status()],500);}
}
if($action==='integrity-backup'){
  try{$backup=loom_db_integrity_backup();json_out(['ok'=>true,'backup'=>$backup,'message'=>'Protected server-side SQL snapshot created before any repair.']);}
  catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],500);}
}
if($action==='integrity-repair'){
  if((string)($body['confirm']??'')!=='CONFIRM')json_out(['ok'=>false,'error'=>'Type CONFIRM to run safe database reconciliation.'],409);
  try{$result=loom_db_integrity_repair($clientId);json_out(['ok'=>true,'repair'=>$result,'database'=>loom_db_status(),'message'=>'Safe identity mappings were reconciled. No user/project records were deleted.']);}
  catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage(),'database'=>loom_db_status()],500);}
}
json_out(['ok'=>false,'error'=>'Unknown database action'],400);
