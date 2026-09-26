<?php
// @loom-file release=0.15.66 revision=13 policy=package-priority
declare(strict_types=1);

// LOOM Global Profile Standard v0.11.20
// Account/authentication is global. A LOOM profile supplies the default visible
// username/avatar. Projects may inherit it or override either field independently.

function loom_global_profile_file(): string { ensure_dir(loom_data_dir().'/users'); return loom_data_dir().'/users/global-profiles.json'; }
function loom_global_profile_store(): array { return array_replace(['schemaVersion'=>'1.0','profiles'=>[]],read_json_file(loom_global_profile_file())?:[]); }
function loom_write_global_profile_store(array $s): void { ensure_dir(dirname(loom_global_profile_file()));$s['schemaVersion']='1.0';$s['updatedAt']=server_timestamp();@file_put_contents(loom_global_profile_file(),json_encode($s,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX); }
function loom_global_profile_key(string $type,string $id): string { return $type.'|'.safe_token($id); }
function loom_visible_name_is_internal(string $value): bool { $v=loom_clean_username($value);return $v===''||preg_match('/^(?:GuestHandle-|ProfileHandle-|acct_|LOOMUser-|Anonymous\s+[A-F0-9]{4,})/i',$v)===1; }

function loom_global_avatar_preset_normalize(string $mode): ?string { if(!preg_match('/^(?:loom-default-)?preset-(0[1-9]|10)$/',trim($mode),$m))return null;return 'loom-default-preset-'.$m[1]; }
function loom_global_avatar_preset_valid(string $mode): bool { return loom_global_avatar_preset_normalize($mode)!==null; }
function loom_global_avatar_repair_preset(string $type,string $id): string { $n=(hexdec(substr(hash('sha256','loom-avatar|'.$type.'|'.$id),0,8))%10)+1;return sprintf('loom-default-preset-%02d',$n); }
function loom_global_avatar_random_preset(): string { try{$n=random_int(1,10);}catch(Throwable $e){$n=(time()%10)+1;}return sprintf('loom-default-preset-%02d',$n); }
function loom_global_profile_id(string $type,string $id): string { return 'gprof_'.substr(hash('sha256',loom_global_profile_key($type,$id)),0,20); }
function loom_global_profile_db_table(): void {
  if(!loom_db_ready())return; static $done=false;if($done)return;
  try{$pdo=loom_db_pdo(true);$pdo->exec("CREATE TABLE IF NOT EXISTS loom_global_profiles (owner_type ENUM('client','user') NOT NULL,owner_id VARCHAR(96) NOT NULL,profile_id VARCHAR(96) NOT NULL,username VARCHAR(64) NOT NULL,username_norm VARCHAR(64) NOT NULL,display_name VARCHAR(80) NULL,avatar_mode VARCHAR(32) NOT NULL DEFAULT 'loom-default-preset-01',custom_ext VARCHAR(12) NULL,mime_type VARCHAR(64) NULL,byte_size INT UNSIGNED NULL,created_at DATETIME(3) NOT NULL,updated_at DATETIME(3) NOT NULL,PRIMARY KEY(owner_type,owner_id),UNIQUE KEY uq_global_profile_username(username_norm),UNIQUE KEY uq_global_profile_id(profile_id),INDEX idx_global_profile_updated(updated_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");try{$cols=$pdo->query("SHOW COLUMNS FROM loom_global_profiles LIKE 'display_name'")->fetchAll();if(!$cols)$pdo->exec("ALTER TABLE loom_global_profiles ADD COLUMN display_name VARCHAR(80) NULL AFTER username_norm");}catch(Throwable $ignored){}$done=true;}catch(Throwable $e){}
}
function loom_global_profile_owner_for_client(string $clientId): array { $linked=loom_account_user_for_client($clientId);$uid=(string)($linked['user_id']??$linked['userId']??'');if($uid!=='')return ['type'=>'user','id'=>$uid,'user'=>$linked];$auth=loom_auth_user();$uid=(string)($auth['user_id']??$auth['userId']??'');if($uid!=='')return ['type'=>'user','id'=>$uid,'user'=>$auth];$id=function_exists('loom_guest_canonical_client_id')?loom_guest_canonical_client_id($clientId):safe_token($clientId);return ['type'=>'client','id'=>$id,'user'=>null]; }
function loom_global_profile_assert_mutation_access(string $clientId): void { $linked=loom_account_user_for_client($clientId);if(!$linked)return;$linkedId=(string)($linked['user_id']??$linked['userId']??'');$auth=loom_auth_user();$authId=(string)($auth['user_id']??$auth['userId']??'');if($linkedId!==''&&($authId===''||!hash_equals($linkedId,$authId)))throw new RuntimeException('Sign in to your permanent LOOM account before changing your LOOM profile.'); }
function loom_global_profile_normalize(array $r): array { $username=(string)($r['username']??'');$display=loom_clean_username((string)($r['display_name']??$r['displayName']??''));if($display===''&&!preg_match('/^(?:GuestHandle-|ProfileHandle-|acct_)/i',$username))$display=loom_clean_username($username);return ['ownerType'=>(string)($r['owner_type']??$r['ownerType']??'client'),'ownerId'=>(string)($r['owner_id']??$r['ownerId']??''),'profileId'=>(string)($r['profile_id']??$r['profileId']??''),'username'=>$username,'usernameNorm'=>(string)($r['username_norm']??$r['usernameNorm']??''),'displayName'=>$display?:null,'avatarMode'=>(string)($r['avatar_mode']??$r['avatarMode']??'loom-default'),'customExt'=>$r['custom_ext']??$r['customExt']??null,'mimeType'=>$r['mime_type']??$r['mimeType']??null,'byteSize'=>isset($r['byte_size'])?(int)$r['byte_size']:($r['byteSize']??null),'createdAt'=>$r['created_at']??$r['createdAt']??null,'updatedAt'=>$r['updated_at']??$r['updatedAt']??null]; }
function loom_global_profile_get(string $type,string $id): ?array { $id=safe_token($id);if($id===''||!in_array($type,['client','user'],true))return null;loom_global_profile_db_table();if(loom_db_ready())try{$pdo=loom_db_pdo(true);$st=$pdo->prepare("SELECT * FROM loom_global_profiles WHERE owner_type=? AND owner_id=?");$st->execute([$type,$id]);$r=$st->fetch();if($r)return loom_global_profile_normalize($r);}catch(Throwable $e){}$s=loom_global_profile_store();$r=$s['profiles'][loom_global_profile_key($type,$id)]??null;return is_array($r)?loom_global_profile_normalize($r):null; }
function loom_global_profile_username_owner(string $username): ?array { $norm=loom_username_norm($username);if($norm==='')return null;loom_global_profile_db_table();if(loom_db_ready())try{$pdo=loom_db_pdo(true);$st=$pdo->prepare("SELECT * FROM loom_global_profiles WHERE username_norm=?");$st->execute([$norm]);$r=$st->fetch();if($r)return loom_global_profile_normalize($r);}catch(Throwable $e){}foreach((loom_global_profile_store()['profiles']??[]) as $r){$n=loom_global_profile_normalize($r);if($n['usernameNorm']===$norm)return $n;}return null; }
function loom_global_default_username(string $type,string $id): string {
  // Human-readable, deterministic defaults: two friendly maker words + six hex chars.
  // A new word-pair is tried on collision, so generated suggestions stay clean rather than growing -2/-3 tails.
  $first=['App','Pixel','Nova','Cloud','Code','Circuit','Bright','Swift','Open','Prime','Core','Craft','Logic','Vector','Orbit','Spark','Mesh','Data','Stack','Hyper','Mod','Byte','Echo','Flux','Grid','Node','Atlas','Mint','Signal','Studio','Build','Frame'];
  $second=['Weaver','Maker','Pilot','Smith','Builder','Crafter','Runner','Forge','Works','Lab','Scout','Flow','Architect','Driver','Ranger','Engine','Foundry','Canvas','Link','Stack','Spark','Nest','Deck','Bridge','Pulse','Loop','Path','Wave','Grid','Kit','Dock','Shift'];
  $seed=$type.'|'.$id;
  for($attempt=0;$attempt<1024;$attempt++){
    $h=strtoupper(hash('sha256',$seed.'|'.$attempt));
    $a=$first[hexdec(substr($h,0,4))%count($first)];$b=$second[hexdec(substr($h,4,4))%count($second)];$suffix=substr($h,8,6);
    $candidate=$a.$b.$suffix;$o=loom_global_profile_username_owner($candidate);
    if(!$o||($o['ownerType']===$type&&$o['ownerId']===$id))return $candidate;
  }
  // Practically unreachable, but retain a collision-safe readable fallback.
  for($i=0;$i<4096;$i++){$candidate='AppWeaver'.strtoupper(substr(hash('sha256',$seed.'|fallback|'.$i),0,6));if(!loom_global_profile_username_owner($candidate))return $candidate;}
  throw new RuntimeException('LOOM could not allocate a unique default username.');
}

function loom_global_profile_display_name(array $profile): string {
  $p=loom_global_profile_normalize($profile);$n=loom_clean_username((string)($p['displayName']??''));if($n!==''&&!loom_visible_name_is_internal($n))return $n;
  $u=loom_clean_username((string)($p['username']??''));return ($u!==''&&!loom_visible_name_is_internal($u))?$u:'';
}
function loom_global_profile_visible_name_for_owner(string $type,string $id): string { $p=loom_global_profile_ensure_for_owner($type,$id);return loom_global_profile_display_name($p); }
function loom_global_profile_write(array $row): array { $r=loom_global_profile_normalize($row);$type=$r['ownerType'];$id=safe_token($r['ownerId']);if(!in_array($type,['client','user'],true)||$id==='')throw new RuntimeException('Invalid LOOM profile.');$r['ownerId']=$id;$r['profileId']=$r['profileId']?:loom_global_profile_id($type,$id);$r['username']=loom_clean_username($r['username']);if($r['username']==='')throw new RuntimeException('LOOM username cannot be empty.');$r['usernameNorm']=loom_username_norm($r['username']);$r['displayName']=loom_clean_username((string)($r['displayName']??''))?:null;$mode=(string)($r['avatarMode']??'');$canonical=loom_global_avatar_preset_normalize($mode);$r['avatarMode']=$mode==='custom'?'custom':($canonical??loom_global_avatar_repair_preset($type,$id));$r['createdAt']=$r['createdAt']?:server_timestamp();$r['updatedAt']=$r['updatedAt']?:server_timestamp();$other=loom_global_profile_username_owner($r['username']);if($other&&!(($other['ownerType']===$type)&&($other['ownerId']===$id)))throw new RuntimeException('That LOOM username is already taken.');
  if(loom_global_profile_get($type,$id)!==null && function_exists('loom_project_identity_global_username_conflicts')){$candidate=loom_global_profile_display_name($r);if($candidate!==''&&loom_project_identity_global_username_conflicts($type,$id,$candidate))throw new RuntimeException('That LOOM username conflicts with another identity inside a project that inherits the LOOM username.');}
  $s=loom_global_profile_store();$s['profiles'][loom_global_profile_key($type,$id)]=$r;loom_write_global_profile_store($s);loom_global_profile_db_table();if(loom_db_ready()){$pdo=loom_db_pdo(true);$st=$pdo->prepare("INSERT INTO loom_global_profiles(owner_type,owner_id,profile_id,username,username_norm,display_name,avatar_mode,custom_ext,mime_type,byte_size,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE profile_id=VALUES(profile_id),username=VALUES(username),username_norm=VALUES(username_norm),display_name=VALUES(display_name),avatar_mode=VALUES(avatar_mode),custom_ext=VALUES(custom_ext),mime_type=VALUES(mime_type),byte_size=VALUES(byte_size),updated_at=VALUES(updated_at)");$st->execute([$type,$id,$r['profileId'],$r['username'],$r['usernameNorm'],$r['displayName'],$r['avatarMode'],$r['customExt'],$r['mimeType'],$r['byteSize'],loom_db_dt($r['createdAt'])?:gmdate('Y-m-d H:i:s.000'),loom_db_dt($r['updatedAt'])?:gmdate('Y-m-d H:i:s.000')]);}return $r;
}
function loom_global_profile_owner_clients(string $type,string $id): array {
  $ids=[];$add=function($v)use(&$ids){$v=safe_token((string)$v);if($v!==''&&str_starts_with($v,'client_'))$ids[$v]=true;};
  if($type==='client')$add($id);
  if($type==='user'){$uid=safe_token($id);$tmp=loom_temp_account_store();foreach(($tmp['clients']??[]) as $cid=>$mapped)if(safe_token((string)$mapped)===$uid)$add($cid);if(loom_db_ready())try{$st=loom_db_pdo(true)->prepare("SELECT client_id FROM loom_user_clients WHERE user_id=?");$st->execute([$uid]);foreach($st->fetchAll() as $x)$add($x['client_id']??'');}catch(Throwable $e){}if(function_exists('loom_guest_list_for_user'))foreach(loom_guest_list_for_user($uid) as $g)$add($g['primaryClientId']??'');}
  return array_keys($ids);
}
function loom_global_profile_seed_display_name(string $type,string $id): string {
  $clean=function($raw){$n=loom_clean_username((string)$raw);return ($n!==''&&!preg_match('/^(?:GuestHandle-|ProfileHandle-|acct_|LOOMUser-)/i',$n))?$n:'';};
  foreach(loom_global_profile_owner_clients($type,$id) as $cid){
    if(function_exists('loom_guest_profile_for_client')){$guest=loom_guest_profile_for_client($cid);if(is_array($guest)){$n=$clean($guest['displayName']??'');if($n!=='')return $n;}}
    $p=read_json_file(loom_data_dir().'/users/'.hash('sha256',$cid).'.json')?:[];if(loom_db_ready()&&function_exists('loom_db_read_client_profile')){$dbp=loom_db_read_client_profile($cid);if(is_array($dbp))$p=array_replace($p,$dbp);}foreach(['displayName','display_name','username','_userLabel','userLabel'] as $k){$n=$clean($p[$k]??'');if($n!=='')return $n;}
  }
  if($type==='user'){$u=loom_account_user_by_id($id);if(is_array($u)){foreach(['displayName','display_name','username'] as $k){$n=$clean($u[$k]??'');if($n!=='')return $n;}}}
  return '';
}
// Internal global handles are allocation keys, not presentation identity. Never
// seed a new LOOM-wide profile from a project-specific row: that made an old
// copied project name capable of becoming global again after a reset.
function loom_global_profile_seed_username(string $type,string $id): string { return loom_global_default_username($type,$id); }
function loom_global_profile_ensure_for_owner(string $type,string $id): array {
  $r=loom_global_profile_get($type,$id);
  if($r){
    $dirty=false;
    if(preg_match('/^LOOMUser-[0-9A-F]{6}(?:-[0-9]+)?$/i',(string)$r['username'])){$r['username']=loom_global_default_username($type,$id);$dirty=true;}
    $visible=loom_clean_username((string)($r['displayName']??''));
    if($visible===''||loom_visible_name_is_internal($visible)){$seedDisplay=loom_global_profile_seed_display_name($type,$id);if($seedDisplay===''||loom_visible_name_is_internal($seedDisplay))$seedDisplay=loom_global_default_username($type,$id);$r['displayName']=$seedDisplay;$dirty=true;}
    if((string)($r['avatarMode']??'')==='loom-default'||(!loom_global_avatar_preset_valid((string)($r['avatarMode']??''))&&(string)($r['avatarMode']??'')!=='custom')){$r['avatarMode']=loom_global_avatar_repair_preset($type,$id);$dirty=true;}
    elseif(($canonical=loom_global_avatar_preset_normalize((string)($r['avatarMode']??'')))!==null&&$canonical!==(string)$r['avatarMode']){$r['avatarMode']=$canonical;$dirty=true;}
    if((string)($r['avatarMode']??'')==='custom'&&!loom_global_avatar_find($type,$id)){$r['avatarMode']=loom_global_avatar_repair_preset($type,$id);$r['customExt']=$r['mimeType']=$r['byteSize']=null;$dirty=true;}
    if($dirty){$r['updatedAt']=server_timestamp();return loom_global_profile_write($r);}return $r;
  }
  $handle=loom_global_profile_seed_username($type,$id);$display=loom_global_profile_seed_display_name($type,$id);if($display==='')$display=$handle;
  return loom_global_profile_write(['ownerType'=>$type,'ownerId'=>$id,'profileId'=>loom_global_profile_id($type,$id),'username'=>$handle,'displayName'=>$display,'avatarMode'=>loom_global_avatar_random_preset(),'createdAt'=>server_timestamp(),'updatedAt'=>server_timestamp()]);
}
function loom_global_profile_public(array $profile,string $clientId=''): array {
  $p=loom_global_profile_normalize($profile);$visible=loom_global_profile_display_name($p);
  if($visible===''&&$clientId!==''&&function_exists('loom_guest_profile_visible_name_for_client'))$visible=loom_guest_profile_visible_name_for_client($clientId);
  if($visible==='')$visible=loom_global_default_username((string)($p['ownerType']??'client'),(string)($p['ownerId']??($clientId?:'unknown')));
  $internal=(string)($p['username']??'');$p['visibleUsername']=$visible;$p['displayName']=$visible;$p['internalHandle']=$internal?:null;
  return $p;
}
function loom_global_profile_ensure(string $clientId): array { $o=loom_global_profile_owner_for_client($clientId);return loom_global_profile_ensure_for_owner($o['type'],$o['id']); }
function loom_global_profile_set_username(string $clientId,string $username): array {
  loom_global_profile_assert_mutation_access($clientId);$name=loom_clean_username($username);if($name==='')throw new RuntimeException('LOOM display name cannot be empty.');$o=loom_global_profile_owner_for_client($clientId);
  // Display names are presentation data. For an unauthenticated Guest, the
  // canonical source is the Guest Profile that the profile UI reads back. The
  // pre-0.15.58 path wrote only the compatibility global-client row, so the UI
  // immediately appeared to "snap back" to the unchanged Guest Profile name.
  if(($o['type']??'')==='client'&&function_exists('loom_guest_profile_set_display_name_for_client')){
    $guest=loom_guest_profile_set_display_name_for_client($clientId,$name);if($guest){$ownerId=function_exists('loom_guest_canonical_client_id')?loom_guest_canonical_client_id($clientId):$clientId;return loom_global_profile_ensure_for_owner('client',$ownerId);}
  }
  $r=loom_global_profile_ensure_for_owner((string)$o['type'],(string)$o['id']);$r['displayName']=$name;$r['updatedAt']=server_timestamp();return loom_global_profile_write($r);
}
function loom_global_profile_admin_set_username(string $type,string $id,string $username): array { $r=loom_global_profile_ensure_for_owner($type,$id);$name=loom_clean_username($username);if($name==='')throw new RuntimeException('LOOM display name cannot be empty.');$r['displayName']=$name;$r['updatedAt']=server_timestamp();return loom_global_profile_write($r); }
function loom_global_profile_list_all(): array { $out=[];loom_global_profile_db_table();if(loom_db_ready())try{$pdo=loom_db_pdo(true);foreach($pdo->query("SELECT * FROM loom_global_profiles") as $r){$n=loom_global_profile_normalize($r);$out[loom_global_profile_key($n['ownerType'],$n['ownerId'])]=$n;}}catch(Throwable $e){}foreach((loom_global_profile_store()['profiles']??[]) as $r){$n=loom_global_profile_normalize($r);$k=loom_global_profile_key($n['ownerType'],$n['ownerId']);if(!isset($out[$k]))$out[$k]=$n;}return array_values($out); }
function loom_global_profile_promote_client(string $clientId,string $userId): void { $clientId=safe_token($clientId);$userId=safe_token($userId);$c=loom_global_profile_get('client',$clientId);if(!$c)return;$u=loom_global_profile_get('user',$userId);if($u){$s=loom_global_profile_store();unset($s['profiles'][loom_global_profile_key('client',$clientId)]);loom_write_global_profile_store($s);if(loom_db_ready())try{loom_db_pdo(true)->prepare("DELETE FROM loom_global_profiles WHERE owner_type='client' AND owner_id=?")->execute([$clientId]);}catch(Throwable $e){}loom_global_avatar_promote_client($clientId,$userId);return;}
  // Temporarily release the client's global-username reservation, then recreate
  // the exact same profile under the durable user owner. Roll back on failure.
  $store=loom_global_profile_store();$clientKey=loom_global_profile_key('client',$clientId);unset($store['profiles'][$clientKey]);loom_write_global_profile_store($store);if(loom_db_ready())try{loom_db_pdo(true)->prepare("DELETE FROM loom_global_profiles WHERE owner_type='client' AND owner_id=?")->execute([$clientId]);}catch(Throwable $e){}
  $copy=$c;$copy['ownerType']='user';$copy['ownerId']=$userId;$copy['profileId']=loom_global_profile_id('user',$userId);$copy['updatedAt']=server_timestamp();try{loom_global_profile_write($copy);loom_global_avatar_promote_client($clientId,$userId);}catch(Throwable $e){$restore=$c;$restore['ownerType']='client';$restore['ownerId']=$clientId;$restore['profileId']=loom_global_profile_id('client',$clientId);try{loom_global_profile_write($restore);}catch(Throwable $ignored){}} }

function loom_global_avatar_dir(): string { $d=loom_data_dir().'/avatars/global-profiles';ensure_dir($d);return $d; }
function loom_global_avatar_base(string $type,string $id): string { return loom_global_avatar_dir().'/'.$type.'_'.hash('sha256',$id); }
function loom_global_avatar_find(string $type,string $id): ?array { $base=loom_global_avatar_base($type,$id);foreach(['webp'=>'image/webp','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg'] as $ext=>$mime){$f=$base.'.'.$ext;if(is_file($f))return ['file'=>$f,'ext'=>$ext,'mime'=>$mime,'size'=>(int)filesize($f),'updatedAt'=>gmdate('c',(int)filemtime($f))];}return null; }
function loom_global_avatar_delete(string $type,string $id): void { $base=loom_global_avatar_base($type,$id);foreach(['webp','png','jpg','jpeg'] as $e){$f=$base.'.'.$e;if(is_file($f))@unlink($f);} }
function loom_global_avatar_state(string $clientId): array { $o=loom_global_profile_owner_for_client($clientId);$p=loom_global_profile_ensure_for_owner($o['type'],$o['id']);$f=loom_global_avatar_find($o['type'],$o['id']);$mode=$p['avatarMode'];if($mode==='custom'&&!$f){$mode=loom_global_avatar_repair_preset($o['type'],$o['id']);$p['avatarMode']=$mode;$p['updatedAt']=server_timestamp();loom_global_profile_write($p);}$presetUrl=loom_global_avatar_preset_valid($mode)?web_base_path().'/assets/avatars/defaults/avatar-'.substr($mode,-2).'.svg':null;return ['mode'=>$mode,'ownerType'=>$o['type'],'ownerId'=>$o['id'],'profileId'=>$p['profileId'],'username'=>loom_global_profile_display_name($p),'internalHandle'=>$p['username'],'customAvailable'=>(bool)$f,'customUpdatedAt'=>$f['updatedAt']??$p['updatedAt'],'customBytes'=>$f['size']??$p['byteSize'],'customMime'=>$f['mime']??$p['mimeType'],'presetUrl'=>$presetUrl]; }
function loom_global_avatar_set_mode(string $clientId,string $mode): array { loom_global_profile_assert_mutation_access($clientId);$o=loom_global_profile_owner_for_client($clientId);if($mode==='loom-default'||$mode==='auto'||$mode==='')$mode=loom_global_avatar_repair_preset($o['type'],$o['id']);$canonical=loom_global_avatar_preset_normalize($mode);if($mode!=='custom'&&$canonical!==null)$mode=$canonical;if($mode!=='custom'&&$canonical===null)throw new RuntimeException('Invalid LOOM profile picture mode.');if($mode==='custom'&&!loom_global_avatar_find($o['type'],$o['id']))throw new RuntimeException('No custom LOOM profile picture is stored yet.');$p=loom_global_profile_ensure_for_owner($o['type'],$o['id']);$p['avatarMode']=$mode;$p['updatedAt']=server_timestamp();loom_global_profile_write($p);if(function_exists('loom_identity_audit'))loom_identity_audit('global-profile.avatar-mode.updated',['clientId'=>$clientId,'ownerType'=>$o['type'],'ownerId'=>$o['id'],'mode'=>$mode]);return loom_global_avatar_state($clientId); }
function loom_global_avatar_store(string $clientId,string $tmp,string $mime,int $bytes): array { $map=['image/webp'=>'webp','image/png'=>'png','image/jpeg'=>'jpg'];if(!isset($map[$mime]))throw new RuntimeException('Avatar must be WebP, PNG, or JPEG after compression.');if($bytes<=0||$bytes>1572864)throw new RuntimeException('Compressed avatar is too large.');$dims=@getimagesize($tmp);if(!$dims||($dims[0]??0)<1||($dims[1]??0)<1||($dims[0]??0)>1024||($dims[1]??0)>1024)throw new RuntimeException('Invalid avatar dimensions.');loom_global_profile_assert_mutation_access($clientId);$o=loom_global_profile_owner_for_client($clientId);loom_global_avatar_delete($o['type'],$o['id']);$dest=loom_global_avatar_base($o['type'],$o['id']).'.'.$map[$mime];if(!@move_uploaded_file($tmp,$dest)){if(!@rename($tmp,$dest)&&!@copy($tmp,$dest))throw new RuntimeException('Could not store LOOM profile picture.');}@chmod($dest,0664);$p=loom_global_profile_ensure_for_owner($o['type'],$o['id']);$p['avatarMode']='custom';$p['customExt']=$map[$mime];$p['mimeType']=$mime;$p['byteSize']=(int)filesize($dest);$p['updatedAt']=server_timestamp();loom_global_profile_write($p);if(function_exists('loom_identity_audit'))loom_identity_audit('global-profile.avatar.uploaded',['clientId'=>$clientId,'ownerType'=>$o['type'],'ownerId'=>$o['id'],'mime'=>$mime,'bytes'=>$p['byteSize']]);return loom_global_avatar_state($clientId); }
function loom_global_avatar_copy_trusted_source(string $clientId,string $source,string $mime,int $bytes): array {
  // Project-provided avatar assets are trusted server files, not raw browser
  // uploads. They may legitimately be larger than the browser's 384/1024px
  // compression envelope, so validate that they are real reasonable images
  // without applying the upload-only dimension ceiling.
  $map=['image/webp'=>'webp','image/png'=>'png','image/jpeg'=>'jpg'];
  if(!isset($map[$mime]))throw new RuntimeException('That project profile picture format cannot be copied into the LOOM profile.');
  if(!is_file($source)||$bytes<=0||$bytes>1572864)throw new RuntimeException('That project profile picture is unavailable or too large to copy.');
  $dims=@getimagesize($source);
  if(!$dims||($dims[0]??0)<1||($dims[1]??0)<1)throw new RuntimeException('That project profile picture is not a valid image.');
  if(($dims[0]??0)>4096||($dims[1]??0)>4096)throw new RuntimeException('That project profile picture is unexpectedly large.');
  loom_global_profile_assert_mutation_access($clientId);
  $o=loom_global_profile_owner_for_client($clientId);
  loom_global_avatar_delete($o['type'],$o['id']);
  $dest=loom_global_avatar_base($o['type'],$o['id']).'.'.$map[$mime];
  if(!@copy($source,$dest))throw new RuntimeException('Could not copy that project profile picture into your LOOM profile.');
  @chmod($dest,0664);
  $p=loom_global_profile_ensure_for_owner($o['type'],$o['id']);
  $p['avatarMode']='custom';$p['customExt']=$map[$mime];$p['mimeType']=$mime;$p['byteSize']=(int)filesize($dest);$p['updatedAt']=server_timestamp();
  loom_global_profile_write($p);
  return loom_global_avatar_state($clientId);
}
function loom_global_avatar_promote_client(string $clientId,string $userId): void { foreach(['webp','png','jpg','jpeg'] as $e){$from=loom_global_avatar_base('client',$clientId).'.'.$e;$to=loom_global_avatar_base('user',$userId).'.'.$e;if(is_file($from)){if(!is_file($to))@rename($from,$to);else @unlink($from);}} }
function loom_global_profile_migrate_temp_to_db(): int { if(!loom_db_ready())return 0;$n=0;foreach((loom_global_profile_store()['profiles']??[]) as $r){try{loom_global_profile_write(loom_global_profile_normalize($r));$n++;}catch(Throwable $e){}}return $n; }
