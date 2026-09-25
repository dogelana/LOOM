<?php
// @loom-file release=0.15.53 revision=3 policy=package-priority
// LOOM v0.15.49 — privacy-bounded Continuity Clusters + strict guest ambiguity gate.
declare(strict_types=1);

/**
 * Continuity is intentionally NOT authentication.
 *
 * It records coarse continuity evidence so LOOM can detect when anonymous guest
 * use has become ambiguous. No raw user-agent string or permanent hardware
 * identifier is stored. The browser sends coarse device/display traits; LOOM
 * stores normalized hashes plus already-recorded network metadata.
 *
 * In 0.15.49 continuity never auto-authenticates or silently resumes another
 * browser's guest. Overlap can only remove anonymous access and require a
 * permanent account. Durable account verification remains the authority boundary.
 */

function loom_continuity_file(): string { return loom_identity_dir().'/continuity-clusters.json'; }
function loom_continuity_default_store(): array {
  return ['schemaVersion'=>'2.0','clusters'=>[],'clients'=>[],'decisions'=>[],'guestLocks'=>[],'installationLocks'=>[],'pendingLocks'=>[],'ambiguityEvents'=>[],'updatedAt'=>null];
}
function loom_continuity_read_store(): array {
  return array_replace(loom_continuity_default_store(),read_json_file(loom_continuity_file())?:[]);
}
function loom_continuity_mutate(callable $fn): mixed {
  $file=loom_continuity_file();ensure_dir(dirname($file));$fh=@fopen($file,'c+');if(!$fh)throw new RuntimeException('Continuity store is unavailable.');
  try{
    if(!flock($fh,LOCK_EX))throw new RuntimeException('Continuity store lock failed.');
    rewind($fh);$raw=stream_get_contents($fh);$s=$raw!==false&&trim($raw)!==''?json_decode($raw,true):null;if(!is_array($s))$s=loom_continuity_default_store();$s=array_replace(loom_continuity_default_store(),$s);
    $result=$fn($s);$s['schemaVersion']='2.0';$s['updatedAt']=server_timestamp();$json=json_encode($s,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
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
  // Anonymous cross-browser auto-resume is intentionally retired. A matching
  // signal is ambiguity evidence, not permission to choose a person.
  $policy=loom_continuity_guest_mode_policy($installationId,$clientId,$signals,true,false);
  return ['matched'=>false,'reason'=>!empty($policy['permanentRequired'])?'permanent-required':'anonymous-auto-resume-disabled','guestPolicy'=>$policy];
}
function loom_continuity_claim_for_user(string $clientId,string $userId,string $mode='verified'): array {
  $clientId=safe_token($clientId);$userId=safe_token($userId);if($clientId===''||$userId==='')return ['claimed'=>false];
  return loom_continuity_mutate(function(array &$s)use($clientId,$userId,$mode){$c=loom_continuity_cluster_for_client_in_store($s,$clientId);if(!$c)return ['claimed'=>false,'reason'=>'no-cluster'];$id=(string)$c['clusterId'];$existing=(string)($c['claimedUserId']??'');if($existing!==''&&!hash_equals($existing,$userId))return ['claimed'=>false,'reason'=>'cluster-owned-by-another-user'];$c['claimedUserId']=$userId;$c['claimedAt']=$c['claimedAt']??server_timestamp();$c['claimMode']=$mode;$c['updatedAt']=server_timestamp();$s['clusters'][$id]=$c;loom_identity_audit('continuity.cluster-claimed',['clusterId'=>$id,'userId'=>$userId,'clientId'=>$clientId,'mode'=>$mode,'clientCount'=>count((array)$c['clientIds']),'profileCount'=>count((array)$c['profileIds'])]);return ['claimed'=>true,'cluster'=>loom_continuity_public_cluster($c)];});
}
function loom_continuity_cluster_for_client(string $clientId): ?array { $s=loom_continuity_read_store();return loom_continuity_cluster_for_client_in_store($s,safe_token($clientId)); }
function loom_continuity_guests_related(string $guestA,string $guestB): bool {
  $guestA=safe_token($guestA);$guestB=safe_token($guestB);if($guestA===''||$guestB==='')return false;if(hash_equals($guestA,$guestB))return true;$s=loom_continuity_read_store();$a=loom_continuity_cluster_for_guest_in_store($s,$guestA);$b=loom_continuity_cluster_for_guest_in_store($s,$guestB);return $a&&$b&&hash_equals((string)$a['clusterId'],(string)$b['clusterId']);
}


// -----------------------------------------------------------------------------
// v0.15.49 strict guest ambiguity policy
// -----------------------------------------------------------------------------
// Guest mode is allowed only while LOOM has no meaningful evidence that the
// current environment overlaps another guest identity.  An overlap does NOT
// prove two sessions are the same person; it does the opposite: it creates
// enough uncertainty that anonymous guest mode is no longer safe.  From that
// point forward every affected guest must authenticate or create a permanent
// account.  The lock is durable and deliberately survives later network/device
// changes.  Authentication remains the only authority boundary.

function loom_continuity_root_guest_id_for_client(string $clientId): string {
  $clientId=safe_token($clientId);if($clientId==='')return '';$g=loom_guest_for_client($clientId);if(!$g)return '';$g=loom_guest_root($g);return safe_token((string)($g['guestId']??''));
}
function loom_continuity_guest_lock_public(array $row): array {
  $labels=array_values(array_unique(array_filter(array_map('strval',(array)($row['labels']??[])))));
  return ['required'=>true,'labels'=>$labels,'firstDetectedAt'=>$row['firstDetectedAt']??null,'lastDetectedAt'=>$row['lastDetectedAt']??null,'peerCount'=>(int)($row['peerCount']??0)];
}
function loom_continuity_mark_guest_lock_in_store(array &$s,string $guestId,array $labels,array $peerGuestIds=[],string $source='overlap'): void {
  $guestId=safe_token($guestId);if($guestId==='')return;$now=server_timestamp();$prior=is_array($s['guestLocks'][$guestId]??null)?$s['guestLocks'][$guestId]:[];
  $peers=[];foreach(array_merge((array)($prior['peerGuestIds']??[]),$peerGuestIds) as $gid){$gid=safe_token((string)$gid);if($gid!==''&&!hash_equals($gid,$guestId))$peers[$gid]=true;}
  $allLabels=[];foreach(array_merge((array)($prior['labels']??[]),$labels) as $label){$label=loom_continuity_text($label,64);if($label!=='')$allLabels[$label]=true;}
  $s['guestLocks'][$guestId]=['required'=>true,'guestId'=>$guestId,'labels'=>array_keys($allLabels),'peerGuestIds'=>array_keys($peers),'peerCount'=>count($peers),'source'=>$source,'firstDetectedAt'=>$prior['firstDetectedAt']??$now,'lastDetectedAt'=>$now];
}
function loom_continuity_mark_installation_lock_in_store(array &$s,string $installationId,array $labels,string $source='overlap'): void {
  $installationId=loom_guest_installation_id($installationId);if($installationId==='')return;$now=server_timestamp();$prior=is_array($s['installationLocks'][$installationId]??null)?$s['installationLocks'][$installationId]:[];$all=[];foreach(array_merge((array)($prior['labels']??[]),$labels) as $label){$label=loom_continuity_text($label,64);if($label!=='')$all[$label]=true;}$s['installationLocks'][$installationId]=['required'=>true,'installationId'=>$installationId,'labels'=>array_keys($all),'source'=>$source,'firstDetectedAt'=>$prior['firstDetectedAt']??$now,'lastDetectedAt'=>$now];
}
function loom_continuity_mark_pending_lock_in_store(array &$s,string $clientId,string $installationId,array $labels,string $source='overlap'): void {
  $clientId=safe_token($clientId);if($clientId==='')return;$now=server_timestamp();$prior=is_array($s['pendingLocks'][$clientId]??null)?$s['pendingLocks'][$clientId]:[];$all=[];foreach(array_merge((array)($prior['labels']??[]),$labels) as $label){$label=loom_continuity_text($label,64);if($label!=='')$all[$label]=true;}$s['pendingLocks'][$clientId]=['required'=>true,'clientId'=>$clientId,'installationId'=>$installationId,'labels'=>array_keys($all),'source'=>$source,'firstDetectedAt'=>$prior['firstDetectedAt']??$now,'lastDetectedAt'=>$now];
}
function loom_continuity_observation_overlap_labels(array $sig,array $obs,?string $ip,string $installationId): array {
  $labels=[];
  if($installationId!==''&&hash_equals($installationId,(string)($obs['installationId']??'')))$labels['same browser installation']=true;
  if(($sig['hardwareHash']??'')!==''&&($obs['hardwareHash']??'')!==''&&hash_equals((string)$sig['hardwareHash'],(string)$obs['hardwareHash']))$labels['matching device characteristics']=true;
  // A display match alone is common; require the coarse platform signature too.
  if(($sig['displayHash']??'')!==''&&($sig['platformHash']??'')!==''&&($obs['displayHash']??'')!==''&&($obs['platformHash']??'')!==''&&hash_equals((string)$sig['displayHash'],(string)$obs['displayHash'])&&hash_equals((string)$sig['platformHash'],(string)$obs['platformHash']))$labels['matching device/display profile']=true;
  $oldIp=loom_valid_ip((string)($obs['ip']??''));
  // Network overlap is intentionally based only on PUBLIC client addresses. A
  // private/loopback REMOTE_ADDR may belong to the hosting reverse proxy itself
  // and must never mass-lock unrelated visitors.
  if($ip&&$oldIp&&loom_ip_is_public($ip)&&loom_ip_is_public($oldIp)){
    if(hash_equals($ip,$oldIp))$labels['same network address']=true;
    elseif(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV6)&&filter_var($oldIp,FILTER_VALIDATE_IP,FILTER_FLAG_IPV6)&&loom_ip_network_key($ip)!==''&&hash_equals(loom_ip_network_key($ip),loom_ip_network_key($oldIp)))$labels['same IPv6 household network']=true;
  }
  return array_keys($labels);
}
function loom_continuity_network_clients_for_ip(string $ip): array {
  $ip=(string)(loom_valid_ip($ip)?:'');if($ip===''||!loom_ip_is_public($ip))return [];$out=[];$networkKey=loom_ip_network_key($ip);
  if(function_exists('loom_network_store')){
    $network=loom_network_store();foreach(($network['identities']??[]) as $row){if(!is_array($row)||($row['ownerType']??'')!=='client')continue;$cid=safe_token((string)($row['ownerId']??''));if($cid==='')continue;foreach((array)($row['addresses']??[]) as $oldIp=>$meta){$old=loom_valid_ip((string)$oldIp);if(!$old)continue;$oldKey=(string)($meta['networkKey']??loom_ip_network_key($old));if(hash_equals($ip,$old)||($networkKey!==''&&$oldKey!==''&&hash_equals($networkKey,$oldKey))){$out[$cid]=true;break;}}}
  }
  if(function_exists('loom_moderation_db')&&($pdo=loom_moderation_db()))try{
    if($networkKey!==''){$st=$pdo->prepare("SELECT owner_id FROM loom_identity_ips WHERE owner_type='client' AND (ip_address=? OR network_key=?)");$st->execute([$ip,$networkKey]);}
    else{$st=$pdo->prepare("SELECT owner_id FROM loom_identity_ips WHERE owner_type='client' AND ip_address=?");$st->execute([$ip]);}
    foreach($st->fetchAll() as $r){$cid=safe_token((string)($r['owner_id']??''));if($cid!=='')$out[$cid]=true;}
  }catch(Throwable $e){}
  return array_keys($out);
}
function loom_continuity_profiles_guest_ids(string $installationId): array {
  $ids=[];foreach(loom_guest_profiles_for_installation($installationId) as $p){$cid=safe_token((string)($p['currentClientId']??''));$gid=$cid!==''?loom_continuity_root_guest_id_for_client($cid):'';if($gid!=='')$ids[$gid]=true;}return array_keys($ids);
}

function loom_continuity_backfill_historical_overlap_locks(): int {
  // One conservative pass per request. This upgrades pre-strict guest histories:
  // if TWO DISTINCT Guest roots already share a network/device/install signal,
  // every still-anonymous Guest in that overlap becomes permanent-account-required.
  // It never merges or authenticates anyone and never uses city/region/country.
  static $done=false;if($done)return 0;$done=true;$groups=[];
  $addGroup=function(string $label,string $key,string $cid)use(&$groups){$cid=safe_token($cid);if($key===''||$cid==='')return;$gk=$label.'|'.$key;if(!isset($groups[$gk]))$groups[$gk]=['label'=>$label,'clients'=>[]];$groups[$gk]['clients'][$cid]=true;};
  if(function_exists('loom_network_store')){$network=loom_network_store();foreach(($network['identities']??[]) as $row){if(!is_array($row)||($row['ownerType']??'')!=='client')continue;$cid=safe_token((string)($row['ownerId']??''));foreach((array)($row['addresses']??[]) as $ip=>$meta){$valid=loom_valid_ip((string)$ip);if(!$valid||!loom_ip_is_public($valid))continue;$key=(string)($meta['networkKey']??loom_ip_network_key($valid));$label=filter_var($valid,FILTER_VALIDATE_IP,FILTER_FLAG_IPV6)?'same IPv6 household network':'same network address';$addGroup($label,$key,$cid);}}}
  $cur=loom_continuity_read_store();foreach(($cur['clients']??[]) as $cid=>$obs){if(!is_array($obs))continue;$cid=safe_token((string)($obs['clientId']??$cid));$install=loom_guest_installation_id((string)($obs['installationId']??''));if($install!=='')$addGroup('same browser installation',$install,$cid);$hw=(string)($obs['hardwareHash']??'');if($hw!=='')$addGroup('matching device characteristics',$hw,$cid);$dh=(string)($obs['displayHash']??'');$ph=(string)($obs['platformHash']??'');if($dh!==''&&$ph!=='')$addGroup('matching device/display profile',$dh.'|'.$ph,$cid);}
  $lockSets=[];
  foreach($groups as $group){$guestIds=[];foreach(array_keys($group['clients']) as $cid){$gid=loom_continuity_root_guest_id_for_client($cid);if($gid!=='')$guestIds[$gid]=true;}if(count($guestIds)<2)continue;$ids=array_keys($guestIds);foreach($ids as $gid){$g=loom_guest_get($gid);if(!$g)continue;$g=loom_guest_root($g);if(!empty($g['attachedUserId']))continue;$lockSets[$gid]['labels'][$group['label']]=true;foreach($ids as $peer)if($peer!==$gid)$lockSets[$gid]['peers'][$peer]=true;}}
  if(!$lockSets)return 0;
  loom_continuity_mutate(function(array &$st)use($lockSets){foreach($lockSets as $gid=>$x)loom_continuity_mark_guest_lock_in_store($st,$gid,array_keys($x['labels']??[]),array_keys($x['peers']??[]),'historical-overlap-backfill');return null;});
  return count($lockSets);
}
function loom_continuity_guest_mode_policy(string $installationId,string $clientId,array $signals,bool $mark=true,bool $creatingNewGuest=false): array {
  $installationId=loom_guest_installation_id($installationId);$clientId=safe_token($clientId);$sig=loom_continuity_normalize_signals($signals);$ipObs=$clientId!==''?loom_capture_request_ip($clientId,''):loom_request_ip_observation();$ip=loom_valid_ip((string)($ipObs['ip']??''));
  loom_continuity_backfill_historical_overlap_locks();
  $currentGuestId=$clientId!==''?loom_continuity_root_guest_id_for_client($clientId):'';$s=loom_continuity_read_store();$labels=[];$peerGuestIds=[];$peerClients=[];
  $add=function(string $label,string $peerGuestId='',string $peerClient='')use(&$labels,&$peerGuestIds,&$peerClients){$label=loom_continuity_text($label,64);if($label!=='')$labels[$label]=true;$peerGuestId=safe_token($peerGuestId);if($peerGuestId!=='')$peerGuestIds[$peerGuestId]=true;$peerClient=safe_token($peerClient);if($peerClient!=='')$peerClients[$peerClient]=true;};

  // Existing durable locks always win, even if the overlapping signal is gone now.
  if($currentGuestId!==''&&is_array($s['guestLocks'][$currentGuestId]??null)&&!empty($s['guestLocks'][$currentGuestId]['required']))foreach((array)($s['guestLocks'][$currentGuestId]['labels']??[]) as $l)$add((string)$l);
  if($installationId!==''&&is_array($s['installationLocks'][$installationId]??null)&&!empty($s['installationLocks'][$installationId]['required']))foreach((array)($s['installationLocks'][$installationId]['labels']??[]) as $l)$add((string)$l);
  if($clientId!==''&&is_array($s['pendingLocks'][$clientId]??null)&&!empty($s['pendingLocks'][$clientId]['required']))foreach((array)($s['pendingLocks'][$clientId]['labels']??[]) as $l)$add((string)$l);

  // A second guest profile on the same browser installation is ambiguity by definition.
  $installationGuests=loom_continuity_profiles_guest_ids($installationId);
  if($creatingNewGuest&&count($installationGuests)>=1){foreach($installationGuests as $gid)$add('browser installation',$gid);}
  if(count($installationGuests)>1){foreach($installationGuests as $gid)$add('browser installation',$gid);}

  // Compare current coarse device/network evidence with all prior continuity observations.
  if($sig['usable'])foreach(($s['clients']??[]) as $obs){if(!is_array($obs))continue;$otherClient=safe_token((string)($obs['clientId']??''));if($otherClient===''||($clientId!==''&&hash_equals($otherClient,$clientId)))continue;$otherGuest=loom_continuity_root_guest_id_for_client($otherClient);if($currentGuestId!==''&&$otherGuest!==''&&hash_equals($currentGuestId,$otherGuest))continue;$ol=loom_continuity_observation_overlap_labels($sig,$obs,$ip,$installationId);if(!$ol)continue;foreach($ol as $l)$add($l,$otherGuest,$otherClient);}

  // Historical exact network overlap catches pre-0.15.47 guest identities too.
  if($ip&&loom_ip_is_public($ip))foreach(loom_continuity_network_clients_for_ip($ip) as $otherClient){if($clientId!==''&&hash_equals($otherClient,$clientId))continue;$otherGuest=loom_continuity_root_guest_id_for_client($otherClient);if($currentGuestId!==''&&$otherGuest!==''&&hash_equals($currentGuestId,$otherGuest))continue;$add(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_IPV6)?'same IPv6 household network':'same network address',$otherGuest,$otherClient);}

  // A second client that already belongs to the SAME canonical Guest is continuity,
  // not ambiguity. Permanent enforcement begins when evidence connects distinct
  // Guest roots, or when a brand-new client overlaps an existing Guest.
  $required=!empty($labels);
  if($required&&$mark){
    $labelList=array_keys($labels);$allGuests=$peerGuestIds;if($currentGuestId!=='')$allGuests[$currentGuestId]=true;foreach($installationGuests as $gid)$allGuests[$gid]=true;$guestIds=array_keys($allGuests);
    loom_continuity_mutate(function(array &$st)use($guestIds,$currentGuestId,$installationId,$clientId,$labelList){foreach($guestIds as $gid)loom_continuity_mark_guest_lock_in_store($st,$gid,$labelList,array_values(array_diff($guestIds,[$gid])),'strict-overlap');if($installationId!=='')loom_continuity_mark_installation_lock_in_store($st,$installationId,$labelList,'strict-overlap');if($clientId!==''&&$currentGuestId==='')loom_continuity_mark_pending_lock_in_store($st,$clientId,$installationId,$labelList,'strict-overlap');$st['ambiguityEvents'][]=['eventId'=>'amb_'.bin2hex(random_bytes(8)),'installationId'=>$installationId,'clientId'=>$clientId?:null,'guestIds'=>$guestIds,'labels'=>$labelList,'createdAt'=>server_timestamp()];if(count($st['ambiguityEvents'])>1000)$st['ambiguityEvents']=array_slice($st['ambiguityEvents'],-1000);return null;});
    loom_identity_audit('continuity.permanent-required',['installationId'=>$installationId,'clientId'=>$clientId?:null,'guestIds'=>$guestIds,'labels'=>$labelList]);
  }
  $labelList=array_keys($labels);$message=$required?'LOOM found another guest session sharing part of this browsing environment ('.implode(', ',$labelList).'). Because LOOM can no longer safely tell the guests apart, anonymous access is disabled for the affected sessions. Sign in to an existing permanent account or create one to continue.':'This guest environment is currently unique enough for anonymous use.';
  return ['mode'=>$required?'permanent-required':'guest-allowed','permanentRequired'=>$required,'labels'=>$labelList,'message'=>$message,'matchedGuestCount'=>count($peerGuestIds),'matchedClientCount'=>count($peerClients),'durable'=>$required];
}
function loom_continuity_assert_guest_allowed(string $installationId,string $clientId,array $signals,bool $creatingNewGuest=false): array {
  $policy=loom_continuity_guest_mode_policy($installationId,$clientId,$signals,true,$creatingNewGuest);if(!empty($policy['permanentRequired']))throw new RuntimeException('Permanent account required because this guest environment overlaps another LOOM guest session.');return $policy;
}
