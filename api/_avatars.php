<?php
// @loom-file release=0.15.56 revision=5 policy=package-priority
declare(strict_types=1);

function loom_avatar_root(): string { return loom_data_dir().'/avatars'; }
function loom_avatar_clients_dir(): string { return loom_avatar_root().'/clients'; } // legacy v0.11.06-
function loom_avatar_users_dir(): string { return loom_avatar_root().'/users'; }     // legacy v0.11.06-
function loom_avatar_prefs_dir(): string { return loom_avatar_root().'/preferences'; } // legacy
function loom_avatar_project_root(): string { return loom_avatar_root().'/project-identities'; }
function loom_avatar_ensure_dirs(): void {
  foreach([loom_avatar_root(),loom_avatar_clients_dir(),loom_avatar_users_dir(),loom_avatar_prefs_dir(),loom_avatar_project_root()] as $d)ensure_dir($d);
  $deny=loom_avatar_root().'/.htaccess';if(!is_file($deny))@file_put_contents($deny,"Require all denied\n",LOCK_EX);
}
function loom_avatar_hash(string $id): string { return hash('sha256',$id); }
function loom_avatar_owner_for_client(string $clientId): array {
  $linked=loom_account_user_for_client($clientId);$uid=(string)($linked['user_id']??$linked['userId']??'');
  if($uid!=='')return ['type'=>'user','id'=>$uid];
  $id=function_exists('loom_guest_canonical_client_id')?loom_guest_canonical_client_id($clientId):$clientId;
  return ['type'=>'client','id'=>$id];
}
function loom_avatar_assert_mutation_access(string $clientId): void { loom_project_identity_assert_mutation_access($clientId); }
function loom_avatar_project_dir(string $project): string { loom_avatar_ensure_dirs();$d=loom_avatar_project_root().'/'.safe_slug($project);ensure_dir($d);return $d; }
function loom_avatar_base_path(string $project,string $type,string $id): string { return loom_avatar_project_dir($project).'/'.$type.'_'.loom_avatar_hash($id); }
function loom_avatar_find_file(string $project,string $type,string $id): ?array {
  $base=loom_avatar_base_path($project,$type,$id);foreach(['webp'=>'image/webp','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg'] as $ext=>$mime){$f=$base.'.'.$ext;if(is_file($f))return ['file'=>$f,'ext'=>$ext,'mime'=>$mime,'size'=>(int)@filesize($f),'updatedAt'=>gmdate('c',(int)@filemtime($f))];}return null;
}
function loom_avatar_delete_files(string $project,string $type,string $id): void { $base=loom_avatar_base_path($project,$type,$id);foreach(['webp','png','jpg','jpeg'] as $ext){$f=$base.'.'.$ext;if(is_file($f))@unlink($f);} }

