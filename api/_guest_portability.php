<?php
// @loom-file release=0.15.68 revision=1 policy=package-priority
// LOOM v0.15.68 — First-class standalone Guest portability.
declare(strict_types=1);

const LOOM_GUEST_PORTABLE_FORMAT='loom-guest-portable/v1';

function loom_guest_portability_display_name(string $guestId): string {
  $g=loom_guest_get($guestId);
  if(!is_array($g)) return $guestId;
  $g=loom_guest_root($g);
  $primary=safe_token((string)($g['primaryClientId']??''));
  if($primary!==''){
    $p=loom_guest_profile_for_client($primary);
    if(is_array($p)){
      $pub=loom_guest_profile_public($p);
      $name=loom_clean_username((string)($pub['displayName']??''));
      if($name!==''&&!loom_visible_name_is_internal($name)) return $name;
    }
    $gp=loom_global_profile_get('client',$primary);
    if(is_array($gp)){
      $name=function_exists('loom_global_profile_display_name')
        ? loom_global_profile_display_name($gp)
        : loom_clean_username((string)($gp['displayName']??$gp['username']??''));
      if($name!==''&&!loom_visible_name_is_internal($name)) return $name;
    }
  }
  return 'Guest '.strtoupper(substr(preg_replace('/[^A-Za-z0-9]/','',$guestId),-6));
}

function loom_guest_portability_all_guests(string $clientId): array {
  loom_backup_require_owner($clientId);
  $store=loom_guest_store();
  $seen=[];$out=[];$ownerClient=loom_access_system_owner_client_id();
  foreach((array)($store['guests']??[]) as $raw){
    if(!is_array($raw)) continue;
    $root=loom_guest_root($raw);
    $gid=safe_token((string)($root['guestId']??''));
    if($gid===''||isset($seen[$gid])) continue;
    $seen[$gid]=true;
    if(($root['status']??'')==='merged') continue;
    $attached=safe_token((string)($root['attachedUserId']??''));
    $clients=array_keys((array)($root['clients']??[]));
    $projects=loom_guest_project_set($root);
    $out[]=[
      'guestId'=>$gid,
      'displayName'=>loom_guest_portability_display_name($gid),
      'status'=>(string)($root['status']??'unattached'),
      'attachedUserId'=>$attached!==''?$attached:null,
      'clientCount'=>count($clients),
      'projectCount'=>count($projects),
      'isSystemOwner'=>$ownerClient!==''&&in_array($ownerClient,$clients,true),
      'exportable'=>$attached==='',
      'coveredByPermanentUser'=>$attached!==''?$attached:null,
      'updatedAt'=>$root['updatedAt']??null,
    ];
  }
  usort($out,fn($a,$b)=>strcasecmp((string)$a['displayName'],(string)$b['displayName']));
  return $out;
}

