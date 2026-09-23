<?php
// @loom-file release=0.15.18 revision=1 policy=package-priority
// Delegated Access Control API.
declare(strict_types=1);
require __DIR__.'/_common.php';
$method=$_SERVER['REQUEST_METHOD']??'GET';$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))$body=[];
$clientId=safe_token((string)($_REQUEST['clientId']??$body['clientId']??''));$project=safe_slug((string)($_REQUEST['project']??$body['project']??''));$action=(string)($_REQUEST['action']??$body['action']??'status');
if($clientId==='')json_out(['ok'=>false,'error'=>'client-id-required'],400);
if($action==='status'||$action==='list'){
  if($project!=='')loom_require_project_capability($clientId,$project,'project.view');elseif(!loom_access_client_is_loom_admin($clientId))json_out(['ok'=>false,'error'=>'admin-access-required'],403);
  json_out(['ok'=>true,'access'=>loom_access_public_state($clientId,$project)]);
}
if($method!=='POST')json_out(['ok'=>false,'error'=>'POST required'],405);
try{
  if($action==='grant-loom-admin'){loom_access_grant_loom_admin((string)($body['userId']??''),$clientId);json_out(['ok'=>true,'message'=>'LOOM Admin granted','access'=>loom_access_public_state($clientId,$project)]);}
  if($action==='revoke-loom-admin'){loom_access_revoke_loom_admin((string)($body['userId']??''),$clientId);json_out(['ok'=>true,'message'=>'LOOM Admin revoked','access'=>loom_access_public_state($clientId,$project)]);}
  if($action==='grant-project'){$project=safe_slug((string)($body['project']??''));loom_access_grant_project($project,(string)($body['subjectType']??''),(string)($body['subjectId']??''),(string)($body['role']??'project-manager'),$clientId,is_array($body['capabilities']??null)?$body['capabilities']:[]);json_out(['ok'=>true,'message'=>'Project access granted','access'=>loom_access_public_state($clientId,$project)]);}
  if($action==='revoke-project'){$project=safe_slug((string)($body['project']??''));loom_access_revoke_project($project,(string)($body['subjectType']??''),(string)($body['subjectId']??''),$clientId);json_out(['ok'=>true,'message'=>'Project access revoked','access'=>loom_access_public_state($clientId,$project)]);}
  json_out(['ok'=>false,'error'=>'unsupported-action'],400);
}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
