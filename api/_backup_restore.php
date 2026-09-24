<?php
// @loom-file release=0.15.39 revision=4 policy=package-priority
declare(strict_types=1);

const LOOM_BACKUP_FORMAT='loom-portable-bundle/v1';
const LOOM_BACKUP_MAX_UPLOAD=1073741824; // 1 GiB guard; host limits may be lower.

function loom_backup_root(): string { $d=loom_data_dir().'/admin/portable-backups'; ensure_dir($d); @file_put_contents($d.'/.htaccess',"Require all denied\nOptions -Indexes\n",LOCK_EX); return $d; }
function loom_backup_pending_db_root(): string { $d=loom_data_dir().'/admin/pending-database-imports'; ensure_dir($d); @file_put_contents($d.'/.htaccess',"Require all denied\nOptions -Indexes\n",LOCK_EX); return $d; }
function loom_backup_rollback_root(): string { $d=loom_data_dir().'/admin/restore-rollbacks'; ensure_dir($d); @file_put_contents($d.'/.htaccess',"Require all denied\nOptions -Indexes\n",LOCK_EX); return $d; }
function loom_backup_safe_id(string $v): string { return preg_replace('/[^a-zA-Z0-9_.-]/','',$v)?:''; }
function loom_backup_now_id(string $prefix='loom'): string { return $prefix.'-'.gmdate('Ymd-His').'-'.substr(bin2hex(random_bytes(5)),0,10); }

function loom_backup_is_project_scoped_type(string $type): bool { return in_array($type,['project','project-data'],true); }
function loom_backup_scope_label(string $type,?string $project=null): string {
  if($type==='full')return 'Entire LOOM installation';
  if($type==='projects')return 'All projects · structure only';
  if($type==='projects-data')return 'All projects + project data';
  $slug=safe_slug((string)$project);if($slug==='')return 'Project';
  try{$data=loom_project_effective_data($slug);$name=trim((string)($data['name']??''));if($name!=='')return $name;}catch(Throwable $e){}
  return humanize_project_slug($slug);
}