function loom_guest_portability_graph(string $guestId): array {
  $guestId=safe_token($guestId);
  $selected=loom_guest_get($guestId);
  if(!is_array($selected)) throw new RuntimeException('Guest Identity not found.');
  $selected=loom_guest_root($selected);
  $rootId=safe_token((string)($selected['guestId']??''));
  if($rootId==='') throw new RuntimeException('Guest Identity is invalid.');
  if(safe_token((string)($selected['attachedUserId']??''))!==''){
    throw new RuntimeException('This Guest History is attached to a permanent user. Export the permanent user instead so the complete person stays together.');
  }

  $guests=[];$clients=[];$profiles=[];$installs=[];$store=loom_guest_store();
  // Preserve every historical Guest record that canonicalizes into this root.
  foreach((array)($store['guests']??[]) as $gid=>$raw){
    if(!is_array($raw)) continue;
    $root=loom_guest_root($raw);
    if(safe_token((string)($root['guestId']??''))!==$rootId) continue;
    loom_user_portability_set_add($guests,(string)($raw['guestId']??$gid));
    loom_user_portability_set_add($clients,(string)($raw['primaryClientId']??''));
    foreach(array_keys((array)($raw['clients']??[])) as $cid) loom_user_portability_set_add($clients,(string)$cid);
  }
  loom_user_portability_set_add($guests,$rootId);

  // Expand SQL Guest/client links when a database exists.
  for($round=0;$round<4;$round++){
    $changed=false;
    if(loom_db_ready()&&$guests){
      $ids=array_keys($guests);$ph=implode(',',array_fill(0,count($ids),'?'));
      foreach(loom_user_portability_db_values("SELECT client_id,guest_id FROM loom_guest_clients WHERE guest_id IN ($ph)",$ids) as $r){
        if(loom_user_portability_set_add($clients,(string)($r['client_id']??''))) $changed=true;
        if(loom_user_portability_set_add($guests,(string)($r['guest_id']??''))) $changed=true;
      }
    }
    if(loom_db_ready()&&$clients){
      $ids=array_keys($clients);$ph=implode(',',array_fill(0,count($ids),'?'));
      foreach(loom_user_portability_db_values("SELECT client_id,guest_id FROM loom_guest_clients WHERE client_id IN ($ph)",$ids) as $r){
        if(loom_user_portability_set_add($clients,(string)($r['client_id']??''))) $changed=true;
        if(loom_user_portability_set_add($guests,(string)($r['guest_id']??''))) $changed=true;
      }
    }
    if(!$changed) break;
  }

  // Guest Profile lineage and installation aliases are part of the same person.
  $gps=loom_guest_profiles_store();
  $changed=true;$round=0;
  while($changed&&$round++<5){
    $changed=false;
    foreach((array)($gps['profiles']??[]) as $pid=>$p){
      if(!is_array($p)) continue;
      $pid=safe_token((string)($p['guestProfileId']??$pid));
      $hit=loom_user_portability_has($profiles,$pid);
      foreach((array)($p['generations']??[]) as $gen){
        $cid=safe_token((string)($gen['clientId']??''));
        $gid=safe_token((string)($gen['guestId']??''));
        if(loom_user_portability_has($clients,$cid)||loom_user_portability_has($guests,$gid)){
          $hit=true;
          if(loom_user_portability_set_add($clients,$cid)) $changed=true;
          if(loom_user_portability_set_add($guests,$gid)) $changed=true;
        }
      }
      if($hit&&loom_user_portability_set_add($profiles,$pid)) $changed=true;
    }
  }
  if(loom_db_ready()&&($clients||$guests)){
    $parts=[];$params=[];
    if($clients){$ids=array_keys($clients);$parts[]='client_id IN ('.implode(',',array_fill(0,count($ids),'?')).')';$params=array_merge($params,$ids);}
    if($guests){$ids=array_keys($guests);$parts[]='guest_id IN ('.implode(',',array_fill(0,count($ids),'?')).')';$params=array_merge($params,$ids);}
    foreach(loom_user_portability_db_values('SELECT DISTINCT guest_profile_id,client_id,guest_id FROM loom_guest_profile_generations WHERE '.implode(' OR ',$parts),$params) as $r){
      loom_user_portability_set_add($profiles,(string)($r['guest_profile_id']??''));
      loom_user_portability_set_add($clients,(string)($r['client_id']??''));
      loom_user_portability_set_add($guests,(string)($r['guest_id']??''));
    }
  }
  foreach((array)($gps['installationMap']??[]) as $iid=>$pids){
    foreach((array)$pids as $pid){
      if(loom_user_portability_has($profiles,(string)$pid)){loom_user_portability_set_add($installs,(string)$iid);break;}
    }
  }
  if(loom_db_ready()&&$profiles){
    $ids=array_keys($profiles);$ph=implode(',',array_fill(0,count($ids),'?'));
    foreach(loom_user_portability_db_values("SELECT DISTINCT installation_id FROM loom_guest_profile_installations WHERE guest_profile_id IN ($ph)",$ids) as $r){
      loom_user_portability_set_add($installs,(string)($r['installation_id']??''));
    }
  }
  return [
    'userIds'=>[],
    'clientIds'=>array_keys($clients),
    'guestIds'=>array_keys($guests),
    'guestProfileIds'=>array_keys($profiles),
    'installationIds'=>array_keys($installs),
  ];
}

