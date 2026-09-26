<?php
// @loom-file release=0.15.62 revision=5 policy=package-priority
require __DIR__.'/_common.php';
if(($_SERVER['REQUEST_METHOD']??'POST')!=='POST')json_out(['ok'=>false,'error'=>'POST required'],405);
$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['ok'=>false,'error'=>'Invalid JSON'],400);
$clientId=safe_token((string)($body['clientId']??''));if(!loom_access_is_system_owner($clientId))json_out(['ok'=>false,'error'=>'system-owner-required','message'=>'Permanent identity deletion is restricted to the System Owner.'],403);if(function_exists('loom_access_reconcile_system_owner_identity'))loom_access_reconcile_system_owner_identity($clientId);
$action=(string)($body['action']??'status');$kind=(string)($body['kind']??'guest-one');$subject=safe_token((string)($body['subjectId']??''));$options=is_array($body['options']??null)?$body['options']:[];
try{
  if($action==='status')json_out(['ok'=>true,'databaseReady'=>loom_db_ready(),'orphanClients'=>count(loom_cleanup_orphan_client_ids(false)),'orphanMedia'=>loom_identity_media_orphans(),'systemOwner'=>['userId'=>loom_access_system_owner_user_id()?:null,'clientId'=>loom_access_system_owner_client_id()?:null,'guestId'=>($g=loom_guest_for_client(loom_access_system_owner_client_id()))?safe_token((string)(loom_guest_root($g)['guestId']??'')):null]]);
  if($action==='media-audit')json_out(['ok'=>true]+loom_identity_media_reconcile(false));
  if($action==='media-reconcile'){if(trim((string)($body['confirmPhrase']??''))!=='SWEEP ORPHAN IDENTITY MEDIA')throw new RuntimeException('Confirmation phrase did not match.');$r=loom_identity_media_reconcile(true);loom_admin_audit('identity.orphan-media.reconciled','identity','orphan-media','',['removed'=>$r['removed']]);json_out(['ok'=>true]+$r);}
  if($action==='preview')json_out(loom_identity_cleanup_preview($kind,$subject,$options));
  if($action==='execute')json_out(loom_identity_cleanup_execute($kind,$subject,$options,(string)($body['confirmPhrase']??'')));
  json_out(['ok'=>false,'error'=>'unknown-action'],400);
}catch(Throwable $e){json_out(['ok'=>false,'error'=>'identity-cleanup-failed','message'=>$e->getMessage()],400);}
