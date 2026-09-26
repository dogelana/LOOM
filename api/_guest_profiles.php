<?php
// @loom-file release=0.15.62 revision=14 policy=package-priority
// LOOM v0.12.08 — explicit guest profiles + generation lineage.
declare(strict_types=1);

function loom_guest_profiles_file(): string { return loom_identity_dir().'/guest-profiles.json'; }
function loom_guest_profiles_store(): array {
  $s=array_replace(['schemaVersion'=>'2.0','profiles'=>[],'installationMap'=>[],'clientProfileMap'=>[]],read_json_file(loom_guest_profiles_file())?:[]);
  // v0.15.51 presentation repair: continuity aliases historically inherited the
  // globally-unique storage username (for example "Michael 2") as their visible
  // name. Keep uniqueness internally, but restore the human-facing name from the
  // source profile when the stored alias is only a numeric collision suffix.
  $changed=false;
  foreach(($s['profiles']??[]) as $pid=>$p){if(!is_array($p))continue;$g=(string)($p['currentGeneration']??'1');$cid=safe_token((string)($p['generations'][$g]['clientId']??''));$preset=(string)($p['avatarPreset']??'');$canonical=loom_global_avatar_preset_normalize($preset);if($canonical!==null&&$canonical!==$preset){$p['avatarPreset']=$canonical;$p['updatedAt']=server_timestamp();$s['profiles'][$pid]=$p;$changed=true;}elseif($canonical===null&&$cid!==''){$p['avatarPreset']=loom_global_avatar_repair_preset('client',$cid);$p['updatedAt']=server_timestamp();$s['profiles'][$pid]=$p;$changed=true;}$ownerId=$cid!==''?(function_exists('loom_guest_canonical_client_id')?loom_guest_canonical_client_id($cid):$cid):'';if($ownerId!=='')try{$gp=loom_global_profile_get('client',$ownerId);$internal=loom_clean_username((string)($gp['username']??''));$visible=loom_clean_username(function_exists('loom_global_profile_display_name')&&$gp?loom_global_profile_display_name($gp):(string)($gp['displayName']??''));$current=loom_clean_username((string)($p['displayName']??''));if($gp&&$visible!==''&&$visible!==$internal&&$current===$internal){$p['displayName']=$visible;$p['updatedAt']=server_timestamp();$s['profiles'][$pid]=$p;$changed=true;}}catch(Throwable $e){}}
  foreach(($s['profiles']??[]) as $pid=>$p){if(!is_array($p))continue;$sourceId=safe_token((string)($p['continuitySourceProfileId']??''));if($sourceId===''||!is_array($s['profiles'][$sourceId]??null))continue;$sourceName=loom_clean_username((string)($s['profiles'][$sourceId]['displayName']??''));$name=loom_clean_username((string)($p['displayName']??''));if($sourceName!==''&&($name===''||preg_match('/^'.preg_quote($sourceName,'/').'\s+\d+$/iu',$name))){$p['displayName']=$sourceName;$p['updatedAt']=server_timestamp();$s['profiles'][$pid]=$p;$changed=true;}}
  if($changed){$json=json_encode($s,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);if($json!==false)@file_put_contents(loom_guest_profiles_file(),$json."\n",LOCK_EX);}
  return $s;
}
function loom_guest_profiles_db_mirror(array $s): void {
  if(!function_exists('loom_db_ready')||!loom_db_ready())return;
  try{
    $pdo=loom_db_pdo(true);if(!$pdo)return;
    $ps=$pdo->prepare("INSERT INTO loom_guest_profiles(guest_profile_id,display_name,avatar_preset,current_generation,last_claimed_user_id,created_at,updated_at,payload_json) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE display_name=VALUES(display_name),avatar_preset=VALUES(avatar_preset),current_generation=VALUES(current_generation),last_claimed_user_id=VALUES(last_claimed_user_id),updated_at=VALUES(updated_at),payload_json=VALUES(payload_json)");
    $gs=$pdo->prepare("INSERT INTO loom_guest_profile_generations(guest_profile_id,generation,client_id,guest_id,status,user_id,created_at,claimed_at) VALUES(?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE client_id=VALUES(client_id),guest_id=VALUES(guest_id),status=VALUES(status),user_id=VALUES(user_id),claimed_at=VALUES(claimed_at)");
    foreach(($s['profiles']??[]) as $pid=>$p){if(!is_array($p))continue;$ps->execute([$pid,(string)($p['displayName']??'Guest'),(string)($p['avatarPreset']??'loom-default-preset-01'),(int)($p['currentGeneration']??1),$p['lastClaimedUserId']??null,loom_db_dt($p['createdAt']??null),loom_db_dt($p['updatedAt']??null),json_encode($p,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);foreach(($p['generations']??[]) as $generation=>$g){if(!is_array($g))continue;$gs->execute([$pid,(int)$generation,(string)($g['clientId']??''),$g['guestId']??null,(string)($g['status']??'active'),$g['userId']??null,loom_db_dt($g['createdAt']??null),loom_db_dt($g['claimedAt']??null)]);}}
    $is=$pdo->prepare("INSERT IGNORE INTO loom_guest_profile_installations(installation_id,guest_profile_id,linked_at) VALUES(?,?,UTC_TIMESTAMP(3))");foreach(($s['installationMap']??[]) as $installationId=>$profiles)foreach((array)$profiles as $pid)$is->execute([(string)$installationId,(string)$pid]);
  }catch(Throwable $e){}
}
function loom_guest_profiles_write(array $s): void {
  $s['schemaVersion']='2.0';$s['updatedAt']=server_timestamp();ensure_dir(dirname(loom_guest_profiles_file()));
  @file_put_contents(loom_guest_profiles_file(),json_encode($s,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
  loom_guest_profiles_db_mirror($s);
}
function loom_guest_profile_name_key(string $value): string { $value=trim($value);return function_exists('mb_strtolower')?mb_strtolower($value,'UTF-8'):strtolower($value); }
function loom_guest_profile_name_available(array $s,string $installationId,string $displayName,?string $exceptProfileId=null): bool { $needle=loom_guest_profile_name_key($displayName);foreach($s['installationMap'][$installationId]??[] as $pid){if($exceptProfileId!==null&&$pid===$exceptProfileId)continue;$p=$s['profiles'][$pid]??null;if(is_array($p)&&loom_guest_profile_name_key((string)($p['displayName']??''))===$needle)return false;}return true; }
function loom_guest_profile_id(): string { return 'gprof_'.bin2hex(random_bytes(10)); }
function loom_guest_installation_id(string $value): string { $v=safe_token($value);return str_starts_with($v,'install_')?$v:''; }
function loom_guest_profile_avatar_mode(string $preset): string { return loom_global_avatar_preset_normalize($preset)??'loom-default-preset-01'; }

function loom_guest_profile_clean_legacy_display_name(string $name,string $clientId=''): string {
  $name=loom_clean_username($name);if($name==='')return '';
  // Pre-0.15.57 storage sometimes appended a numeric collision suffix to what
  // was supposed to be a human-facing name. Strip it only when the unsuffixed
  // base is demonstrably owned by another profile, which identifies the legacy
  // allocator pattern rather than blindly changing names people actually typed.
  if(preg_match('/^(.*?)\s+([2-9][0-9]*)$/u',$name,$m)){$base=loom_clean_username((string)$m[1]);if($base!=='')try{$o=loom_global_profile_username_owner($base);if($o&&safe_token((string)($o['ownerId']??''))!==safe_token($clientId))return $base;}catch(Throwable $e){}}
  return $name;
}
function loom_guest_profile_effective_display_name(array $p): string {
  // Human-facing guest names are presentation data and do not need global
  // uniqueness. Prefer the explicit profile name; the globally unique client
  // username remains an internal handle/fallback only.
  $display=loom_clean_username((string)($p['displayName']??''));if($display!=='')return $display;
  $g=(string)($p['currentGeneration']??'1');$row=$p['generations'][$g]??[];$cid=safe_token((string)($row['clientId']??''));
  if($cid!=='')try{$ownerId=function_exists('loom_guest_canonical_client_id')?loom_guest_canonical_client_id($cid):$cid;$gp=loom_global_profile_ensure_for_owner('client',$ownerId);$u=loom_clean_username(function_exists('loom_global_profile_display_name')?loom_global_profile_display_name((array)$gp):(string)($gp['displayName']??$gp['username']??''));if($u!==''&&!preg_match('/^GuestHandle-/i',$u))return $u;}catch(Throwable $e){}
  return 'Guest';
}
function loom_guest_profile_public(array $p): array {
  $g=(string)($p['currentGeneration']??'1');$row=$p['generations'][$g]??[];
  return ['guestProfileId'=>$p['guestProfileId'],'displayName'=>loom_guest_profile_effective_display_name($p),'avatarPreset'=>loom_guest_profile_avatar_mode((string)($p['avatarPreset']??'')),'currentGeneration'=>(int)$g,'currentClientId'=>$row['clientId']??null,'currentGuestId'=>$row['guestId']??null,'lastClaimedUserId'=>$p['lastClaimedUserId']??null,'createdAt'=>$p['createdAt']??null,'updatedAt'=>$p['updatedAt']??null,'generationStatus'=>$row['status']??'active','continuityRestored'=>!empty($p['continuitySourceProfileId'])];
}

function loom_guest_profile_visible_name_for_client(string $clientId): string {
  $clientId=safe_token($clientId);if($clientId==='')return '';
  $direct=loom_guest_profile_for_client($clientId);if($direct){$n=loom_guest_profile_effective_display_name($direct);if($n!==''&&!preg_match('/^GuestHandle-/i',$n))return $n;}
  $u=loom_account_user_for_client($clientId);$uid=safe_token((string)($u['user_id']??$u['userId']??''));
  if($uid!==''){$guests=loom_guest_list_for_user($uid);usort($guests,fn($a,$b)=>strcmp((string)($b['attachedAt']??$b['updatedAt']??''),(string)($a['attachedAt']??$a['updatedAt']??'')));foreach($guests as $g){$cid=safe_token((string)($g['primaryClientId']??''));if($cid==='')continue;$p=loom_guest_profile_for_client($cid);if(!$p)continue;$n=loom_guest_profile_effective_display_name($p);if($n!==''&&!preg_match('/^GuestHandle-/i',$n))return $n;}}
  try{$gp=loom_global_profile_ensure($clientId);$raw=function_exists('loom_global_profile_display_name')?loom_global_profile_display_name((array)$gp):(string)($gp['displayName']??$gp['username']??'');$n=loom_guest_profile_clean_legacy_display_name($raw,$clientId);if($n!==''&&!preg_match('/^(?:GuestHandle-|acct_)/i',$n))return $n;}catch(Throwable $e){}
  return '';
}
function loom_guest_profile_get(string $profileId): ?array { $s=loom_guest_profiles_store();$p=$s['profiles'][safe_token($profileId)]??null;return is_array($p)?$p:null; }
function loom_guest_profile_for_client(string $clientId): ?array { $s=loom_guest_profiles_store();$pid=(string)($s['clientProfileMap'][safe_token($clientId)]??'');$p=$pid!==''?($s['profiles'][$pid]??null):null;return is_array($p)?$p:null; }
function loom_guest_profile_make_client(): string { return 'client_'.bin2hex(random_bytes(12)); }
function loom_guest_profile_seed_system_username(string $display,string $clientId): string {
  // Guest-facing names are never made unique by appending " 2", " 3", etc.
  // The globally-unique profile username is an internal storage handle only; the
  // human-facing Guest Profile keeps the exact displayName the person chose.
  $clientId=safe_token($clientId);$base='GuestHandle-'.strtoupper(substr(hash('sha256','loom-guest-handle|'.$clientId),0,10));$candidate=$base;$n=2;
  while($o=loom_global_profile_username_owner($candidate)){if(($o['ownerType']??'')==='client'&&($o['ownerId']??'')===$clientId)break;$candidate=$base.'-'.$n++;}
  return $candidate;
}
function loom_guest_profile_seed_generation_profile(string $clientId,string $display,string $preset): void {
  try{$p=loom_global_profile_ensure_for_owner('client',$clientId);$p['username']=loom_guest_profile_seed_system_username($display,$clientId);$p['displayName']=loom_clean_username($display)?:($p['displayName']??null);$p['avatarMode']=loom_guest_profile_avatar_mode($preset);$p['updatedAt']=server_timestamp();loom_global_profile_write($p);}catch(Throwable $e){}
}
function loom_guest_profile_create(string $installationId,string $displayName,string $avatarPreset='auto',?string $preferredClientId=null): array {
  $installationId=loom_guest_installation_id($installationId);if($installationId==='')throw new RuntimeException('Invalid installation identity.');
  $preferredClientId=safe_token((string)$preferredClientId);$clientId=str_starts_with($preferredClientId,'client_')?$preferredClientId:loom_guest_profile_make_client();$displayName=loom_clean_username($displayName);if($displayName==='')$displayName=loom_global_default_username('client',$clientId);
  $preset=loom_global_avatar_preset_normalize($avatarPreset)??loom_global_avatar_random_preset();$s=loom_guest_profiles_store();if(!loom_guest_profile_name_available($s,$installationId,$displayName))throw new RuntimeException('That guest name is already in use on this device.');$now=server_timestamp();$guest=loom_guest_for_client($clientId);if(!$guest)$guest=loom_guest_create_for_client($clientId);else $guest=loom_guest_root($guest);$pid=loom_guest_profile_id();
  $p=['guestProfileId'=>$pid,'displayName'=>$displayName,'avatarPreset'=>$preset,'currentGeneration'=>1,'generations'=>['1'=>['generation'=>1,'clientId'=>$clientId,'guestId'=>$guest['guestId'],'status'=>'active','createdAt'=>$now,'claimedAt'=>null,'userId'=>null]],'lastClaimedUserId'=>null,'createdAt'=>$now,'updatedAt'=>$now];
  $s['profiles'][$pid]=$p;$s['installationMap'][$installationId]=array_values(array_unique(array_merge($s['installationMap'][$installationId]??[],[$pid])));$s['clientProfileMap'][$clientId]=$pid;loom_guest_profiles_write($s);loom_guest_profile_seed_generation_profile($clientId,$displayName,$preset);loom_identity_audit('guest-profile.created',['guestProfileId'=>$pid,'installationId'=>$installationId,'clientId'=>$clientId]);return loom_guest_profile_public($p);
}
function loom_guest_profile_attach_installation(string $installationId,string $profileId): void { $installationId=loom_guest_installation_id($installationId);$profileId=safe_token($profileId);if($installationId===''||$profileId==='')return;$s=loom_guest_profiles_store();if(!isset($s['profiles'][$profileId]))return;$s['installationMap'][$installationId]=array_values(array_unique(array_merge($s['installationMap'][$installationId]??[],[$profileId])));loom_guest_profiles_write($s); }
function loom_guest_profile_adopt_legacy(string $installationId,string $legacyClientId): ?array {
  $installationId=loom_guest_installation_id($installationId);$legacyClientId=safe_token($legacyClientId);if($installationId===''||$legacyClientId===''||!str_starts_with($legacyClientId,'client_'))return null;
  if($existing=loom_guest_profile_for_client($legacyClientId)){loom_guest_profile_attach_installation($installationId,$existing['guestProfileId']);return loom_guest_profile_public($existing);}
  $guest=loom_guest_ensure_for_client($legacyClientId);$gp=loom_global_profile_get('client',loom_guest_canonical_client_id($legacyClientId));$display=loom_guest_profile_clean_legacy_display_name((string)($gp['username']??''),$legacyClientId);if($display===''||preg_match('/^GuestHandle-[A-F0-9-]+$/i',$display))$display=loom_global_default_username('client',$legacyClientId);
  $preset=loom_global_avatar_repair_preset('client',$legacyClientId);$pid=loom_guest_profile_id();$now=server_timestamp();
  $p=['guestProfileId'=>$pid,'displayName'=>$display,'avatarPreset'=>$preset,'currentGeneration'=>1,'generations'=>['1'=>['generation'=>1,'clientId'=>$legacyClientId,'guestId'=>$guest['guestId'],'status'=>'active','createdAt'=>$guest['createdAt']??$now,'claimedAt'=>null,'userId'=>$guest['attachedUserId']??null]],'lastClaimedUserId'=>$guest['attachedUserId']??null,'createdAt'=>$guest['createdAt']??$now,'updatedAt'=>$now];
  $s=loom_guest_profiles_store();$s['profiles'][$pid]=$p;$s['installationMap'][$installationId]=array_values(array_unique(array_merge($s['installationMap'][$installationId]??[],[$pid])));$s['clientProfileMap'][$legacyClientId]=$pid;loom_guest_profiles_write($s);loom_identity_audit('guest-profile.legacy-adopted',['guestProfileId'=>$pid,'installationId'=>$installationId,'clientId'=>$legacyClientId]);return loom_guest_profile_public($p);
}
function loom_guest_profiles_for_installation(string $installationId): array { $installationId=loom_guest_installation_id($installationId);if($installationId==='')return [];$s=loom_guest_profiles_store();$out=[];$changed=false;foreach($s['installationMap'][$installationId]??[] as $pid){$p=$s['profiles'][$pid]??null;if(!is_array($p))continue;$effective=loom_guest_profile_effective_display_name($p);if($effective!==''&&$effective!==(string)($p['displayName']??'')&&loom_guest_profile_name_available($s,$installationId,$effective,(string)$pid)){$p['displayName']=$effective;$p['updatedAt']=server_timestamp();$s['profiles'][$pid]=$p;$changed=true;}$out[]=loom_guest_profile_public($p);}if($changed)loom_guest_profiles_write($s);usort($out,fn($a,$b)=>strcmp((string)$a['createdAt'],(string)$b['createdAt']));return $out; }
function loom_guest_profile_update(string $installationId,string $profileId,?string $displayName=null,?string $avatarPreset=null): array {
  $installationId=loom_guest_installation_id($installationId);$profileId=safe_token($profileId);$s=loom_guest_profiles_store();if(!in_array($profileId,$s['installationMap'][$installationId]??[],true)||!isset($s['profiles'][$profileId]))throw new RuntimeException('Guest profile is not available on this installation.');$p=$s['profiles'][$profileId];
  if($displayName!==null){$name=loom_clean_username($displayName);if($name==='')throw new RuntimeException('Guest name cannot be empty.');if(!loom_guest_profile_name_available($s,$installationId,$name,$profileId))throw new RuntimeException('That guest name is already in use on this device.');$p['displayName']=$name;}
  if($avatarPreset!==null)$p['avatarPreset']=loom_global_avatar_preset_normalize($avatarPreset)??loom_global_avatar_repair_preset('client',(string)(($p['generations'][(string)($p['currentGeneration']??1)]['clientId']??'')));$p['updatedAt']=server_timestamp();$s['profiles'][$profileId]=$p;loom_guest_profiles_write($s);$g=$p['generations'][(string)$p['currentGeneration']]??[];$cid=(string)($g['clientId']??'');if($cid!=='')loom_guest_profile_seed_generation_profile($cid,$p['displayName'],$p['avatarPreset']);return loom_guest_profile_public($p);
}

// v0.15.58: self-profile display-name edits must mutate the canonical Guest
// Profile, not only its legacy global-client presentation row. A Guest Profile
// can be present on more than one installation through explicit continuity, so
// enforce the existing per-installation name rule everywhere that profile is
// visible, then synchronize every generation's compatibility presentation row.
function loom_guest_profile_set_display_name_for_client(string $clientId,string $displayName): ?array {
  $clientId=safe_token($clientId);$name=loom_clean_username($displayName);if($clientId===''||!str_starts_with($clientId,'client_'))return null;if($name==='')throw new RuntimeException('Guest name cannot be empty.');
  $s=loom_guest_profiles_store();$pid=safe_token((string)($s['clientProfileMap'][$clientId]??''));$p=$pid!==''?($s['profiles'][$pid]??null):null;if(!is_array($p))return null;
  foreach(($s['installationMap']??[]) as $installationId=>$profileIds){if(!in_array($pid,(array)$profileIds,true))continue;if(!loom_guest_profile_name_available($s,(string)$installationId,$name,$pid))throw new RuntimeException('That guest name is already in use on this device.');}
  $p['displayName']=$name;$p['updatedAt']=server_timestamp();$s['profiles'][$pid]=$p;loom_guest_profiles_write($s);
  foreach((array)($p['generations']??[]) as $generation){$cid=safe_token((string)($generation['clientId']??''));if($cid!=='')loom_guest_profile_seed_generation_profile($cid,$name,(string)($p['avatarPreset']??loom_global_avatar_repair_preset('client',$cid)));}
  loom_identity_audit('guest-profile.display-name.updated',['guestProfileId'=>$pid,'clientId'=>$clientId,'displayName'=>$name]);
  return loom_guest_profile_public($p);
}
function loom_guest_profile_create_continuity_alias(string $installationId,string $sourceProfileId,string $clientId,string $guestId,string $clusterId=''): array {
  $installationId=loom_guest_installation_id($installationId);$sourceProfileId=safe_token($sourceProfileId);$clientId=safe_token($clientId);$guestId=safe_token($guestId);$clusterId=safe_token($clusterId);
  if($installationId===''||$sourceProfileId===''||$clientId===''||$guestId==='')throw new RuntimeException('Invalid continuity profile context.');
  $s=loom_guest_profiles_store();foreach($s['installationMap'][$installationId]??[] as $existingPid){$existing=$s['profiles'][$existingPid]??null;if(!is_array($existing))continue;$row=$existing['generations'][(string)($existing['currentGeneration']??1)]??[];if(($row['clientId']??'')===$clientId)return loom_guest_profile_public($existing);}
  $source=$s['profiles'][$sourceProfileId]??null;if(!is_array($source))throw new RuntimeException('Continuity source profile is unavailable.');
  $name=loom_guest_profile_effective_display_name($source);if($name==='')$name=loom_global_default_username('client',$clientId); // aliases may intentionally share a visible name; only the internal system username must be unique.
  $preset=loom_guest_profile_avatar_mode((string)($source['avatarPreset']??''));$pid=loom_guest_profile_id();$now=server_timestamp();$p=['guestProfileId'=>$pid,'displayName'=>$name,'avatarPreset'=>$preset,'currentGeneration'=>1,'generations'=>['1'=>['generation'=>1,'clientId'=>$clientId,'guestId'=>$guestId,'status'=>'active','createdAt'=>$now,'claimedAt'=>null,'userId'=>null]],'lastClaimedUserId'=>null,'continuitySourceProfileId'=>$sourceProfileId,'continuityClusterId'=>$clusterId?:null,'continuityRestoredAt'=>$now,'createdAt'=>$now,'updatedAt'=>$now];
  $s['profiles'][$pid]=$p;$s['installationMap'][$installationId]=array_values(array_unique(array_merge($s['installationMap'][$installationId]??[],[$pid])));$s['clientProfileMap'][$clientId]=$pid;loom_guest_profiles_write($s);loom_identity_audit('guest-profile.continuity-alias-created',['guestProfileId'=>$pid,'sourceProfileId'=>$sourceProfileId,'installationId'=>$installationId,'clientId'=>$clientId,'guestId'=>$guestId,'clusterId'=>$clusterId?:null]);return loom_guest_profile_public($p);
}
function loom_guest_profile_claim_generation(string $clientId,string $userId,string $mode='login'): ?array {
  $clientId=safe_token($clientId);$userId=safe_token($userId);if($clientId===''||$userId==='')return null;$s=loom_guest_profiles_store();$pid=(string)($s['clientProfileMap'][$clientId]??'');if($pid===''||!isset($s['profiles'][$pid]))return null;$p=$s['profiles'][$pid];$current=(int)($p['currentGeneration']??1);$key=(string)$current;$row=$p['generations'][$key]??null;if(!is_array($row)||($row['clientId']??'')!==$clientId)return null;
  if(($row['status']??'active')!=='claimed'){$row['status']='claimed';$row['claimedAt']=server_timestamp();$row['userId']=$userId;$row['claimMode']=$mode;$p['generations'][$key]=$row;}
  $effectiveName=loom_guest_profile_effective_display_name($p);if($effectiveName!=='')$p['displayName']=$effectiveName;
  $next=$current+1;$nextKey=(string)$next;if(!isset($p['generations'][$nextKey])){$nextClient=loom_guest_profile_make_client();$guest=loom_guest_create_for_client($nextClient);$p['generations'][$nextKey]=['generation'=>$next,'clientId'=>$nextClient,'guestId'=>$guest['guestId'],'status'=>'active','createdAt'=>server_timestamp(),'claimedAt'=>null,'userId'=>null];$s['clientProfileMap'][$nextClient]=$pid;loom_guest_profile_seed_generation_profile($nextClient,$p['displayName'],$p['avatarPreset']);}
  $p['currentGeneration']=$next;$p['lastClaimedUserId']=$userId;$p['updatedAt']=server_timestamp();$s['profiles'][$pid]=$p;loom_guest_profiles_write($s);$nextRow=$p['generations'][$nextKey];loom_identity_audit('guest-profile.generation-claimed',['guestProfileId'=>$pid,'generation'=>$current,'clientId'=>$clientId,'userId'=>$userId,'nextGeneration'=>$next,'nextClientId'=>$nextRow['clientId'],'mode'=>$mode]);return ['profile'=>loom_guest_profile_public($p),'claimedGeneration'=>$current,'resumeGuestClientId'=>$nextRow['clientId']];
}
function loom_guest_profile_resume_client_for_claimed_client(string $clientId): ?string { $p=loom_guest_profile_for_client($clientId);if(!$p)return null;$row=$p['generations'][(string)$p['currentGeneration']]??[];$cid=safe_token((string)($row['clientId']??''));return $cid!==''?$cid:null; }