function loom_guest_portability_capture(string $guestId): array {
  $graph=loom_guest_portability_graph($guestId);
  $rootRaw=loom_guest_get($guestId);
  if(!is_array($rootRaw)) throw new RuntimeException('Guest Identity not found.');
  $root=loom_guest_root($rootRaw);
  $canonical=safe_token((string)($root['guestId']??$guestId));
  $local=loom_user_portability_local_capture($graph);
  $db=loom_user_portability_db_capture($graph);
  $streams=loom_user_portability_stream_capture($graph);
  $projects=loom_user_portability_project_refs($local,$db,$streams);sort($projects);
  $ownerClient=loom_access_system_owner_client_id();
  $wasOwner=$ownerClient!==''&&in_array($ownerClient,(array)($graph['clientIds']??[]),true);
  return [
    'format'=>LOOM_GUEST_PORTABLE_FORMAT,
    'schemaVersion'=>1,
    'sourceLoomVersion'=>loom_release_version(),
    'exportedAt'=>server_timestamp(),
    'guest'=>[
      'guestId'=>$canonical,
      'displayName'=>loom_guest_portability_display_name($canonical),
      'status'=>$root['status']??'unattached',
      'primaryClientId'=>$root['primaryClientId']??null,
      'createdAt'=>$root['createdAt']??null,
      'updatedAt'=>$root['updatedAt']??null,
    ],
    'displayName'=>loom_guest_portability_display_name($canonical),
    'wasSystemOwner'=>$wasOwner,
    'graph'=>$graph,
    'projectRefs'=>$projects,
    'local'=>$local,
    'database'=>$db,
    'streams'=>$streams,
    'security'=>[
      'authSessionsIncluded'=>false,
      'guestRecoverySecretsIncluded'=>false,
      'systemOwnerBindingIncluded'=>false,
      'databaseCredentialsIncluded'=>false,
    ],
  ];
}

function loom_guest_portability_zip_add_assets(ZipArchive $zip,array $capture,array &$files,array &$assetManifest): void {
  foreach(loom_user_portability_asset_descriptors((array)$capture['graph']) as $a){
    $kind=(string)($a['kind']??'');
    if(isset($a['source'])){
      $name='payload/guest/assets/'.$kind.'/'.safe_token((string)($a['ownerType']??'owner')).'/'.hash('sha256',(string)($a['ownerId']??'')).'.'.preg_replace('/[^a-z0-9]/i','',(string)($a['ext']??'bin'));
      loom_backup_add_file($zip,(string)$a['source'],$name,$files);
      $copy=$a;unset($copy['source']);$copy['path']=$name;$assetManifest[]=$copy;continue;
    }
    $dir=(string)($a['sourceDir']??'');if(!is_dir($dir)) continue;
    $base=rtrim($dir,DIRECTORY_SEPARATOR);
    $prefix='payload/guest/assets/'.$kind.'/'.safe_token((string)($a['guestId']??'guest'));
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS));
    $paths=[];
    foreach($it as $f){
      if(!$f->isFile()) continue;
      $rel=str_replace('\\','/',substr($f->getPathname(),strlen($base)+1));
      $dest=$prefix.'/'.$rel;
      loom_backup_add_file($zip,$f->getPathname(),$dest,$files);$paths[]=$dest;
    }
    $copy=$a;unset($copy['sourceDir']);$copy['paths']=$paths;$assetManifest[]=$copy;
  }
}