function loom_backup_retention_days(): int {
  $days=14;
  try{$g=loom_read_global_settings();$v=$g['modules']['loom.backup.restore']['generatedBackupRetentionDays']??14;$days=(int)$v;}catch(Throwable $e){}
  return max(1,min(365,$days));
}
function loom_backup_cleanup_old(): void {
  $cut=time()-(loom_backup_retention_days()*86400);
  foreach(glob(loom_backup_root().'/*',GLOB_ONLYDIR)?:[] as $dir){$mtime=(int)(@filemtime($dir)?:0);$meta=read_json_file($dir.'/meta.json')?:read_json_file($dir.'/import-meta.json');$created=is_array($meta)?strtotime((string)($meta['createdAt']??'')):false;$ts=$created?:$mtime;if($ts>0&&$ts<$cut)loom_backup_remove_tree($dir);}
}
function loom_backup_zip_supported(): bool { return class_exists('ZipArchive'); }
function loom_backup_require_zip(): void { if(!loom_backup_zip_supported())throw new RuntimeException('PHP ZipArchive support is required for LOOM portable backups.'); }
function loom_backup_project_capable(string $clientId,string $project): bool { return loom_access_has_capability($clientId,'project.settings',$project)||loom_access_client_is_loom_admin($clientId)||loom_access_is_system_owner($clientId); }
function loom_backup_require_project(string $clientId,string $project): void { if(!loom_backup_project_capable($clientId,$project))throw new RuntimeException('Project Admin access is required to export or import this project.'); }
function loom_backup_require_global(string $clientId): void { if(!loom_access_client_is_loom_admin($clientId))throw new RuntimeException('LOOM Admin access is required.'); }
function loom_backup_require_owner(string $clientId): void { if(!loom_access_is_system_owner($clientId))throw new RuntimeException('System Owner access is required for full LOOM state backup/restore.'); }
function loom_backup_normalize_rel(string $rel): string { $rel=str_replace('\\','/',trim($rel));$rel=preg_replace('#/+#','/',$rel)?:'';$rel=ltrim($rel,'/');if($rel===''||str_contains($rel,'../')||$rel==='..'||str_starts_with($rel,'../'))throw new RuntimeException('Unsafe bundle path.');return $rel; }
function loom_backup_excluded_secret_rel(string $rel): bool {
  $r=strtolower(str_replace('\\','/',$rel));
  $patterns=[
    'config/database.json','admin/settings/capability-secret.json','data/admin/settings/capability-secret.json',
    'auth-sessions','auth_sessions','session-token','session_token','oauth','secrets.json','secret.json'
  ];
  foreach($patterns as $p)if(str_contains($r,$p))return true;
  return false;
}
function loom_backup_sha256(string $path): string { return hash_file('sha256',$path)?:''; }
function loom_backup_add_file(ZipArchive $zip,string $src,string $dest,array &$files): void {
  if(!is_file($src))return;$dest=loom_backup_normalize_rel($dest);if(!$zip->addFile($src,$dest))throw new RuntimeException('Could not add '.$dest.' to backup.');
  $files[$dest]=['sha256'=>loom_backup_sha256($src),'size'=>(int)filesize($src)];
}
function loom_backup_add_bytes(ZipArchive $zip,string $data,string $dest,array &$files): void {
  $dest=loom_backup_normalize_rel($dest);if(!$zip->addFromString($dest,$data))throw new RuntimeException('Could not add '.$dest.' to backup.');
  $files[$dest]=['sha256'=>hash('sha256',$data),'size'=>strlen($data)];
}
function loom_backup_add_tree(ZipArchive $zip,string $src,string $dest,array &$files,?callable $filter=null): void {
  if(!is_dir($src))return;$base=rtrim($src,DIRECTORY_SEPARATOR);$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS));
  foreach($it as $f){if(!$f->isFile())continue;$full=$f->getPathname();$rel=str_replace('\\','/',substr($full,strlen($base)+1));if($filter&&!$filter($rel,$full))continue;loom_backup_add_file($zip,$full,rtrim($dest,'/').'/'.$rel,$files);}
}
function loom_backup_copy_tree(string $src,string $dest,?callable $filter=null): void {
  if(!is_dir($src))return;ensure_dir($dest);$base=rtrim($src,DIRECTORY_SEPARATOR);$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base,FilesystemIterator::SKIP_DOTS));
  foreach($it as $f){$full=$f->getPathname();$rel=str_replace('\\','/',substr($full,strlen($base)+1));if($f->isDir())continue;if($filter&&!$filter($rel,$full))continue;$target=$dest.'/'.str_replace('/',DIRECTORY_SEPARATOR,$rel);ensure_dir(dirname($target));if(!@copy($full,$target))throw new RuntimeException('Could not restore '.$rel);}
}
function loom_backup_remove_tree(string $path): void { if(!is_dir($path)){if(is_file($path))@unlink($path);return;}$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);foreach($it as $f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());}@rmdir($path); }
function loom_backup_copy_zip_prefix(ZipArchive $zip,string $prefix,string $dest): int {
  $prefix=rtrim($prefix,'/').'/';$count=0;for($i=0;$i<$zip->numFiles;$i++){$name=(string)$zip->getNameIndex($i);if(!str_starts_with($name,$prefix)||str_ends_with($name,'/'))continue;$rel=substr($name,strlen($prefix));$rel=loom_backup_normalize_rel($rel);$target=$dest.'/'.str_replace('/',DIRECTORY_SEPARATOR,$rel);ensure_dir(dirname($target));$in=$zip->getStream($name);if(!$in)throw new RuntimeException('Could not read '.$name);$out=@fopen($target,'wb');if(!$out){fclose($in);throw new RuntimeException('Could not write '.$target);}stream_copy_to_stream($in,$out);fclose($in);fclose($out);$count++;}return $count;
}
function loom_backup_zip_has_prefix(ZipArchive $zip,string $prefix): bool {
  $prefix=rtrim($prefix,'/').'/';for($i=0;$i<$zip->numFiles;$i++){$name=(string)$zip->getNameIndex($i);if(str_starts_with($name,$prefix)&&!str_ends_with($name,'/'))return true;}return false;
}
function loom_backup_html_framer_filter(string $rel): bool {
  $rel=str_replace('\\','/',$rel);$parts=explode('/',$rel);foreach($parts as $part){if($part==='.tmp'||str_starts_with($part,'.capture-')||str_contains($part,'.bak.'))return false;}return true;
}
function loom_backup_html_framer_count(string $root): int {
  $j=read_json_file(rtrim($root,'/').'/frames.json');return is_array($j['frames']??null)?count($j['frames']):0;
}
function loom_backup_project_record(string $slug): array {
  $slug=safe_slug($slug);$dir=project_dir($slug);if(!$dir)throw new RuntimeException('Project not found: '.$slug);$data=loom_project_effective_data($slug);
  return ['slug'=>$slug,'name'=>(string)($data['name']??humanize_project_slug($slug)),'source'=>loom_project_source($slug)?:'unknown','runtimePath'=>'payload/projects/'.$slug.'/project','overlayPath'=>'payload/projects/'.$slug.'/overlay','overridePath'=>'payload/projects/'.$slug.'/project-overrides.json','settingsPath'=>'payload/projects/'.$slug.'/admin-settings.json','htmlFramerPath'=>'payload/projects/'.$slug.'/html-framer'];
}
function loom_backup_add_project(ZipArchive $zip,string $slug,bool $withData,array &$files,array &$record): void {
  $slug=safe_slug($slug);$record=loom_backup_project_record($slug);$dir=project_dir($slug);loom_backup_add_tree($zip,$dir,'payload/projects/'.$slug.'/project',$files);
  $storage=loom_instance_project_storage_path($slug);$overlay=$storage.'/overlay';if(is_dir($overlay))loom_backup_add_tree($zip,$overlay,'payload/projects/'.$slug.'/overlay',$files);
  $override=$storage.'/project-overrides.json';if(is_file($override))loom_backup_add_file($zip,$override,'payload/projects/'.$slug.'/project-overrides.json',$files);
  $settings=loom_admin_settings_file($slug);if(is_file($settings))loom_backup_add_file($zip,$settings,'payload/projects/'.$slug.'/admin-settings.json',$files);
  // HTML Framer packages are executable project structure, not disposable app data.
  // A plain Project export must therefore carry frames.json, extracted package files,
  // and source.zip so the module survives migration without requiring Project + Data.
  $htmlFramer=$storage.'/html-framer';$frameCount=0;
  if(is_dir($htmlFramer)){$frameCount=loom_backup_html_framer_count($htmlFramer);loom_backup_add_tree($zip,$htmlFramer,'payload/projects/'.$slug.'/html-framer',$files,'loom_backup_html_framer_filter');}
  $record['htmlFramerIncluded']=is_dir($htmlFramer);$record['htmlFramerFrameCount']=$frameCount;
  if($withData){
    if(is_dir($storage)){
      // html-framer is exported above as project structure; exclude it here to avoid
      // duplicate payloads and keep Project vs Project + Data semantics clear.
      $filter=function(string $rel): bool { $first=explode('/',$rel,2)[0]??'';return !in_array($first,['project','overlay','project-overrides.json','html-framer'],true); };
      loom_backup_add_tree($zip,$storage,'payload/projects/'.$slug.'/instance-data',$files,$filter);
    }
    if(function_exists('loom_project_state_file')){$stateFile=loom_project_state_file($slug);if(is_file($stateFile))loom_backup_add_file($zip,$stateFile,'payload/projects/'.$slug.'/project-state.json',$files);}
    $record['dataPath']='payload/projects/'.$slug.'/instance-data';$record['projectStatePath']='payload/projects/'.$slug.'/project-state.json';
  }
}
function loom_backup_rollback_filter(string $rel): bool {
  $rel=str_replace('\\','/',$rel);
  if(str_starts_with($rel,'data/admin/portable-backups/')||str_starts_with($rel,'data/admin/restore-rollbacks/')||str_starts_with($rel,'data/admin/database-backups/'))return false;
  return true;
}
function loom_backup_restore_filter(string $rel): bool {
  $rel=str_replace('\\','/',$rel);
  if(in_array($rel,['data/admin/identity.json','config/database.json','data/admin/settings/capability-secret.json'],true))return false;
  if(str_starts_with($rel,'data/admin/portable-backups/')||str_starts_with($rel,'data/admin/restore-rollbacks/')||str_starts_with($rel,'data/admin/database-backups/')||str_starts_with($rel,'data/admin/pending-database-imports/'))return false;
  return true;
}
function loom_backup_clear_restorable_instance(): void {
  $root=loom_instance_root();
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
  foreach($it as $f){
    $full=$f->getPathname();$rel=str_replace('\\','/',substr($full,strlen($root)+1));
    if($rel===''||!loom_backup_restore_filter($rel))continue;
    if($f->isFile()||$f->isLink())@unlink($full);elseif($f->isDir())@rmdir($full);
  }
}
function loom_backup_instance_filter(string $rel): bool {
  $rel=str_replace('\\','/',$rel);if(loom_backup_excluded_secret_rel($rel))return false;
  if(str_starts_with($rel,'data/admin/portable-backups/')||str_starts_with($rel,'data/admin/restore-rollbacks/')||str_starts_with($rel,'data/admin/pending-database-imports/')||str_starts_with($rel,'data/admin/database-backups/'))return false;
  if(in_array($rel,['data/accounts/store.json','data/admin/identity.json','data/identity/guest-identities.json'],true))return false;
  return true;
}
function loom_backup_db_tables(PDO $pdo): array { $out=[];foreach($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_NUM) as $r){$t=(string)($r[0]??'');if(str_starts_with($t,'loom_'))$out[]=$t;}sort($out);return $out; }
function loom_backup_db_export(bool $includeAuthHashes=false): ?array {
  $pdo=loom_db_pdo(true);if(!$pdo)return null;$tables=[];$skipped=['loom_auth_sessions','loom_guest_recovery','loom_admin_state'];
  foreach(loom_backup_db_tables($pdo) as $table){if(in_array($table,$skipped,true))continue;$cols=[];foreach($pdo->query('SHOW COLUMNS FROM `'.str_replace('`','',$table).'`')->fetchAll() as $c)$cols[]=(string)$c['Field'];$rows=$pdo->query('SELECT * FROM `'.str_replace('`','',$table).'`')->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as &$row){if($table==='loom_admin_state')$row['token_hash']=null;if($table==='loom_users'&&!$includeAuthHashes)$row['password_hash']='__LOOM_PASSWORD_RESET_REQUIRED__';if($table==='loom_guest_identities'&&is_string($row['payload_json']??null)){$gp=json_decode((string)$row['payload_json'],true);if(is_array($gp)){$gp['recoveryHash']=null;$row['payload_json']=json_encode($gp,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);}}if(array_key_exists('recovery_token',$row))$row['recovery_token']=null;if(array_key_exists('token_hash',$row)&&$table!=='loom_admin_state')$row['token_hash']=null;}unset($row);
    $tables[$table]=['columns'=>$cols,'rows'=>$rows,'rowCount'=>count($rows)];
  }
  return ['format'=>'loom-db-portable/v1','driver'=>'mysql','schemaVersion'=>loom_release_version(),'exportedAt'=>server_timestamp(),'excludedTables'=>$skipped,'authenticationHashesIncluded'=>$includeAuthHashes,'tables'=>$tables];
}
function loom_backup_manifest_base(string $type,string $clientId): array {
  return ['format'=>LOOM_BACKUP_FORMAT,'bundleVersion'=>1,'sourceLoomVersion'=>loom_release_version(),'exportType'=>$type,'createdAt'=>server_timestamp(),'createdBy'=>['clientId'=>safe_token($clientId),'userId'=>loom_access_effective_user_id($clientId)?:null],'projects'=>[],'dataClasses'=>['included'=>[],'excluded'=>[]],'database'=>['included'=>false,'portable'=>true,'credentialsIncluded'=>false],'security'=>['rawPasswordsIncluded'=>false,'databaseCredentialsIncluded'=>false,'authSessionsIncluded'=>false,'serverSecretsIncluded'=>false],'compatibility'=>['minimumLoomVersion'=>'0.15.30','importsAsInstanceProject'=>true],'files'=>[]];
}
function loom_backup_create(string $type,string $clientId,array $opts=[]): array {
  loom_backup_cleanup_old();loom_backup_require_zip();$clientId=safe_token($clientId);$requestedProject=safe_slug((string)($opts['project']??''));$project=loom_backup_is_project_scoped_type($type)?$requestedProject:'';$allProjects=false;$withData=false;$full=false;
  switch($type){case 'project':loom_backup_require_project($clientId,$project);break;case 'project-data':loom_backup_require_project($clientId,$project);$withData=true;break;case 'projects':loom_backup_require_global($clientId);$allProjects=true;break;case 'projects-data':loom_backup_require_global($clientId);$allProjects=true;$withData=true;break;case 'full':loom_backup_require_owner($clientId);$allProjects=true;$withData=true;$full=true;break;default:throw new RuntimeException('Unknown export type.');}
  $id=loom_backup_now_id($type);$dir=loom_backup_root().'/'.$id;ensure_dir($dir);$zipPath=$dir.'/'.$id.'.zip';$zip=new ZipArchive();if($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true)throw new RuntimeException('Could not create backup ZIP.');$files=[];$manifest=loom_backup_manifest_base($type,$clientId);
  try{
    $slugs=$allProjects?loom_all_project_slugs():[$project];foreach($slugs as $slug){$rec=[];loom_backup_add_project($zip,$slug,$withData,$files,$rec);$manifest['projects'][]=$rec;}
    $manifest['dataClasses']['included'][]='project-structure';$manifest['dataClasses']['included'][]='project-branding-settings-assets';$manifest['dataClasses']['included'][]='project-html-framer-packages';if($withData)$manifest['dataClasses']['included'][]='project-instance-data';
    $manifest['dataClasses']['excluded']=array_values(array_filter(['database-credentials','auth-sessions','server-secrets',!$full?'global-identities-and-settings':null]));
    if($full){
      loom_backup_add_tree($zip,loom_instance_root(),'payload/global-instance',$files,'loom_backup_instance_filter');$manifest['dataClasses']['included'][]='global-instance-state';
      $accounts=loom_temp_account_store();$accounts['sessions']=[];loom_backup_add_bytes($zip,json_encode($accounts,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)?:'{}','payload/global-instance/data/accounts/store.json',$files);
      $owner=loom_admin_identity();if(is_array($owner)){unset($owner['tokenHash']);loom_backup_add_bytes($zip,json_encode($owner,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)?:'{}','payload/global-instance/data/admin/identity.json',$files);}
      if(function_exists('loom_guest_store')){$gs=loom_guest_store();$gs['recoveryIndex']=[];foreach(($gs['guests']??[]) as &$g)if(is_array($g)){$g['recoveryHash']=null;}unset($g);loom_backup_add_bytes($zip,json_encode($gs,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)?:'{}','payload/global-instance/data/identity/guest-identities.json',$files);}
      $db=loom_backup_db_export(true);if($db){$json=json_encode($db,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);loom_backup_add_bytes($zip,$json?:'{}','payload/database/loom-data.json',$files);$manifest['database']['included']=true;$manifest['database']['tableCount']=count($db['tables']??[]);$manifest['dataClasses']['included'][]='database-application-data';}
    }
    $manifest['files']=$files;$mjson=json_encode($manifest,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);loom_backup_add_bytes($zip,$mjson?:'{}','loom-export.json',$files);$zip->close();
    $meta=['backupId'=>$id,'type'=>$type,'project'=>loom_backup_is_project_scoped_type($type)?($project?:null):null,'scopeLabel'=>loom_backup_scope_label($type,$project),'createdAt'=>$manifest['createdAt'],'size'=>(int)filesize($zipPath),'sha256'=>loom_backup_sha256($zipPath),'file'=>basename($zipPath),'manifest'=>$manifest];@file_put_contents($dir.'/meta.json',json_encode($meta,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);
    loom_audit_record('backup.export.created',['clientId'=>$clientId,'project'=>loom_backup_is_project_scoped_type($type)?($project?:null):null,'details'=>['backupId'=>$id,'type'=>$type,'scopeLabel'=>$meta['scopeLabel'],'projectCount'=>count($manifest['projects']),'databaseIncluded'=>$manifest['database']['included']]],'LOOM portable export created.');return $meta;
  }catch(Throwable $e){$zip->close();loom_backup_remove_tree($dir);throw $e;}
}
function loom_backup_list(string $clientId): array { loom_backup_cleanup_old();loom_backup_require_global($clientId);$out=[];$owner=loom_access_is_system_owner($clientId);foreach(glob(loom_backup_root().'/*/meta.json')?:[] as $f){$m=read_json_file($f);if(!is_array($m))continue;$type=(string)($m['type']??'');if($type==='full'&&!$owner)continue;$changed=false;if(!loom_backup_is_project_scoped_type($type)&&array_key_exists('project',$m)&&$m['project']!==null){$m['project']=null;$changed=true;}$scope=loom_backup_scope_label($type,loom_backup_is_project_scoped_type($type)?(string)($m['project']??''):null);if((string)($m['scopeLabel']??'')!==$scope){$m['scopeLabel']=$scope;$changed=true;}if($changed)@file_put_contents($f,json_encode($m,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);$m['downloadUrl']=web_base_path().'/api/backup.php?action=download&id='.rawurlencode((string)$m['backupId']).'&clientId='.rawurlencode($clientId);unset($m['manifest']);$out[]=$m;}usort($out,fn($a,$b)=>strcmp((string)($b['createdAt']??''),(string)($a['createdAt']??'')));return $out; }
function loom_backup_meta(string $id): ?array { $id=loom_backup_safe_id($id);if($id==='')return null;$m=read_json_file(loom_backup_root().'/'.$id.'/meta.json');return is_array($m)?$m:null; }
function loom_backup_delete(string $clientId,string $id): void { loom_backup_require_global($clientId);$id=loom_backup_safe_id($id);$meta=loom_backup_meta($id);if(is_array($meta)&&($meta['type']??'')==='full')loom_backup_require_owner($clientId);$dir=loom_backup_root().'/'.$id;if(!is_dir($dir))throw new RuntimeException('Backup not found.');loom_backup_remove_tree($dir);loom_audit_record('backup.export.deleted',['clientId'=>$clientId,'details'=>['backupId'=>$id]],'Generated backup deleted.'); }
function loom_backup_read_manifest_from_zip(string $path): array { loom_backup_require_zip();$z=new ZipArchive();if($z->open($path)!==true)throw new RuntimeException('Could not open LOOM bundle.');$raw=$z->getFromName('loom-export.json');$z->close();$m=is_string($raw)?json_decode($raw,true):null;if(!is_array($m)||($m['format']??'')!==LOOM_BACKUP_FORMAT)throw new RuntimeException('This is not a supported LOOM portable bundle.');return $m; }
function loom_backup_verify_zip(string $path,array $manifest): array {
  $z=new ZipArchive();if($z->open($path)!==true)throw new RuntimeException('Could not open LOOM bundle.');$bad=[];$checked=0;$expanded=0;$declared=is_array($manifest['files']??null)?$manifest['files']:[];$allowed=['loom-export.json'=>true];foreach($declared as $n=>$meta)$allowed[(string)$n]=true;
  for($i=0;$i<$z->numFiles;$i++){$n=(string)$z->getNameIndex($i);if($n===''||str_ends_with($n,'/'))continue;if(!isset($allowed[$n])){$bad[]='undeclared:'.$n;}}
  foreach($declared as $name=>$meta){$name=(string)$name;if($name==='loom-export.json')continue;$stat=$z->statName($name);if(!is_array($stat)){$bad[]=$name;continue;}$expectedSize=(int)($meta['size']??-1);$actualSize=(int)($stat['size']??-1);$expanded+=max(0,$actualSize);if($expectedSize>=0&&$actualSize!==$expectedSize){$bad[]=$name;continue;}if($expanded>5*1024*1024*1024){$z->close();throw new RuntimeException('Bundle expands beyond LOOM safety limit.');}$stream=$z->getStream($name);if(!$stream){$bad[]=$name;continue;}$ctx=hash_init('sha256');hash_update_stream($ctx,$stream);fclose($stream);$sha=hash_final($ctx);if(!hash_equals((string)($meta['sha256']??''),$sha))$bad[]=$name;$checked++;}
  $z->close();return ['ok'=>!$bad,'checked'=>$checked,'bad'=>$bad,'expandedBytes'=>$expanded];
}
function loom_backup_store_upload(array $file): array { if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Bundle upload failed.');$size=(int)($file['size']??0);if($size<1||$size>LOOM_BACKUP_MAX_UPLOAD)throw new RuntimeException('Bundle size is invalid or too large.');$id=loom_backup_now_id('import');$dir=loom_backup_root().'/'.$id;ensure_dir($dir);$path=$dir.'/incoming.zip';if(!@move_uploaded_file((string)$file['tmp_name'],$path)&&!@copy((string)$file['tmp_name'],$path))throw new RuntimeException('Could not stage uploaded bundle.');$manifest=loom_backup_read_manifest_from_zip($path);$verify=loom_backup_verify_zip($path,$manifest);if(!$verify['ok']){loom_backup_remove_tree($dir);throw new RuntimeException('Bundle checksum verification failed: '.implode(', ',array_slice($verify['bad'],0,5)));}$meta=['importId'=>$id,'path'=>$path,'manifest'=>$manifest,'verify'=>$verify,'createdAt'=>server_timestamp()];@file_put_contents($dir.'/import-meta.json',json_encode(['importId'=>$id,'manifest'=>$manifest,'verify'=>$verify,'createdAt'=>$meta['createdAt']],JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);return $meta; }
function loom_backup_load_import(string $id): array { $id=loom_backup_safe_id($id);$dir=loom_backup_root().'/'.$id;$meta=read_json_file($dir.'/import-meta.json');$path=$dir.'/incoming.zip';if(!is_array($meta)||!is_file($path))throw new RuntimeException('Staged import not found.');return ['importId'=>$id,'path'=>$path,'manifest'=>$meta['manifest']??[],'verify'=>$meta['verify']??[],'createdAt'=>$meta['createdAt']??null]; }
function loom_backup_preview(string $clientId,array $staged): array { $m=$staged['manifest'];$type=(string)($m['exportType']??'');if($type==='full')loom_backup_require_owner($clientId);else loom_backup_require_global($clientId);$projects=[];foreach(($m['projects']??[]) as $p){$slug=safe_slug((string)($p['slug']??''));$projects[]=['slug'=>$slug,'name'=>$p['name']??humanize_project_slug($slug),'exists'=>project_dir($slug)!==null,'currentSource'=>loom_project_source($slug),'willImportAs'=>'instance','htmlFramerIncluded'=>(bool)($p['htmlFramerIncluded']??false),'htmlFramerFrameCount'=>(int)($p['htmlFramerFrameCount']??0)];}
  return ['importId'=>$staged['importId'],'exportType'=>$type,'sourceLoomVersion'=>$m['sourceLoomVersion']??null,'createdAt'=>$m['createdAt']??null,'projects'=>$projects,'database'=>$m['database']??[],'dataClasses'=>$m['dataClasses']??[],'verification'=>$staged['verify'],'requiresSystemOwner'=>$type==='full','strategies'=>['create-new','replace','merge','skip']]; }
function loom_backup_snapshot_path(string $label): string { $id=loom_backup_now_id('rollback-'.$label);$d=loom_backup_rollback_root().'/'.$id;ensure_dir($d);return $d; }
function loom_backup_restore_project_from_zip(ZipArchive $z,array $p,string $target,string $strategy): array {
  $source=safe_slug((string)($p['slug']??''));$target=safe_slug($target?:$source);if($source===''||$target==='')throw new RuntimeException('Invalid project mapping.');$exists=project_dir($target)!==null;if($strategy==='skip'&&$exists)return ['source'=>$source,'target'=>$target,'status'=>'skipped'];if($strategy==='create-new'&&$exists)throw new RuntimeException('Project '.$target.' already exists. Choose Replace, Merge, or a new slug.');
  $storage=loom_instance_project_storage_path($target);$runtime=$storage.'/project';$rollback=null;if($exists&&$strategy==='replace'){$rollback=loom_backup_snapshot_path('project-'.$target);if(is_dir($storage))loom_backup_copy_tree($storage,$rollback.'/instance-project');else{$cur=project_dir($target);if($cur)loom_backup_copy_tree($cur,$rollback.'/release-project');}loom_backup_remove_tree($runtime);loom_backup_remove_tree($storage.'/overlay');@unlink($storage.'/project-overrides.json');}
  ensure_dir($storage);loom_backup_copy_zip_prefix($z,'payload/projects/'.$source.'/project',$runtime);loom_backup_copy_zip_prefix($z,'payload/projects/'.$source.'/overlay',$storage.'/overlay');
  $projectDefault=$runtime.'/project.default.json';if(is_file($projectDefault)&&$target!==$source){$pd=read_json_file($projectDefault);if(is_array($pd)){$pd['slug']=$target;@file_put_contents($projectDefault,json_encode($pd,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n",LOCK_EX);}}
  foreach(['project-overrides.json','admin-settings.json'] as $f){$name='payload/projects/'.$source.'/'.$f;$data=$z->getFromName($name);if(is_string($data)){if($f==='project-overrides.json')$dest=$storage.'/project-overrides.json';else $dest=loom_admin_settings_file($target);ensure_dir(dirname($dest));if($f==='admin-settings.json'&&$target!==$source){$sj=json_decode($data,true);if(is_array($sj)){$sj['project']=$target;$data=json_encode($sj,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";}}@file_put_contents($dest,$data,LOCK_EX);}}
  $htmlPrefix=(string)($p['htmlFramerPath']??('payload/projects/'.$source.'/html-framer'));
  $declaresHtmlFramer=array_key_exists('htmlFramerIncluded',$p);$hasHtmlFramer=loom_backup_zip_has_prefix($z,$htmlPrefix);
  $declaredFrameCount=(int)($p['htmlFramerFrameCount']??0);
  if($declaresHtmlFramer&&($p['htmlFramerIncluded']??false)&&$declaredFrameCount>0&&!$hasHtmlFramer)throw new RuntimeException('Portable bundle declares HTML Framer packages but their payload is missing.');
  $dataPrefix='payload/projects/'.$source.'/instance-data';$legacyDataHasHtmlFramer=loom_backup_zip_has_prefix($z,$dataPrefix.'/html-framer');
  // New-format bundles explicitly own Framer structure, including an intentional
  // empty state. Old bundles did not declare this field, so preserve destination
  // Framer files unless an older Project + Data payload actually contains them.
  if($strategy==='replace'&&($declaresHtmlFramer||$legacyDataHasHtmlFramer))loom_backup_remove_tree($storage.'/html-framer');
  if($hasHtmlFramer)loom_backup_copy_zip_prefix($z,$htmlPrefix,$storage.'/html-framer');
  if($strategy==='replace')loom_backup_remove_tree($storage.'/data');loom_backup_copy_zip_prefix($z,$dataPrefix,$storage);
  $projectState=$z->getFromName('payload/projects/'.$source.'/project-state.json');if(is_string($projectState)){ $statePath=loom_project_state_file($target);ensure_dir(dirname($statePath));@file_put_contents($statePath,$projectState,LOCK_EX); }
  return ['source'=>$source,'target'=>$target,'status'=>'imported','strategy'=>$strategy,'rollback'=>$rollback,'htmlFramerRestored'=>($hasHtmlFramer||$legacyDataHasHtmlFramer)];
}
function loom_backup_db_apply(array $payload,string $strategy): array {
  if(!loom_db_ready()){ $id=loom_backup_now_id('db-pending');$path=loom_backup_pending_db_root().'/'.$id.'.json';@file_put_contents($path,json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);return ['status'=>'staged','pendingId'=>$id,'message'=>'Database payload staged until a LOOM database is configured.']; }
  $pdo=loom_db_pdo(true);if(!$pdo)throw new RuntimeException('Database is not ready.');$tables=$payload['tables']??[];$pdo->exec('SET FOREIGN_KEY_CHECKS=0');$pdo->beginTransaction();$count=0;try{foreach($tables as $table=>$block){if(!preg_match('/^loom_[a-z0-9_]+$/',$table))continue;if($table==='loom_auth_sessions')continue;$rows=$block['rows']??[];if(!is_array($rows))continue;if($strategy==='replace')$pdo->exec('DELETE FROM `'.$table.'`');foreach($rows as $row){if(!is_array($row)||!$row)continue;if($table==='loom_users'&&($row['password_hash']??'')==='__LOOM_PASSWORD_RESET_REQUIRED__')continue;$cols=array_keys($row);$safe=array_values(array_filter($cols,fn($c)=>preg_match('/^[a-zA-Z0-9_]+$/',$c)));if(count($safe)!==count($cols))continue;$qcols='`'.implode('`,`',$safe).'`';$marks=implode(',',array_fill(0,count($safe),'?'));$updates=implode(',',array_map(fn($c)=>'`'.$c.'`=VALUES(`'.$c.'`)',$safe));$sql='INSERT INTO `'.$table.'` ('.$qcols.') VALUES ('.$marks.')';if($strategy!=='create-new')$sql.=' ON DUPLICATE KEY UPDATE '.$updates;$st=$pdo->prepare($sql);$st->execute(array_values($row));$count++;}}$pdo->commit();$pdo->exec('SET FOREIGN_KEY_CHECKS=1');return ['status'=>'applied','rows'=>$count];}catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();$pdo->exec('SET FOREIGN_KEY_CHECKS=1');throw $e;}
}
function loom_backup_apply(string $clientId,array $staged,array $opts): array {
  $m=$staged['manifest'];$type=(string)($m['exportType']??'');$strategy=(string)($opts['strategy']??'create-new');if(!in_array($strategy,['create-new','replace','merge','skip'],true))throw new RuntimeException('Invalid import strategy.');if($type==='full'){loom_backup_require_owner($clientId);if((string)($opts['confirm']??'')!=='RESTORE LOOM')throw new RuntimeException('Type RESTORE LOOM to authorize a full-state restore.');}else loom_backup_require_global($clientId);
  $z=new ZipArchive();if($z->open($staged['path'])!==true)throw new RuntimeException('Could not open staged import.');$result=['projects'=>[],'database'=>null,'globalState'=>null];try{foreach(($m['projects']??[]) as $p){$source=safe_slug((string)($p['slug']??''));$map=is_array($opts['projectMap']??null)?($opts['projectMap'][$source]??$source):$source;$projStrategy=is_array($opts['projectStrategies']??null)?($opts['projectStrategies'][$source]??$strategy):$strategy;$result['projects'][]=loom_backup_restore_project_from_zip($z,$p,safe_slug((string)$map),(string)$projStrategy);}
    if($type==='full'){
      $rollback=loom_backup_snapshot_path('global');loom_backup_copy_tree(loom_instance_root(),$rollback.'/instance','loom_backup_rollback_filter');$destinationRootAuthority=loom_admin_identity();$tmp=loom_backup_root().'/'.$staged['importId'].'/global-stage';loom_backup_remove_tree($tmp);ensure_dir($tmp);loom_backup_copy_zip_prefix($z,'payload/global-instance',$tmp);if($strategy==='replace')loom_backup_clear_restorable_instance();if(is_dir($tmp))loom_backup_copy_tree($tmp,loom_instance_root(),'loom_backup_restore_filter');if(is_array($destinationRootAuthority))loom_write_admin_identity($destinationRootAuthority);$result['globalState']=['status'=>$strategy==='replace'?'replaced':'merged','rollback'=>$rollback,'destinationSystemOwnerPreserved'=>true];
      $dbRaw=$z->getFromName('payload/database/loom-data.json');if(is_string($dbRaw)){$db=json_decode($dbRaw,true);if(is_array($db)){if(loom_db_ready())try{$result['databaseRollback']=loom_db_integrity_backup();}catch(Throwable $e){$result['databaseRollback']=['warning'=>$e->getMessage()];}$result['database']=loom_backup_db_apply($db,$strategy==='replace'?'replace':'merge');}}
    }
  }finally{$z->close();}
  loom_audit_record('backup.import.applied',['clientId'=>$clientId,'details'=>['importId'=>$staged['importId'],'exportType'=>$type,'strategy'=>$strategy,'projects'=>$result['projects'],'database'=>$result['database']]],'LOOM portable import applied.');return $result;
}
function loom_backup_pending_db_list(string $clientId): array { loom_backup_require_owner($clientId);$out=[];foreach(glob(loom_backup_pending_db_root().'/*.json')?:[] as $f){$j=read_json_file($f);$out[]=['id'=>basename($f,'.json'),'createdAt'=>gmdate('c',filemtime($f)?:time()),'tableCount'=>is_array($j['tables']??null)?count($j['tables']):0,'size'=>(int)filesize($f)];}return $out; }
function loom_backup_apply_pending_db(string $clientId,string $id,string $strategy='merge'): array { loom_backup_require_owner($clientId);$id=loom_backup_safe_id($id);$path=loom_backup_pending_db_root().'/'.$id.'.json';$j=read_json_file($path);if(!is_array($j))throw new RuntimeException('Pending database payload not found.');$r=loom_backup_db_apply($j,$strategy==='replace'?'replace':'merge');if(($r['status']??'')==='applied')@unlink($path);return $r; }
