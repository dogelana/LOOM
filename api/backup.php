<?php
// @loom-file release=0.15.68 revision=4 policy=package-priority
declare(strict_types=1);
require_once __DIR__.'/_common.php';
require_once __DIR__.'/_backup_restore.php';
$method=$_SERVER['REQUEST_METHOD']??'GET';$action=(string)($_GET['action']??'');
try{
  if($method==='GET'&&$action==='download'){
    $clientId=safe_token((string)($_GET['clientId']??''));$id=loom_backup_safe_id((string)($_GET['id']??''));$meta=loom_backup_meta($id);if(!$meta)throw new RuntimeException('Backup not found.');$type=(string)($meta['type']??'');if(in_array($type,['full','user','guest'],true))loom_backup_require_owner($clientId);elseif(loom_backup_is_project_scoped_type($type)){$proj=safe_slug((string)($meta['project']??''));if($proj==='')throw new RuntimeException('Project backup metadata is incomplete.');loom_backup_require_project($clientId,$proj);}else loom_backup_require_global($clientId);$path=loom_backup_root().'/'.$id.'/'.(string)$meta['file'];if(!is_file($path))throw new RuntimeException('Backup file is missing.');header_remove('Content-Type');header('Content-Type: application/zip');header('Content-Disposition: attachment; filename="'.basename($path).'"');header('Content-Length: '.filesize($path));header('Cache-Control: private, no-store, max-age=0');readfile($path);exit;
  }
  if($method==='POST'&&!empty($_FILES['bundle'])){
    $clientId=safe_token((string)($_POST['clientId']??''));loom_backup_require_global($clientId);$staged=loom_backup_store_upload($_FILES['bundle']);try{$preview=loom_backup_preview($clientId,$staged);}catch(Throwable $e){loom_backup_remove_tree(loom_backup_root().'/'.$staged['importId']);throw $e;}json_out(['ok'=>true,'preview'=>$preview]);
  }
  if($method!=='POST')json_out(['ok'=>false,'error'=>'POST required'],405);$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))$body=[];$action=(string)($body['action']??$action);$clientId=safe_token((string)($body['clientId']??''));
  if($action==='user-list')json_out(['ok'=>true,'users'=>loom_user_portability_all_users($clientId)]);
  if($action==='person-list')json_out(['ok'=>true,'users'=>loom_user_portability_all_users($clientId),'guests'=>loom_guest_portability_all_guests($clientId)]);
  if($action==='create'){$meta=loom_backup_create((string)($body['type']??''),$clientId,$body);$meta['downloadUrl']=web_base_path().'/api/backup.php?action=download&id='.rawurlencode((string)$meta['backupId']).'&clientId='.rawurlencode($clientId);unset($meta['manifest']);json_out(['ok'=>true,'backup'=>$meta]);}
  if($action==='list'){loom_backup_require_global($clientId);$pending=loom_access_is_system_owner($clientId)?loom_backup_pending_db_list($clientId):[];json_out(['ok'=>true,'backups'=>loom_backup_list($clientId),'pendingDatabase'=>$pending]);}
  if($action==='delete'){loom_backup_delete($clientId,(string)($body['id']??''));json_out(['ok'=>true]);}
  if($action==='preview'){$staged=loom_backup_load_import((string)($body['importId']??''));json_out(['ok'=>true,'preview'=>loom_backup_preview($clientId,$staged)]);}
  if($action==='apply'){$staged=loom_backup_load_import((string)($body['importId']??''));json_out(['ok'=>true,'result'=>loom_backup_apply($clientId,$staged,$body)]);}
  if($action==='apply-pending-db')json_out(['ok'=>true,'result'=>loom_backup_apply_pending_db($clientId,(string)($body['id']??''),(string)($body['strategy']??'merge'))]);
  json_out(['ok'=>false,'error'=>'Unknown backup action'],400);
}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],500);}