function loom_guest_portability_preview_payload(array $payload,string $bootstrapClientId=''): array {
  if(($payload['format']??'')!==LOOM_GUEST_PORTABLE_FORMAT) throw new RuntimeException('Unsupported Guest bundle payload.');
  $g=(array)($payload['guest']??[]);
  $source=safe_token((string)($g['guestId']??''));
  if($source==='') throw new RuntimeException('Guest bundle has no canonical Guest ID.');
  $bootstrapClientId=safe_token($bootstrapClientId);$existing=loom_guest_get($source);$mode=$existing?'exact-merge':'exact-create';$conflicts=[];
  $graph=(array)($payload['graph']??[]);
  $sourceGuestSet=loom_user_portability_set((array)($graph['guestIds']??[]));
  $sourceClientSet=loom_user_portability_set((array)($graph['clientIds']??[]));

  foreach(array_keys($sourceClientSet) as $cid){
    $linked=loom_account_user_for_client($cid);
    if(is_array($linked)) $conflicts[]='Browser identity '.$cid.' is already attached to a permanent user.';
    $eg=loom_guest_for_client($cid);
    if($eg){
      $rid=safe_token((string)(loom_guest_root($eg)['guestId']??''));
      $isBootstrapBrowser=$bootstrapClientId!==''&&hash_equals($bootstrapClientId,$cid);
      if($rid!==''&&!isset($sourceGuestSet[$rid])&&!$isBootstrapBrowser) $conflicts[]='Browser identity '.$cid.' already belongs to a different Guest Identity.';
    }
  }
  $profileStore=loom_guest_profiles_store();
  foreach((array)($graph['guestProfileIds']??[]) as $pid){
    $pid=safe_token((string)$pid);
    if($pid===''||!is_array($profileStore['profiles'][$pid]??null)) continue;
    $ep=(array)$profileStore['profiles'][$pid];$same=false;
    foreach((array)($ep['generations']??[]) as $gen){
      if(isset($sourceClientSet[safe_token((string)($gen['clientId']??''))])||isset($sourceGuestSet[safe_token((string)($gen['guestId']??''))])){$same=true;break;}
    }
    if(!$same) $conflicts[]='Guest Profile '.$pid.' already exists for another identity lineage.';
  }
  foreach((array)($payload['local']['globalProfiles']??[]) as $r){
    $r=(array)$r;$name=loom_clean_username((string)($r['username']??''));$ownerId=safe_token((string)($r['ownerId']??$r['owner_id']??''));
    if($name===''||$ownerId==='') continue;
    $owner=loom_global_profile_username_owner($name);
    if($owner){$ot=(string)($owner['ownerType']??'');$oid=safe_token((string)($owner['ownerId']??''));if(!($ot==='client'&&isset($sourceClientSet[$oid])))$conflicts[]='Global profile username '.$name.' is already owned by another identity.';}
  }
  foreach((array)($payload['local']['projectIdentities']??[]) as $r){
    $r=(array)$r;$project=safe_slug((string)($r['project']??$r['project_slug']??''));$ownerId=safe_token((string)($r['ownerId']??$r['owner_id']??''));$modeName=(string)($r['usernameMode']??$r['username_mode']??'global');$name=loom_clean_username((string)($r['username']??''));
    if($project===''||$ownerId===''||$modeName!=='project'||$name===''||project_dir($project)===null) continue;
    try{$other=loom_project_identity_conflict($project,'client',$ownerId,$name);if($other)$conflicts[]='Project username '.$name.' is already in use in '.$project.'.';}catch(Throwable $e){}
  }
  $projects=[];
  foreach((array)($payload['projectRefs']??[]) as $p){$p=safe_slug((string)$p);if($p==='')continue;$projects[]=['slug'=>$p,'installed'=>project_dir($p)!==null,'dataWillRemainDormantIfMissing'=>project_dir($p)===null];}
  return [
    'sourceGuestId'=>$source,
    'targetGuestId'=>$source,
    'mode'=>$mode,
    'displayName'=>$payload['displayName']??$g['displayName']??$source,
    'wasSystemOwner'=>(bool)($payload['wasSystemOwner']??false),
    'graphCounts'=>array_map('count',array_filter($graph,'is_array')),
    'projects'=>$projects,
    'conflicts'=>array_values(array_unique($conflicts)),
    'canApply'=>empty($conflicts),
    'security'=>$payload['security']??[],
    'note'=>'The complete standalone Guest identity graph is restored with its original Guest, browser, profile, project-state and history identifiers. Recovery secrets and System Owner authority are intentionally excluded from ordinary import.',
  ];
}

