<?php
// @loom-file release=0.15.47 revision=1 policy=package-priority
// LOOM v0.15.47 — privacy-bounded anonymous Continuity Clusters.
declare(strict_types=1);

/**
 * Continuity is intentionally NOT authentication.
 *
 * It lets a fresh browser resume harmless anonymous LOOM state when several
 * independent weak signals line up with one and only one recent unattached
 * guest cluster. No raw user-agent string or permanent hardware identifier is
 * stored. The browser sends coarse device/display traits; LOOM stores hashes
 * of normalized traits plus already-recorded network metadata.
 *
 * A continuity match may restore anonymous profile/project state. It must never
 * grant account, Admin, purchase, private-message, or other authenticated
 * authority. Durable account verification remains the authority boundary.
 */

function loom_continuity_file(): string { return loom_identity_dir().'/continuity-clusters.json'; }
function loom_continuity_default_store(): array {
  return ['schemaVersion'=>'1.0','clusters'=>[],'clients'=>[],'decisions'=>[],'updatedAt'=>null];
}
function loom_continuity_read_store(): array {
  return array_replace(loom_continuity_default_store(),read_json_file(loom_continuity_file())?:[]);
}
function loom_continuity_mutate(callable $fn): mixed {
  $file=loom_continuity_file();ensure_dir(dirname($file));$fh=@fopen($file,'c+');if(!$fh)throw new RuntimeException('Continuity store is unavailable.');
  try{
    if(!flock($fh,LOCK_EX))throw new RuntimeException('Continuity store lock failed.');
    rewind($fh);$raw=stream_get_contents($fh);$s=$raw!==false&&trim($raw)!==''?json_decode($raw,true):null;if(!is_array($s))$s=loom_continuity_default_store();$s=array_replace(loom_continuity_default_store(),$s);
    $result=$fn($s);$s['schemaVersion']='1.0';$s['updatedAt']=server_timestamp();$json=json_encode($s,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
    rewind($fh);ftruncate($fh,0);fwrite($fh,$json);fflush($fh);flock($fh,LOCK_UN);return $result;
  } finally { fclose($fh); }
}
function loom_continuity_text(mixed $v,int $max=80): string {
  $v=preg_replace('/[\x00-\x1F\x7F]/u','',trim((string)$v))??'';return function_exists('mb_substr')?mb_substr($v,0,$max,'UTF-8'):substr($v,0,$max);
}
function loom_continuity_int(mixed $v,int $min,int $max): int { return max($min,min($max,(int)$v)); }
function loom_continuity_float(mixed $v,float $min,float $max): float { $n=(float)$v;return max($min,min($max,round($n,2))); }
function loom_continuity_signal_hash(array $parts): string { return hash('sha256',json_encode($parts,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)); }
function loom_continuity_normalize_signals(mixed $input): array {
  $x=is_array($input)?$input:[];
  $langs=[];foreach(array_slice((array)($x['languages']??[]),0,4) as $v){$v=strtolower(loom_continuity_text($v,20));if($v!=='')$langs[$v]=true;}
  $language=strtolower(loom_continuity_text($x['language']??'',20));if($language!=='')$langs[$language]=true;
  $platform=strtolower(loom_continuity_text($x['platform']??'',40));
  $timezone=loom_continuity_text($x['timezone']??'',64);
  $browserFamily=strtolower(loom_continuity_text($x['browserFamily']??'',28));
  $screenW=loom_continuity_int($x['screenWidth']??0,0,10000);$screenH=loom_continuity_int($x['screenHeight']??0,0,10000);
  $availW=loom_continuity_int($x['availWidth']??0,0,10000);$availH=loom_continuity_int($x['availHeight']??0,0,10000);
  $dpr=loom_continuity_float($x['devicePixelRatio']??1,0.5,8);$depth=loom_continuity_int($x['colorDepth']??0,0,64);
  $touch=loom_continuity_int($x['maxTouchPoints']??0,0,20);$cores=loom_continuity_int($x['hardwareConcurrency']??0,0,128);$mem=loom_continuity_float($x['deviceMemory']??0,0,64);
  $mobile=!empty($x['mobile']);
  $display=['w'=>$screenW,'h'=>$screenH,'aw'=>$availW,'ah'=>$availH,'dpr'=>$dpr,'depth'=>$depth,'touch'=>$touch];
  $platformParts=['platform'=>$platform,'mobile'=>$mobile,'touch'=>$touch,'cores'=>$cores,'memory'=>$mem];
  $locale=['timezone'=>$timezone,'languages'=>array_keys($langs)];
  $hardware=['display'=>$display,'platform'=>$platformParts,'locale'=>$locale];
  $usable=$screenW>0&&$screenH>0&&$platform!==''&&$timezone!=='';
  return [
    'usable'=>$usable,
    'hardwareHash'=>$usable?loom_continuity_signal_hash($hardware):'',
    'displayHash'=>($screenW>0&&$screenH>0)?loom_continuity_signal_hash($display):'',
    'platformHash'=>$platform!==''?loom_continuity_signal_hash($platformParts):'',
    'localeHash'=>$timezone!==''?loom_continuity_signal_hash($locale):'',
    'browserFamily'=>$browserFamily?:'unknown',
    'mobile'=>$mobile,
  ];
}
function loom_continuity_cluster_id(string $guestId): string { return 'cont_'.substr(hash('sha256','loom-continuity|'.$guestId),0,24); }
function loom_continuity_cluster_for_guest_in_store(array $s,string $guestId): ?array {
  foreach(($s['clusters']??[]) as $c)if(in_array($guestId,(array)($c['guestIds']??[]),true))return is_array($c)?$c:null;return null;
}
function loom_continuity_cluster_for_client_in_store(array $s,string $clientId): ?array {
  $cid=(string)($s['clients'][$clientId]['clusterId']??'');$c=$cid!==''?($s['clusters'][$cid]??null):null;return is_array($c)?$c:null;
}
function loom_continuity_ensure_cluster(array &$s,string $guestId): array {
  $guestId=safe_token($guestId);$existing=loom_continuity_cluster_for_guest_in_store($s,$guestId);if($existing)return $existing;
  $id=loom_continuity_cluster_id($guestId);$now=server_timestamp();$c=['clusterId'=>$id,'canonicalGuestId'=>$guestId,'guestIds'=>[$guestId],'profileIds'=>[],'clientIds'=>[],'installationIds'=>[],'claimedUserId'=>null,'claimedAt'=>null,'createdAt'=>$now,'updatedAt'=>$now];$s['clusters'][$id]=$c;return $c;
}
function loom_continuity_public_cluster(array $c): array {
  return ['clusterId'=>$c['clusterId']??null,'claimed'=>(bool)($c['claimedUserId']??null),'clientCount'=>count(array_unique((array)($c['clientIds']??[]))),'profileCount'=>count(array_unique((array)($c['profileIds']??[]))),'installationCount'=>count(array_unique((array)($c['installationIds']??[]))),'updatedAt'=>$c['updatedAt']??null];
}
function loom_continuity_observe(string $installationId,string $clientId,string $profileId,array $signals,string $source='active'): array {
  $installationId=loom_guest_installation_id($installationId);$clientId=safe_token($clientId);$profileId=safe_token($profileId);if($installationId===''||$clientId==='')return ['observed'=>false];
  $sig=loom_continuity_normalize_signals($signals);if(!$sig['usable'])return ['observed'=>false,'reason'=>'insufficient-signals'];
  $g=loom_guest_for_client($clientId);if(!$g)$g=loom_guest_ensure_for_client($clientId);$g=loom_guest_root($g);$guestId=(string)$g['guestId'];$ipObs=loom_capture_request_ip($clientId,'');$ip=loom_valid_ip((string)($ipObs['ip']??''));$now=server_timestamp();
  return loom_continuity_mutate(function(array &$s)use($installationId,$clientId,$profileId,$sig,$guestId,$ip,$now,$source){
    $c=loom_continuity_ensure_cluster($s,$guestId);$id=(string)$c['clusterId'];$c['guestIds']=array_values(array_unique(array_merge((array)$c['guestIds'],[$guestId])));$c['clientIds']=array_values(array_unique(array_merge((array)$c['clientIds'],[$clientId])));if($profileId!=='')$c['profileIds']=array_values(array_unique(array_merge((array)$c['profileIds'],[$profileId])));$c['installationIds']=array_values(array_unique(array_merge((array)$c['installationIds'],[$installationId])));$c['updatedAt']=$now;$s['clusters'][$id]=$c;
    $prior=is_array($s['clients'][$clientId]??null)?$s['clients'][$clientId]:[];$s['clients'][$clientId]=[
      'clientId'=>$clientId,'clusterId'=>$id,'guestId'=>$guestId,'profileId'=>$profileId?:($prior['profileId']??null),'installationId'=>$installationId,
      'hardwareHash'=>$sig['hardwareHash'],'displayHash'=>$sig['displayHash'],'platformHash'=>$sig['platformHash'],'localeHash'=>$sig['localeHash'],'browserFamily'=>$sig['browserFamily'],'mobile'=>$sig['mobile'],'ip'=>$ip,
      'firstSeen'=>$prior['firstSeen']??$now,'lastSeen'=>$now,'seenCount'=>(int)($prior['seenCount']??0)+1,'source'=>$source
    ];
    return ['observed'=>true,'cluster'=>loom_continuity_public_cluster($c)];
  });
}
function loom_continuity_ts(?string $value): int { $t=$value?strtotime($value):false;return $t===false?0:$t; }
function loom_continuity_score(array $now,array $obs,?string $ip): array {
  $score=0;$signals=[];$hardware=$now['hardwareHash']!==''&&hash_equals((string)$now['hardwareHash'],(string)($obs['hardwareHash']??''));
  if(!$hardware)return ['score'=>0,'hardwareMatch'=>false,'signals'=>[]];$score+=58;$signals[]='hardware-signature';
  if($now['displayHash']!==''&&hash_equals((string)$now['displayHash'],(string)($obs['displayHash']??''))){$score+=8;$signals[]='display';}
  if($now['platformHash']!==''&&hash_equals((string)$now['platformHash'],(string)($obs['platformHash']??''))){$score+=8;$signals[]='platform';}
  if($now['localeHash']!==''&&hash_equals((string)$now['localeHash'],(string)($obs['localeHash']??''))){$score+=6;$signals[]='locale';}
  $oldIp=loom_valid_ip((string)($obs['ip']??''));if($ip&&$oldIp&&hash_equals($ip,$oldIp)){$score+=8;$signals[]='network';}
  $age=max(0,time()-loom_continuity_ts((string)($obs['lastSeen']??'')));if($age<=3600){$score+=10;$signals[]='recent-1h';}elseif($age<=86400){$score+=8;$signals[]='recent-24h';}elseif($age<=604800){$score+=5;$signals[]='recent-7d';}elseif($age<=2592000){$score+=2;$signals[]='recent-30d';}
  return ['score'=>$score,'hardwareMatch'=>true,'signals'=>$signals];
}
function loom_continuity_match(string $installationId,string $clientId,array $signals): array {
  $installationId=loom_guest_installation_id($installationId);$clientId=safe_token($clientId);if($installationId===''||$clientId==='')return ['matched'=>false,'reason'=>'invalid-context'];
  if(loom_account_user_for_client($clientId))return ['matched'=>false,'reason'=>'already-authenticated'];
  if(loom_guest_for_client($clientId))return ['matched'=>false,'reason'=>'existing-guest'];
  $sig=loom_continuity_normalize_signals($signals);if(!$sig['usable'])return ['matched'=>false,'reason'=>'insufficient-signals'];
  $ipObs=loom_request_ip_observation();$ip=loom_valid_ip((string)($ipObs['ip']??''));$s=loom_continuity_read_store();$candidates=[];$hardwareClusters=[];
  foreach(($s['clients']??[]) as $obs){if(!is_array($obs)||($obs['installationId']??'')===$installationId)continue;$cid=(string)($obs['clusterId']??'');$cluster=$cid!==''?($s['clusters'][$cid]??null):null;if(!is_array($cluster))continue;
    $sc=loom_continuity_score($sig,$obs,$ip);if(!$sc['hardwareMatch'])continue;$hardwareClusters[$cid]=true;
    // Claimed/attached clusters are never anonymously resumed, but they still
    // count as evidence that this physical device may be shared by people.
    if(!empty($cluster['claimedUserId']))continue;$guestId=safe_token((string)($cluster['canonicalGuestId']??''));$g=$guestId!==''?loom_guest_get($guestId):null;if(!$g)continue;$g=loom_guest_root($g);if(($g['status']??'')!=='unattached'||!empty($g['attachedUserId']))continue;
    $prev=$candidates[$cid]??null;if(!$prev||$sc['score']>$prev['score'])$candidates[$cid]=['cluster'=>$cluster,'score'=>$sc['score'],'signals'=>$sc['signals'],'observation'=>$obs];
  }
  // A physical device/browser signature that has represented multiple guest roots
  // is treated as a shared device. Never auto-pick a human on such a device.
  if(count($hardwareClusters)!==1){$reason=count($hardwareClusters)>1?'shared-device-ambiguous':'no-candidate';return ['matched'=>false,'reason'=>$reason,'candidateCount'=>count($hardwareClusters)];}
  $rows=array_values($candidates);usort($rows,fn($a,$b)=>$b['score']<=>$a['score']);$top=$rows[0]??null;$second=$rows[1]['score']??0;if(!$top)return ['matched'=>false,'reason'=>'no-candidate'];$margin=$top['score']-$second;
  if(!in_array('network',(array)$top['signals'],true))return ['matched'=>false,'reason'=>'network-changed','confidence'=>$top['score'],'margin'=>$margin];
  if($top['score']<92||$margin<18)return ['matched'=>false,'reason'=>'confidence-too-low','confidence'=>$top['score'],'margin'=>$margin];
  $cluster=$top['cluster'];$sourceProfileId='';foreach(array_reverse((array)($cluster['profileIds']??[])) as $pid){if(loom_guest_profile_get((string)$pid)){$sourceProfileId=(string)$pid;break;}}if($sourceProfileId==='')return ['matched'=>false,'reason'=>'profile-unavailable'];
  $guestId=(string)$cluster['canonicalGuestId'];loom_guest_map_client($guestId,$clientId);$profile=loom_guest_profile_create_continuity_alias($installationId,$sourceProfileId,$clientId,$guestId,(string)$cluster['clusterId']);
  $observed=loom_continuity_observe($installationId,$clientId,(string)$profile['guestProfileId'],$signals,'auto-restored');
  loom_continuity_mutate(function(array &$st)use($installationId,$clientId,$cluster,$top,$margin){$st['decisions'][]=['decisionId'=>'cdec_'.bin2hex(random_bytes(8)),'kind'=>'auto-restore','installationId'=>$installationId,'clientId'=>$clientId,'clusterId'=>$cluster['clusterId'],'confidence'=>$top['score'],'margin'=>$margin,'signals'=>$top['signals'],'createdAt'=>server_timestamp()];if(count($st['decisions'])>500)$st['decisions']=array_slice($st['decisions'],-500);return null;});
  loom_identity_audit('continuity.auto-restored',['installationId'=>$installationId,'clientId'=>$clientId,'clusterId'=>$cluster['clusterId'],'guestId'=>$guestId,'confidence'=>$top['score'],'margin'=>$margin]);
  return ['matched'=>true,'restored'=>true,'confidence'=>$top['score'],'profile'=>$profile,'cluster'=>$observed['cluster']??loom_continuity_public_cluster($cluster)];
}
function loom_continuity_claim_for_user(string $clientId,string $userId,string $mode='verified'): array {
  $clientId=safe_token($clientId);$userId=safe_token($userId);if($clientId===''||$userId==='')return ['claimed'=>false];
  return loom_continuity_mutate(function(array &$s)use($clientId,$userId,$mode){$c=loom_continuity_cluster_for_client_in_store($s,$clientId);if(!$c)return ['claimed'=>false,'reason'=>'no-cluster'];$id=(string)$c['clusterId'];$existing=(string)($c['claimedUserId']??'');if($existing!==''&&!hash_equals($existing,$userId))return ['claimed'=>false,'reason'=>'cluster-owned-by-another-user'];$c['claimedUserId']=$userId;$c['claimedAt']=$c['claimedAt']??server_timestamp();$c['claimMode']=$mode;$c['updatedAt']=server_timestamp();$s['clusters'][$id]=$c;loom_identity_audit('continuity.cluster-claimed',['clusterId'=>$id,'userId'=>$userId,'clientId'=>$clientId,'mode'=>$mode,'clientCount'=>count((array)$c['clientIds']),'profileCount'=>count((array)$c['profileIds'])]);return ['claimed'=>true,'cluster'=>loom_continuity_public_cluster($c)];});
}
function loom_continuity_cluster_for_client(string $clientId): ?array { $s=loom_continuity_read_store();return loom_continuity_cluster_for_client_in_store($s,safe_token($clientId)); }
function loom_continuity_guests_related(string $guestA,string $guestB): bool {
  $guestA=safe_token($guestA);$guestB=safe_token($guestB);if($guestA===''||$guestB==='')return false;if(hash_equals($guestA,$guestB))return true;$s=loom_continuity_read_store();$a=loom_continuity_cluster_for_guest_in_store($s,$guestA);$b=loom_continuity_cluster_for_guest_in_store($s,$guestB);return $a&&$b&&hash_equals((string)$a['clusterId'],(string)$b['clusterId']);
}
