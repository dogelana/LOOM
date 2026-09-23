<?php
// @loom-file release=0.15.22 revision=1 policy=package-priority
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$marker=dirname(__DIR__).'/.loom-deploying.json';
$data=[];
if(is_file($marker)){
  $raw=@file_get_contents($marker);
  $decoded=is_string($raw)?json_decode($raw,true):null;
  if(is_array($decoded))$data=$decoded;
}
$expires=(float)($data['expires_at_epoch']??0);
$active=!empty($data)&&($expires<=0||$expires>microtime(true));
$retry=max(1,min(10,(int)($data['retry_after']??3)));
header('X-LOOM-Deploying: '.($active?'1':'0'));
if($active)header('Retry-After: '.$retry);
echo json_encode([
  'ok'=>true,
  'deploying'=>$active,
  'state'=>$active?'deploying':'ready',
  'targetRelease'=>$active?(string)($data['target_release']??''):'',
  'transactionId'=>$active?(string)($data['transaction_id']??''):'',
  'retryAfter'=>$retry,
  'serverTimestamp'=>gmdate('c')
],JSON_UNESCAPED_SLASHES);
