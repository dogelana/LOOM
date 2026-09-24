<?php
// @loom-file release=0.12.11 revision=3 policy=package-priority
require __DIR__.'/_common.php';

if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')json_out(['ok'=>false,'error'=>'POST required'],405);
$body=json_decode((string)file_get_contents('php://input'),true);
if(!is_array($body))json_out(['ok'=>false,'error'=>'Invalid JSON'],400);

$clientId=safe_token((string)($body['clientId']??''));
if($clientId===''||!str_starts_with($clientId,'client_'))json_out(['ok'=>false,'error'=>'Invalid client identity'],400);

$now=server_timestamp();
loom_capture_request_ip($clientId,'');
$guest=loom_guest_ensure_for_client($clientId);

$auth=loom_auth_user();
$linked=$auth?:loom_account_user_for_client($clientId);
$userId=(string)($linked['user_id']??$linked['userId']??'');

$dir=loom_data_dir().'/users';
ensure_dir($dir);
$file=$dir.'/'.hash('sha256',$clientId).'.json';

$profile=loom_db_ready()?loom_db_read_client_profile($clientId):null;
if(!is_array($profile))$profile=read_json_file($file)?:[];

$profile=array_replace([
  'profileId'=>'profile_'.substr(hash('sha256',$clientId),0,16),
  'clientId'=>$clientId,
  'userId'=>$userId?:null,
  'username'=>null,
  'createdAt'=>$now,
  'updatedAt'=>$now,
  'visitor'=>true,
  'visitorSource'=>'loom-home'
],$profile);

$profile['clientId']=$clientId;
$profile['userId']=$userId?:null;
$profile['visitor']=$userId==='';
$profile['visitorSource']=$profile['visitorSource']??'loom-home';
$profile['createdAt']=$profile['createdAt']?:$now;
$profile['updatedAt']=$now;

@file_put_contents($file,json_encode($profile,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
if(loom_db_ready())loom_db_write_client_profile($profile);

// LOOM global identity exists before project membership. This remains owned
// by the temporary client until account registration/sign-in promotes it.
$global=loom_global_profile_ensure($clientId);

json_out([
  'ok'=>true,
  'temporary'=>$userId==='',
  'registered'=>$userId!=='',
  'clientId'=>$clientId,
  'userId'=>$userId?:null,
  'guest'=>loom_guest_public($guest),
  'globalProfile'=>[
    'profileId'=>$global['profileId']??null,
    'username'=>$global['username']??null,
    'avatarMode'=>$global['avatarMode']??'loom-default'
  ],
  'firstSeen'=>$profile['createdAt'],
  'lastSeen'=>$profile['updatedAt']
]);
