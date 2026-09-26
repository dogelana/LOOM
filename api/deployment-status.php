<?php
// @loom-file release=0.15.66 revision=5 policy=package-priority
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

$root=dirname(__DIR__);
$marker=$root.'/.loom-deploying.json';
$data=[];$markerPresent=is_file($marker);
if($markerPresent){
  $raw=@file_get_contents($marker);
  $decoded=is_string($raw)?json_decode($raw,true):null;
  if(is_array($decoded))$data=$decoded;
}
$expires=(float)($data['expires_at_epoch']??0);
$active=!empty($data)&&($expires<=0||$expires>microtime(true));if(!$active&&$markerPresent&&$expires>0&&$expires<=microtime(true)){@unlink($marker);$markerPresent=false;$data=[];}
$retry=max(1,min(10,(int)($data['retry_after']??2)));
$manifest=[];$manifestPath=$root.'/.loom-deployment.json';
if(is_file($manifestPath)){$raw=@file_get_contents($manifestPath);$decoded=is_string($raw)?json_decode($raw,true):null;if(is_array($decoded))$manifest=$decoded;}
$canonical=trim((string)($manifest['loom_release']??''));
header('X-LOOM-Deploying: '.($active?'1':'0'));
if($active)header('Retry-After: '.$retry);
echo json_encode([
  'ok'=>true,
  'deploying'=>$active,
  'state'=>$active?'deploying':'ready',
  'phase'=>$active?(string)($data['phase']??'applying'):'ready',
  'targetRelease'=>$active?(string)($data['target_release']??''):'',
  'canonicalRelease'=>$canonical,
  'transactionId'=>$active?(string)($data['transaction_id']??''):'',
  'retryAfter'=>$retry,
  'markerPresent'=>$markerPresent,
  'leaseExpiresAt'=>$active&&$expires>0?gmdate('c',(int)$expires):null,
  'serverTimestamp'=>gmdate('c')
],JSON_UNESCAPED_SLASHES);
