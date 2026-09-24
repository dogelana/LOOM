<?php
// @loom-file release=0.12.08 revision=2 policy=package-priority
require __DIR__.'/_common.php';
if($_SERVER['REQUEST_METHOD']!=='POST')json_out(['error'=>'POST required'],405);
$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['error'=>'Invalid JSON'],400);
$project=safe_slug((string)($body['project']??''));if($project===''||!project_dir($project))json_out(['error'=>'Invalid project'],400);
$clientId=safe_token((string)($body['clientId']??''));if($clientId!=='')loom_capture_request_ip($clientId,$project);loom_enforce_project_access($project,$clientId);
// IP addresses are stored separately as network metadata; never as identity/authentication proof.
$event=append_project_event($project,$body);
json_out(['ok'=>true,'id'=>$event['id'],'serverTimestamp'=>$event['serverTimestamp']]);
