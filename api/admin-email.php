<?php
// @loom-file release=0.15.49 revision=2 policy=package-priority
declare(strict_types=1);
require_once __DIR__.'/_common.php';
if(($_SERVER['REQUEST_METHOD']??'GET')!=='POST')json_out(['ok'=>false,'error'=>'POST required'],405);$body=json_decode((string)file_get_contents('php://input'),true);if(!is_array($body))json_out(['ok'=>false,'error'=>'Invalid JSON'],400);$clientId=safe_token((string)($body['clientId']??''));loom_require_admin($clientId);$action=(string)($body['action']??'status');
try{
  if($action==='status')json_out(['ok'=>true,'email'=>loom_email_status(false)]);
  if($action==='save'){$settings=loom_email_write_settings((array)($body['settings']??[]));loom_admin_audit('system-email.settings.update','system','email','',['enabled'=>$settings['enabled'],'transport'=>$settings['transport']]);json_out(['ok'=>true,'message'=>'System email settings saved.','email'=>loom_email_status(false)]);}
  if($action==='probe')json_out(['ok'=>true,'email'=>loom_email_status(true)]);
  if($action==='send-test'){$to=trim((string)($body['to']??''));$cfg=loom_email_settings(true);$render=loom_email_template($cfg,'LOOM email test','Your LOOM system email configuration is working.','<p style="line-height:1.6;color:#334a3a">This message was sent from LOOM Admin using the currently configured transport.</p>');$res=loom_email_send($to,'LOOM email setup test',$render['html'],$render['text'],'system-test');if(!$res['ok'])throw new RuntimeException($res['message']);json_out(['ok'=>true,'message'=>'Test email accepted by the configured transport.','delivery'=>$res,'email'=>loom_email_status(false)]);}
  json_out(['ok'=>false,'error'=>'unknown-action'],400);
}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