// Legacy global avatar helpers are read-only migration sources.
function loom_avatar_legacy_base_path(string $type,string $id): string { loom_avatar_ensure_dirs();return ($type==='user'?loom_avatar_users_dir():loom_avatar_clients_dir()).'/'.loom_avatar_hash($id); }
function loom_avatar_legacy_find_file(string $type,string $id): ?array {$base=loom_avatar_legacy_base_path($type,$id);foreach(['webp'=>'image/webp','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg'] as $ext=>$mime){$f=$base.'.'.$ext;if(is_file($f))return ['file'=>$f,'ext'=>$ext,'mime'=>$mime,'size'=>(int)@filesize($f),'updatedAt'=>gmdate('c',(int)@filemtime($f))];}return null;}
function loom_avatar_legacy_pref(string $type,string $id): array {
  if($type==='user'){
    if(loom_db_ready())try{$pdo=loom_db_pdo(true);$st=$pdo->prepare("SELECT * FROM loom_user_avatar_profiles WHERE user_id=?");$st->execute([$id]);$r=$st->fetch();if($r)return ['mode'=>$r['mode']?:'auto','customExt'=>$r['custom_ext']?:null,'mimeType'=>$r['mime_type']?:null,'byteSize'=>$r['byte_size']!==null?(int)$r['byte_size']:null,'updatedAt'=>$r['updated_at']?:null];}catch(Throwable $e){}
    return read_json_file(loom_avatar_prefs_dir().'/user_'.loom_avatar_hash($id).'.json')?:['mode'=>'auto'];
  }
  $p=read_json_file(loom_data_dir().'/users/'.hash('sha256',$id).'.json')?:[];return ['mode'=>$p['avatarMode']??'auto','updatedAt'=>$p['avatarUpdatedAt']??null];
}
function loom_avatar_migration_file(): string { loom_avatar_ensure_dirs();return loom_avatar_prefs_dir().'/project-identity-migrations.json'; }
function loom_avatar_migrate_legacy_once(string $project,string $clientId): void {
  $owner=loom_avatar_owner_for_client($clientId);$key=$owner['type'].':'.$owner['id'];$m=read_json_file(loom_avatar_migration_file())?:['migrated'=>[]];if(isset($m['migrated'][$key]))return;
  $pref=loom_avatar_legacy_pref($owner['type'],$owner['id']);$legacy=loom_avatar_legacy_find_file($owner['type'],$owner['id']);$mode=(string)($pref['mode']??'auto');
  $meta=['avatarMode'=>in_array($mode,['auto','loom-default','project-default','custom'],true)?$mode:'auto'];
  if($legacy){$dest=loom_avatar_base_path($project,$owner['type'],$owner['id']).'.'.$legacy['ext'];if(@copy($legacy['file'],$dest)){$meta=['avatarMode'=>'custom','customExt'=>$legacy['ext'],'mimeType'=>$legacy['mime'],'byteSize'=>(int)@filesize($dest)];}}
  try{loom_project_identity_set_avatar_meta($project,$owner['type'],$owner['id'],$meta);}catch(Throwable $e){}
  $m['migrated'][$key]=['project'=>safe_slug($project),'migratedAt'=>server_timestamp()];@file_put_contents(loom_avatar_migration_file(),json_encode($m,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
}
function loom_avatar_state(string $clientId,string $project): array {
  $project=safe_slug($project);if($project===''||!project_dir($project))throw new RuntimeException('Invalid project.');
  loom_project_identity_ensure($project,$clientId,true);loom_avatar_migrate_legacy_once($project,$clientId);$owner=loom_avatar_owner_for_client($clientId);$ident=loom_project_identity_get_for_owner($project,$owner['type'],$owner['id']);$mode=(string)($ident['avatarMode']??'auto');
  if(!in_array($mode,['auto','global','loom-default','project-default','custom'],true))$mode='auto';$custom=loom_avatar_find_file($project,$owner['type'],$owner['id']);if($mode==='custom'&&!$custom)$mode='auto';
  return ['mode'=>$mode,'ownerType'=>$owner['type'],'ownerId'=>$owner['id'],'project'=>$project,'customAvailable'=>(bool)$custom,'customUpdatedAt'=>$custom['updatedAt']??($ident['updatedAt']??null),'customBytes'=>$custom['size']??($ident['byteSize']??null),'customMime'=>$custom['mime']??($ident['mimeType']??null),'globalAvatar'=>loom_global_avatar_state($clientId)];
}
function loom_avatar_set_mode(string $clientId,string $project,string $mode): array {
  if(!in_array($mode,['auto','global','loom-default','project-default','custom'],true))throw new RuntimeException('Invalid avatar mode.');loom_avatar_assert_mutation_access($clientId);$owner=loom_avatar_owner_for_client($clientId);$custom=loom_avatar_find_file($project,$owner['type'],$owner['id']);if($mode==='custom'&&!$custom)throw new RuntimeException('No custom avatar has been uploaded for this project identity yet.');loom_project_identity_set_avatar_meta($project,$owner['type'],$owner['id'],['avatarMode'=>$mode]);return loom_avatar_state($clientId,$project);
}
function loom_avatar_store_file(string $clientId,string $project,string $tmp,string $mime,int $bytes): array {
  $map=['image/webp'=>'webp','image/png'=>'png','image/jpeg'=>'jpg'];if(!isset($map[$mime]))throw new RuntimeException('Avatar must be WebP, PNG, or JPEG after compression.');if($bytes<=0||$bytes>1572864)throw new RuntimeException('Compressed avatar is too large. Maximum stored size is 1.5 MB.');$dims=@getimagesize($tmp);if(!$dims||($dims[0]??0)<1||($dims[1]??0)<1)throw new RuntimeException('Uploaded avatar is not a valid image.');if(($dims[0]??0)>1024||($dims[1]??0)>1024)throw new RuntimeException('Avatar dimensions are unexpectedly large. Please retry browser compression.');
  loom_avatar_assert_mutation_access($clientId);$project=safe_slug($project);loom_project_identity_ensure($project,$clientId,true);$owner=loom_avatar_owner_for_client($clientId);loom_avatar_delete_files($project,$owner['type'],$owner['id']);$dest=loom_avatar_base_path($project,$owner['type'],$owner['id']).'.'.$map[$mime];if(!@move_uploaded_file($tmp,$dest)){if(!@rename($tmp,$dest)&&!@copy($tmp,$dest))throw new RuntimeException('Could not store avatar image.');}@chmod($dest,0664);loom_project_identity_set_avatar_meta($project,$owner['type'],$owner['id'],['avatarMode'=>'custom','customExt'=>$map[$mime],'mimeType'=>$mime,'byteSize'=>(int)@filesize($dest)]);return loom_avatar_state($clientId,$project);
}
function loom_avatar_promote_client_to_user(string $clientId,string $userId): void {
  $clientHash=loom_avatar_hash($clientId);$userHash=loom_avatar_hash($userId);$base=loom_avatar_project_root();if(!is_dir($base))return;foreach(glob($base.'/*')?:[] as $dir){if(!is_dir($dir))continue;$project=basename($dir);foreach(['webp','png','jpg','jpeg'] as $ext){$from=$dir.'/client_'.$clientHash.'.'.$ext;$to=$dir.'/user_'.$userHash.'.'.$ext;if(is_file($from)){if(!is_file($to))@rename($from,$to);else @unlink($from);}}}
}


// v0.11.20 — project avatar retrieval for LOOM-global profile reuse.
function loom_avatar_project_provider_default_file(string $project): ?array {
  $dir=project_dir($project);if(!$dir)return null;$root=$dir.'/actions';if(!is_dir($root))return null;
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
  foreach($it as $file){
    if(!$file->isFile()||strtolower($file->getFilename())!=='manifest.json')continue;
    $m=read_json_file($file->getPathname());if(!$m||($m['enabled']??true)===false)continue;
    $ext=$m['extensions']['core.user.profile.avatar']??null;if(!is_array($ext))continue;
    $asset=(string)($ext['default_asset']??$ext['defaultAsset']??'');
    if($asset===''&&($ext['default']??false))$asset='assets/default-avatar.png';
    if($asset==='')continue;
    $candidate=realpath($file->getPath().'/'.$asset);if(!$candidate||!is_file($candidate)||!str_starts_with($candidate,$dir.DIRECTORY_SEPARATOR))continue;
    $fi=new finfo(FILEINFO_MIME_TYPE);$mime=(string)$fi->file($candidate);if(!in_array($mime,['image/webp','image/png','image/jpeg'],true))continue;
    return ['kind'=>'project-default','file'=>$candidate,'mime'=>$mime,'size'=>(int)filesize($candidate),'project'=>safe_slug($project)];
  }
  return null;
}
function loom_avatar_effective_source(string $clientId,string $project): array {
  $project=safe_slug($project);$state=loom_avatar_state($clientId,$project);$owner=loom_avatar_owner_for_client($clientId);$mode=(string)($state['mode']??'auto');
  if($mode==='custom'){$f=loom_avatar_find_file($project,$owner['type'],$owner['id']);if($f)return ['kind'=>'project-custom','file'=>$f['file'],'mime'=>$f['mime'],'size'=>$f['size'],'project'=>$project];}
  if(in_array($mode,['auto','project-default'],true)){$f=loom_avatar_project_provider_default_file($project);if($f)return $f;}
  if(in_array($mode,['auto','global'],true)){$go=loom_global_profile_owner_for_client($clientId);$gf=loom_global_avatar_find($go['type'],$go['id']);$gs=loom_global_avatar_state($clientId);if(($gs['mode']??'')==='custom'&&$gf)return ['kind'=>'global-custom','file'=>$gf['file'],'mime'=>$gf['mime'],'size'=>$gf['size'],'project'=>$project];if(preg_match('/^preset-(0[1-9]|10)$/',(string)($gs['mode']??''))){$pf=root_dir().'/assets/avatars/defaults/avatar-'.substr((string)$gs['mode'],-2).'.svg';if(is_file($pf))return ['kind'=>'global-preset','file'=>$pf,'mime'=>'image/svg+xml','size'=>(int)filesize($pf),'project'=>$project,'presetMode'=>$gs['mode']];}}
  return ['kind'=>'loom-default','file'=>null,'mime'=>null,'size'=>0,'project'=>$project];
}
