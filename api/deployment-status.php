<?php
// @loom-file release=0.15.67 revision=6 policy=package-priority
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
$now=microtime(true);$expires=(float)($data['expires_at_epoch']??0);$schema=(string)($data['schema']??'');$active=false;$staleReason=null;
if(!empty($data)){
  if($expires>0)$active=$expires>$now;
  elseif($schema==='loom-deployment-gate/v2')$staleReason='v2-marker-missing-lease';
  else{$updated=(float)($data['updated_epoch']??0);$mtime=(float)(@filemtime($marker)?:0);$fresh=max($updated,$mtime);$active=$fresh>0&&($now-$fresh)<=180;if(!$active)$staleReason='legacy-marker-expired';}
}
if($markerPresent&&!$active){@unlink($marker);$markerPresent=false;$data=[];}
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
  'staleMarkerRecovered'=>$staleReason,
  'leaseExpiresAt'=>$active&&$expires>0?gmdate('c',(int)$expires):null,
  'serverTimestamp'=>gmdate('c')
],JSON_UNESCAPED_SLASHES);
