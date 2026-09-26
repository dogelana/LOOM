<?php
// @loom-file release=0.15.27 revision=2 policy=package-priority
declare(strict_types=1);
require_once __DIR__.'/_common.php';
header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store, max-age=0');
$action=(string)($_GET['action']??$_POST['action']??'');
$body=[];if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){$raw=file_get_contents('php://input');$body=json_decode($raw?:'{}',true);if(!is_array($body))$body=[];$action=(string)($body['action']??$action);}
try{
  if($action==='create'){json_out(['ok'=>true,'share'=>loom_referral_create((string)($body['clientId']??''),(string)($body['project']??''),$body['targetUrl']??null)]);}
  if($action==='hit'){$code=(string)($body['code']??$_GET['code']??'');$visit=(string)($body['visitKey']??$_GET['visitKey']??'');$share=loom_referral_hit($code,$visit);json_out(['ok'=>(bool)$share,'share'=>$share],$share?200:404);}
  if($action==='accept'){json_out(['ok'=>true]+loom_referral_accept((string)($body['code']??''),(string)($body['clientId']??''),(string)($body['project']??''),(string)($body['path']??'')));}
  if($action==='stats'){$client=(string)($body['clientId']??$_GET['clientId']??'');json_out(['ok'=>true,'stats'=>loom_referral_stats($client)]);}
  if($action==='admin-list'){if(!loom_request_is_admin())json_out(['ok'=>false,'error'=>'admin-required'],403);json_out(['ok'=>true,'stats'=>loom_referral_admin_summary()]);}
  json_out(['ok'=>false,'error'=>'unknown-action'],400);
}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
