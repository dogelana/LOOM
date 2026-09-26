<?php
// @loom-file release=0.15.49 revision=2 policy=package-priority
declare(strict_types=1);
require_once __DIR__.'/_common.php';
$method=$_SERVER['REQUEST_METHOD']??'POST';if($method!=='POST')json_out(['ok'=>false,'error'=>'POST required'],405);$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['ok'=>false,'error'=>'Invalid JSON'],400);$action=(string)($body['action']??'');
try{
  if($action==='request-password-reset')json_out(loom_email_request_password_reset(trim((string)($body['email']??''))));
  if($action==='validate-reset'){json_out(['ok'=>true,'valid'=>(bool)loom_email_validate_reset_token((string)($body['token']??''))]);}
  if($action==='reset-password')json_out(loom_email_consume_password_reset((string)($body['token']??''),(string)($body['password']??'')));
  json_out(['ok'=>false,'error'=>'unknown-action'],400);
}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