function loom_guest_portability_merge_local(array $payload): array {
  $local=(array)($payload['local']??[]);$counts=['records'=>0];
  // Preserve guest/client username reservations from the local account store without importing unrelated accounts/sessions.
  $as=loom_temp_account_store();
  foreach((array)($local['accounts']['usernames']??[]) as $k=>$r){$r=(array)$r;$type=(string)($r['ownerType']??'');$id=safe_token((string)($r['ownerId']??''));if($type!=='client'||$id==='')continue;$key=loom_username_norm((string)($r['username']??$k));if($key!==''){$as['usernames'][$key]=$r;$counts['records']++;}}
  loom_write_temp_account_store($as);

  foreach((array)($local['clientProfiles']??[]) as $cid=>$r){
    $cid=safe_token((string)$cid);if($cid==='')continue;
    $file=loom_data_dir().'/users/'.hash('sha256',$cid).'.json';$existing=read_json_file($file)?:[];$merged=array_replace_recursive($existing,(array)$r);
    @file_put_contents($file,json_encode($merged,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);$counts['records']++;
  }
  $gp=loom_global_profile_store();
  foreach((array)($local['globalProfiles']??[]) as $r){
    $r=(array)$r;$type=(string)($r['ownerType']??$r['owner_type']??'');$id=safe_token((string)($r['ownerId']??$r['owner_id']??''));if($type!=='client'||$id==='')continue;
    $mode=(string)($r['avatarMode']??$r['avatar_mode']??'');$canonical=loom_global_avatar_preset_normalize($mode);if($canonical!==null)$r['avatarMode']=$canonical;elseif($mode!=='custom')$r['avatarMode']=loom_global_avatar_repair_preset('client',$id);
    $gp['profiles'][loom_global_profile_key('client',$id)]=$r;$counts['records']++;
  }
  loom_write_global_profile_store($gp);

  $pi=loom_project_identity_store();
  foreach((array)($local['projectIdentities']??[]) as $r){
    $r=(array)$r;$project=safe_slug((string)($r['project']??$r['project_slug']??''));$type=(string)($r['ownerType']??$r['owner_type']??'');$id=safe_token((string)($r['ownerId']??$r['owner_id']??''));
    if($project===''||$type!=='client'||$id==='')continue;$r['project']=$project;$r['ownerType']='client';$r['ownerId']=$id;if((string)($r['avatarMode']??$r['avatar_mode']??'')==='loom-default')$r['avatarMode']='global';
    $pi['identities'][loom_project_identity_key($project,'client',$id)]=$r;$counts['records']++;
  }
  loom_write_project_identity_store($pi);

  foreach((array)($local['projectState']??[]) as $project=>$rows){
    foreach((array)$rows as $key=>$r){$parts=explode('|',(string)$key);if(count($parts)<3)continue;$id=array_pop($parts);$type=array_pop($parts);$module=implode('|',$parts);if($type!=='client')continue;loom_project_state_write((string)$project,$module,'client',(string)$id,(array)($r['state']??[]),(string)($r['updatedAt']??server_timestamp()));$counts['records']++;}
  }

  $gs=loom_guest_store();$slice=(array)($local['guestIdentity']??[]);
  foreach((array)($slice['guests']??[]) as $k=>$r){$r=(array)$r;$r['recoveryHash']=null;$r['recoveryHint']=null;$gs['guests'][$k]=$r;$counts['records']++;}
  foreach((array)($slice['clientMap']??[]) as $k=>$v)$gs['clientMap'][$k]=$v;
  foreach((array)($slice['attachments']??[]) as $k=>$r)$gs['attachments'][$k]=$r;
  foreach((array)($slice['conflicts']??[]) as $k=>$r)$gs['conflicts'][$k]=$r;
  if(isset($gs['recoveryIndex']))$gs['recoveryIndex']=array_diff_key((array)$gs['recoveryIndex'],array_flip((array)($payload['graph']['guestIds']??[])));
  loom_guest_write_store($gs);

  $gps=loom_guest_profiles_store();$slice=(array)($local['guestProfiles']??[]);
  foreach((array)($slice['profiles']??[]) as $k=>$r){$gps['profiles'][$k]=$r;$counts['records']++;}
  foreach((array)($slice['installationMap']??[]) as $k=>$v)$gps['installationMap'][$k]=array_values(array_unique(array_merge((array)($gps['installationMap'][$k]??[]),(array)$v)));
  foreach((array)($slice['clientProfileMap']??[]) as $k=>$v)$gps['clientProfileMap'][$k]=$v;
  loom_guest_profiles_write($gps);

  $m=loom_moderation_store();$slice=(array)($local['moderation']??[]);
  foreach((array)($slice['identityStates']??[]) as $k=>$r)$m['identityStates'][$k]=$r;
  foreach((array)($slice['projectStates']??[]) as $project=>$rows)foreach((array)$rows as $k=>$r)$m['projectStates'][$project][$k]=$r;
  $seen=[];foreach((array)($m['audit']??[]) as $r)$seen[hash('sha256',json_encode($r)?:'')]=true;
  foreach((array)($slice['audit']??[]) as $r){$h=hash('sha256',json_encode($r)?:'');if(!isset($seen[$h])){$m['audit'][]=$r;$seen[$h]=true;}}
  loom_write_moderation_store($m);

  $ac=loom_access_store();$slice=(array)($local['access']??[]);
  foreach((array)($slice['projectGrants']??[]) as $project=>$rows)foreach((array)$rows as $k=>$r)$ac['projectGrants'][$project][$k]=$r;
  loom_access_write($ac);
  $n=loom_network_store();foreach((array)($local['network']['identities']??[]) as $k=>$r)$n['identities'][$k]=$r;loom_write_network_store($n);
  $c=loom_continuity_read_store();foreach((array)($local['continuity']??[]) as $bucket=>$rows){if(!is_array($rows))continue;if(!isset($c[$bucket])||!is_array($c[$bucket]))$c[$bucket]=[];foreach($rows as $k=>$r)$c[$bucket][$k]=$r;}loom_cleanup_write_json(loom_continuity_file(),$c);
  $rf=loom_referral_read_store();$slice=(array)($local['referrals']??[]);foreach((array)($slice['shares']??[]) as $k=>$r)$rf['shares'][$k]=$r;foreach((array)($slice['acceptances']??[]) as $k=>$r)$rf['acceptances'][$k]=$r;loom_cleanup_write_json(loom_referral_file(),$rf);
  return $counts;
}

function loom_guest_portability_restore_assets(ZipArchive $zip,array $assets): int {
  $n=0;
  foreach($assets as $a){
    $kind=(string)($a['kind']??'');
    if(isset($a['path'])){
      $raw=$zip->getFromName((string)$a['path']);if(!is_string($raw))continue;
      $type=(string)($a['ownerType']??'');$id=safe_token((string)($a['ownerId']??''));$ext=preg_replace('/[^a-z0-9]/i','',(string)($a['ext']??'bin'));
      if($kind==='global-avatar')$dest=loom_global_avatar_base($type,$id).'.'.$ext;
      elseif($kind==='legacy-avatar')$dest=loom_avatar_legacy_base_path($type,$id).'.'.$ext;
      elseif($kind==='project-avatar')$dest=loom_avatar_base_path(safe_slug((string)($a['project']??'')),$type,$id).'.'.$ext;
      else continue;
      ensure_dir(dirname($dest));if(@file_put_contents($dest,$raw,LOCK_EX)!==false){@chmod($dest,0664);$n++;}continue;
    }
    if(in_array($kind,['guest-snapshots','guest-assets'],true)){
      $gid=safe_token((string)($a['guestId']??''));if($gid==='')continue;$destRoot=loom_identity_dir().'/'.$kind.'/'.$gid;
      foreach((array)($a['paths']??[]) as $path){$prefix='payload/guest/assets/'.$kind.'/'.$gid.'/';if(!str_starts_with((string)$path,$prefix))continue;$rel=substr((string)$path,strlen($prefix));if($rel===''||str_contains($rel,'..'))continue;$raw=$zip->getFromName((string)$path);if(!is_string($raw))continue;$dest=$destRoot.'/'.$rel;ensure_dir(dirname($dest));if(@file_put_contents($dest,$raw,LOCK_EX)!==false){@chmod($dest,0664);$n++;}}
    }
  }
  return $n;
}

function loom_guest_portability_import(ZipArchive $zip,array $payload,array $assetManifest,string $strategy='merge',string $bootstrapClientId=''): array {
  if(($payload['format']??'')!==LOOM_GUEST_PORTABLE_FORMAT) throw new RuntimeException('Unsupported Guest bundle.');
  $preview=loom_guest_portability_preview_payload($payload,$bootstrapClientId);
  if(!$preview['canApply']) throw new RuntimeException('Guest import conflict: '.implode(' ',$preview['conflicts']));
  if(!in_array($strategy,['merge','create-new'],true))$strategy='merge';
  if($strategy==='create-new'&&$preview['mode']!=='exact-create') throw new RuntimeException('Create-new import requires a destination without this Guest Identity.');
  $source=safe_token((string)$preview['sourceGuestId']);
  $local=loom_guest_portability_merge_local($payload);
  $streams=loom_user_portability_append_streams((array)($payload['streams']??[]));
  $assets=loom_guest_portability_restore_assets($zip,$assetManifest);
  $dbResult=null;
  if(is_array($payload['database']??null))$dbResult=loom_backup_db_apply((array)$payload['database'],$strategy==='create-new'?'create-new':'merge');
  if(loom_db_ready()){
    foreach((array)($payload['graph']['guestIds']??[]) as $gid){$g=loom_guest_get((string)$gid);if(is_array($g))try{loom_guest_db_sync($g);}catch(Throwable $e){}}
  }
  loom_audit_record('backup.guest.imported',['details'=>['sourceGuestId'=>$source,'mode'=>$preview['mode'],'projectRefs'=>$payload['projectRefs']??[],'database'=>$dbResult]],'Portable LOOM Guest restored.');
  return ['status'=>'restored','sourceGuestId'=>$source,'targetGuestId'=>$source,'mode'=>$preview['mode'],'local'=>$local,'streamRows'=>$streams,'assetFiles'=>$assets,'database'=>$dbResult,'systemOwnerTransferred'=>false];
}
