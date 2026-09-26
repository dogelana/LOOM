<?php
// @loom-file release=0.15.62 revision=60 policy=package-priority
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

function root_dir(): string { return realpath(__DIR__ . '/..') ?: dirname(__DIR__); }


function loom_deployment_gate_status(): ?array {
  $marker=root_dir().'/.loom-deploying.json';
  if(!is_file($marker))return null;
  $raw=@file_get_contents($marker);if(!is_string($raw)||$raw==='')return null;
  $data=json_decode($raw,true);if(!is_array($data)||!$data)return null;
  $expires=(float)($data['expires_at_epoch']??0);
  if($expires>0&&$expires<=microtime(true))return null;
  return $data;
}
function loom_enforce_deployment_gate(): void {
  $gate=loom_deployment_gate_status();if(!$gate)return;
  $retry=max(1,min(10,(int)($gate['retry_after']??3)));
  http_response_code(503);
  header('Retry-After: '.$retry);
  header('X-LOOM-Deploying: 1');
  $target=trim((string)($gate['target_release']??''));
  if($target!=='')header('X-LOOM-Target-Release: '.$target);
  $script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??''));
  $isApi=str_contains($script,'/api/');
  if(!$isApi){
    header('Content-Type: text/html; charset=utf-8');
    $label=$target!==''?'LOOM '.htmlspecialchars($target,ENT_QUOTES,'UTF-8'):'LOOM';
    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta http-equiv="refresh" content="'.$retry.'"><title>LOOM is updating</title><style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#edf4fb;color:#172231;font-family:Inter,system-ui,sans-serif}.c{width:min(560px,calc(100% - 36px));padding:24px;border:1px solid #c9d9e8;border-radius:20px;background:#fff;box-shadow:0 24px 70px #17223122}.m{font-weight:950;color:#2878d7;letter-spacing:.1em;font-size:11px}.c h1{margin:8px 0 5px;font-size:24px}.c p{margin:0;color:#66778a;line-height:1.5;font-size:13px}</style></head><body><div class="c"><div class="m">'.$label.'</div><h1>LOOM is updating…</h1><p>This page is temporarily paused while a verified deployment completes. It will retry automatically.</p></div></body></html>';
    exit;
  }
  echo json_encode([
    'ok'=>false,
    'error'=>'deployment-in-progress',
    'message'=>'LOOM is updating. Requests are paused until the transactional deployment completes.',
    'deploying'=>true,
    'phase'=>(string)($gate['phase']??'applying'),
    'targetRelease'=>$target,
    'transactionId'=>(string)($gate['transaction_id']??''),
    'retryAfter'=>$retry,
    'serverTimestamp'=>gmdate('c')
  ],JSON_UNESCAPED_SLASHES);
  exit;
}
loom_enforce_deployment_gate();

/**
 * LOOM Clean Instance Protocol (0.12.13+)
 *
 * /instance is the ONLY mutable installation-owned filesystem boundary.
 * Release archives NEVER contain /instance. They contain /instance.sample only.
 *
 * There is intentionally no compatibility import from pre-Instance layouts.
 */
function loom_instance_ensure_dir(string $dir): string {
  if(!is_dir($dir) && !@mkdir($dir,0775,true) && !is_dir($dir))
    throw new RuntimeException('LOOM Instance Vault could not be created: '.$dir);
  if(!is_writable($dir))
    throw new RuntimeException('LOOM Instance Vault is not writable: '.$dir);
  return $dir;
}
function loom_instance_root(): string {
  static $ready=null;
  if(is_string($ready)&&$ready!=='')return $ready;

  $dir=loom_instance_ensure_dir(root_dir().'/instance');

  $deny=$dir.'/.htaccess';
  if(!is_file($deny)){
    $ok=@file_put_contents($deny,"Require all denied\nOptions -Indexes\n",LOCK_EX);
    if($ok===false)throw new RuntimeException('LOOM could not protect the Instance Vault.');
  }

  $marker=$dir.'/.loom-instance.json';
  if(!is_file($marker)){
    $payload=[
      'schema'=>'loom-instance/v1',
      'protocol'=>'clean-instance',
      'createdAt'=>gmdate('c')
    ];
    $ok=@file_put_contents(
      $marker,
      json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n",
      LOCK_EX
    );
    if($ok===false)throw new RuntimeException('LOOM could not initialize the Instance Vault.');
  }

  $ready=$dir;
  return $ready;
}
function loom_data_dir(): string {
  return loom_instance_ensure_dir(loom_instance_root().'/data');
}
function loom_config_dir(): string {
  return loom_instance_ensure_dir(loom_instance_root().'/config');
}
function loom_instance_project_dir(string $project): string {
  $slug=preg_replace('/[^a-z0-9_-]/','',strtolower($project))?:'';
  if($slug==='')throw new RuntimeException('Invalid project for Instance Vault.');
  return loom_instance_ensure_dir(loom_instance_root().'/projects/'.$slug);
}
function loom_project_override_file(string $project): string {
  return loom_instance_project_dir($project).'/project-overrides.json';
}
function loom_project_overlay_dir(string $project): string {
  return loom_instance_ensure_dir(loom_instance_project_dir($project).'/overlay');
}
function safe_slug(string $value): string { return preg_replace('/[^a-z0-9_-]/', '', strtolower($value)) ?: ''; }
function safe_token(string $value): string { return preg_replace('/[^a-zA-Z0-9_.-]/','',$value) ?: ''; }
function loom_brand_hex(mixed $value,string $fallback): string {
  $v=strtoupper(trim((string)$value));return preg_match('/^#[0-9A-F]{6}$/',$v)?$v:$fallback;
}
function loom_project_brand_colors(string $project): array {
  $data=loom_project_effective_data($project);
  $primary=loom_brand_hex($data['brand_primary_color']??null,'');
  if($primary==='')$primary=loom_brand_hex($data['social_color']??null,'#111111');
  $accent=loom_brand_hex($data['brand_accent_color']??null,'#168346');
  return ['primary'=>$primary?:'#111111','accent'=>$accent?:'#168346'];
}
function loom_mix_hex(string $from,string $to,float $towardTo): string {
  $a=loom_brand_hex($from,'#000000');$b=loom_brand_hex($to,'#FFFFFF');$t=max(0.0,min(1.0,$towardTo));
  $av=sscanf(substr($a,1),'%02x%02x%02x');$bv=sscanf(substr($b,1),'%02x%02x%02x');
  $r=[];for($i=0;$i<3;$i++)$r[$i]=(int)round($av[$i]+(($bv[$i]-$av[$i])*$t));
  return sprintf('#%02X%02X%02X',$r[0],$r[1],$r[2]);
}
function loom_project_brand_theme(string $project): array {
  $data=loom_project_effective_data($project);$colors=loom_project_brand_colors($project);
  $explicitPrimary=loom_brand_hex($data['brand_primary_color']??null,'');$explicitAccent=loom_brand_hex($data['brand_accent_color']??null,'');
  $provided=($explicitPrimary!==''||$explicitAccent!=='');
  $primary=$colors['primary'];$accent=$colors['accent'];
  return [
    'provided'=>$provided,'primary'=>$primary,'accent'=>$accent,
    'background'=>loom_mix_hex($accent,'#FFFFFF',.91),'highlight'=>loom_mix_hex($primary,'#FFFFFF',.86),'edge'=>loom_mix_hex($accent,'#FFFFFF',.95),
    'surface'=>'#FFFFFF','surfaceSoft'=>loom_mix_hex($accent,'#FFFFFF',.965),'surfaceTint'=>loom_mix_hex($accent,'#FFFFFF',.925),
    'border'=>loom_mix_hex($accent,'#FFFFFF',.78),'text'=>loom_mix_hex($primary,'#121815',.72),'muted'=>loom_mix_hex($primary,'#667269',.78)
  ];
}
function loom_project_wordmark_lines(string $name): array {
  $display=trim(preg_replace('/[\s_\-–—]+/u',' ',trim($name))??'');
  if($display==='')return ['line1'=>'PROJECT','line2'=>''];
  $words=preg_split('/\s+/u',$display,-1,PREG_SPLIT_NO_EMPTY)?:[];
  if(count($words)<=1)return ['line1'=>$words[0]??$display,'line2'=>''];
  $best=1;$bestScore=PHP_INT_MAX;
  for($i=1;$i<count($words);$i++){
    $a=implode(' ',array_slice($words,0,$i));$b=implode(' ',array_slice($words,$i));
    $la=function_exists('mb_strlen')?mb_strlen($a,'UTF-8'):strlen($a);$lb=function_exists('mb_strlen')?mb_strlen($b,'UTF-8'):strlen($b);
    $score=abs($la-$lb)+(max($la,$lb)>42?(max($la,$lb)-42)*3:0);
    if($score<$bestScore){$bestScore=$score;$best=$i;}
  }
  return ['line1'=>implode(' ',array_slice($words,0,$best)),'line2'=>implode(' ',array_slice($words,$best))];
}

function loom_project_brand_identity(string $project): array {
  $slug=safe_slug($project);$data=loom_project_effective_data($slug);$branding=is_array($data['branding']??null)?$data['branding']:[];
  $stored=is_array($branding['wordmark']??null)?$branding['wordmark']:[];
  $name=loom_clean_project_text($data['name']??humanize_project_slug($slug),140);if($name==='')$name=humanize_project_slug($slug);
  $auto=loom_project_wordmark_lines($name);$mode=in_array((string)($stored['mode']??'auto'),['auto','custom'],true)?(string)($stored['mode']??'auto'):'auto';
  $line1=$mode==='custom'?loom_clean_project_text($stored['line1']??'',80):$auto['line1'];
  $line2=$mode==='custom'?loom_clean_project_text($stored['line2']??'',80):$auto['line2'];
  if($mode==='custom'&&$line1===''&&$line2===''){$mode='auto';$line1=$auto['line1'];$line2=$auto['line2'];}
  $colors=loom_project_brand_colors($slug);
  $fontFamily=loom_clean_project_text($stored['font_family']??'League Spartan',60);if($fontFamily==='')$fontFamily='League Spartan';
  $fontWeight=max(100,min(950,(int)($stored['font_weight']??900)));
  $fontCss=$fontFamily==='League Spartan'?'https://fonts.googleapis.com/css2?family=League+Spartan:wght@700;800;900&display=swap':'';
  return [
    'mode'=>$mode,'name'=>$name,'line1'=>$line1,'line2'=>$line2,'auto_line1'=>$auto['line1'],'auto_line2'=>$auto['line2'],
    'primary'=>$colors['primary'],'accent'=>$colors['accent'],'font_family'=>$fontFamily,'font_weight'=>$fontWeight,'font_css'=>$fontCss
  ];
}
function loom_project_legacy_action_manifest(string $project,string $actionId): ?array {
  $slug=safe_slug($project);if($slug===''||$actionId==='')return null;
  if(!isset($GLOBALS['loom_project_legacy_manifest_cache'])||!is_array($GLOBALS['loom_project_legacy_manifest_cache']))$GLOBALS['loom_project_legacy_manifest_cache']=[];
  $cache=&$GLOBALS['loom_project_legacy_manifest_cache'];
  if(!array_key_exists($slug,$cache)){
    $map=[];$dir=project_dir($slug);$root=$dir?$dir.'/actions':'';
    if($root!==''&&is_dir($root)){
      $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root,FilesystemIterator::SKIP_DOTS));
      foreach($it as $file){
        if(!$file->isFile()||strtolower($file->getFilename())!=='manifest.json')continue;
        $m=read_json_file($file->getPathname());$id=is_array($m)?(string)($m['action']['id']??''):'';
        if($id!==''&&!array_key_exists($id,$map))$map[$id]=$m;
      }
    }
    $cache[$slug]=$map;
  }
  $hit=$cache[$slug][$actionId]??null;return is_array($hit)?$hit:null;
}
function loom_project_core_manifest_for_project(string $project,array $manifest): array {
  $id=(string)($manifest['action']['id']??'');if($id==='')return $manifest;
  // A legacy project-local copy may contribute project-specific defaults, but
  // implementation code/module version stays release-managed from core-modules.
  $legacy=loom_project_legacy_action_manifest($project,$id);
  if($legacy){
    if(is_array($legacy['config']??null)){
      $legacyConfig=$legacy['config'];
      if($id==='core.ui.background-orbs'){
        // Old Instance Projects contain a copied snapshot of the former LOOM defaults.
        // Treat values equal to that historical default set as inherited, not as user intent,
        // so future LOOM default tuning reaches existing projects. Real legacy deviations survive.
        $historicSets=[
          ['sourceMode'=>'project','orbVolume'=>70,'speed'=>100,'glowLevel'=>58,'glowColor'=>'#8FA8FF','specialOrbEnabled'=>true,'specialOrbIntervalSeconds'=>42,'specialOrbDurationSeconds'=>14,'specialOrbSize'=>48],
          ['sourceMode'=>'project','orbVolume'=>96,'speed'=>135,'glowLevel'=>74,'glowColor'=>'#168346','specialOrbEnabled'=>true,'specialOrbIntervalSeconds'=>38,'specialOrbDurationSeconds'=>15,'specialOrbSize'=>54,'secondaryGlowColor'=>'#111111']
        ];
        $isInheritedSnapshot=false;foreach($historicSets as $historic){$match=true;foreach($legacyConfig as $key=>$value){if(!array_key_exists($key,$historic)||$historic[$key]!==$value){$match=false;break;}}if($match){$isInheritedSnapshot=true;break;}}
        if(!$isInheritedSnapshot){$delta=$legacyConfig;if($delta)$manifest['config']=array_replace_recursive(is_array($manifest['config']??null)?$manifest['config']:[],$delta);}
      } else {
        $manifest['config']=array_replace_recursive(is_array($manifest['config']??null)?$manifest['config']:[],$legacyConfig);
      }
    }
    if(is_array($legacy['presentation']??null))$manifest['presentation']=array_replace_recursive(is_array($manifest['presentation']??null)?$manifest['presentation']:[],$legacy['presentation']);
  }
  $brand=loom_project_brand_identity($project);$theme=loom_project_brand_theme($project);
  if($id==='core.ui.load-logo-text'){
    $cfg=is_array($manifest['config']??null)?$manifest['config']:[];$admin=loom_module_admin_overrides($project,$id);
    // Backwards compatibility: pre-0.15.14 explicit Logo Text values mean this location was customized.
    $wordingSource=(string)($admin['wordingSource']??((array_key_exists('line1',$admin)||array_key_exists('line2',$admin))?'custom':'project'));
    $colorSource=(string)($admin['colorSource']??((array_key_exists('primaryColor',$admin)||array_key_exists('accentColor',$admin))?'custom':'project'));
    $fontSource=(string)($admin['fontSource']??(array_key_exists('fontFamily',$admin)?'custom':'project'));
    $cfg['wordingSource']=in_array($wordingSource,['project','custom'],true)?$wordingSource:'project';
    $cfg['colorSource']=in_array($colorSource,['project','custom'],true)?$colorSource:'project';
    $cfg['fontSource']=in_array($fontSource,['project','custom'],true)?$fontSource:'project';
    if($cfg['wordingSource']==='project'){$cfg['line1']=$brand['line1'];$cfg['line2']=$brand['line2'];}
    if($cfg['colorSource']==='project'){$cfg['primaryColor']=$brand['primary'];$cfg['accentColor']=$brand['accent'];}
    if($cfg['fontSource']==='project'){$cfg['fontFamily']=$brand['font_family'];$cfg['fontWeight']=$brand['font_weight'];$cfg['fontGoogleCss']=$brand['font_css'];}
    $cfg['projectWordmark']=$brand;
    $manifest['config']=$cfg;
  }
  if($theme['provided']&&$id==='loom.page.styling'){
    $cfg=is_array($manifest['config']??null)?$manifest['config']:[];
    $cfg['backgroundColor']=$theme['background'];$cfg['backgroundHighlightColor']=$theme['highlight'];$cfg['backgroundEdgeColor']=$theme['edge'];
    $cfg['textColor']=$theme['text'];$cfg['accentColor']=$theme['primary'];$cfg['mutedColor']=$theme['muted'];$cfg['surfaceColor']=$theme['surface'];$cfg['borderColor']=$theme['border'];
    $manifest['config']=$cfg;
  }
  if($theme['provided']&&$id==='core.ui.header-bar'){
    $cfg=is_array($manifest['config']??null)?$manifest['config']:[];$cfg['backgroundColor']=$theme['surfaceSoft'];$manifest['config']=$cfg;
  }
  if($id==='core.ui.footer-bar'){
    $cfg=is_array($manifest['config']??null)?$manifest['config']:[];
    $cfg['projectName']=$brand['name'];$cfg['projectWordmarkLine1']=$brand['line1'];$cfg['projectWordmarkLine2']=$brand['line2'];
    $cfg['projectWordmarkPrimary']=$brand['primary'];$cfg['projectWordmarkAccent']=$brand['accent'];
    $cfg['projectWordmarkFontFamily']=$brand['font_family'];$cfg['projectWordmarkFontWeight']=$brand['font_weight'];$cfg['projectWordmarkFontCss']=$brand['font_css'];
    if($theme['provided']){$cfg['backgroundColor']=$theme['surfaceTint'];$cfg['brandRowBackground']=$theme['surface'];$cfg['toolsRowBackground']=$theme['surfaceSoft'];}
    $manifest['config']=$cfg;
  }
  if($id==='loom.showcase'){
    $cfg=is_array($manifest['config']??null)?$manifest['config']:[];
    $cfg['projectPrimary']=$theme['primary'];$cfg['projectAccent']=$theme['accent'];$cfg['projectThemeProvided']=$theme['provided'];
    $cfg['projectFontFamily']=$brand['font_family'];$cfg['projectFontWeight']=$brand['font_weight'];$cfg['projectFontCss']=$brand['font_css'];
    $manifest['config']=$cfg;
  }
  if($id==='core.ui.background-orbs'){
    $cfg=is_array($manifest['config']??null)?$manifest['config']:[];$admin=loom_module_admin_overrides($project,$id);
    if(!array_key_exists('glowColor',$admin))$cfg['glowColor']=$brand['accent'];
    $cfg['secondaryGlowColor']=$brand['primary'];
    $manifest['config']=$cfg;
  }
  if($id==='loom.social-links'){
    $cfg=is_array($manifest['config']??null)?$manifest['config']:[];$data=loom_project_effective_data($project);
    $cfg['projectColor']=loom_brand_hex($data['social_color']??null,$brand['primary']);
    $manifest['config']=$cfg;
  }
  if($id==='core.ui.loader'){
    $cfg=is_array($manifest['config']??null)?$manifest['config']:[];
    $cfg['brandLine1']=$brand['line1'];$cfg['brandLine2']=$brand['line2'];$cfg['brandColor1']=$brand['primary'];$cfg['brandColor2']=$brand['accent'];
    $cfg['brandFontFamily']=$brand['font_family'];$cfg['brandFontWeight']=$brand['font_weight'];$cfg['brandFontCss']=$brand['font_css'];
    $manifest['config']=$cfg;
  }
  if($id==='core.ui.toast-theme'){
    $cfg=is_array($manifest['config']??null)?$manifest['config']:[];
    $cfg['projectPrimary']=$brand['primary'];$cfg['projectAccent']=$brand['accent'];
    $manifest['config']=$cfg;
  }
  return $manifest;
}
function loom_project_core_effective_config(string $project,string $actionId): ?array {
  foreach(loom_core_module_records('project') as $record){$m=$record['manifest'];if((string)($m['action']['id']??'')!==$actionId)continue;$m=loom_project_core_manifest_for_project($project,$m);return loom_module_config_with_admin_overrides($project,$m);}return null;
}
function loom_instance_projects_root_path(): string { return loom_instance_root().'/projects'; }
function loom_instance_project_storage_path(string $project): string { $slug=safe_slug($project);return $slug!==''?loom_instance_projects_root_path().'/'.$slug:''; }
function loom_instance_project_runtime_path(string $project): string { $base=loom_instance_project_storage_path($project);return $base!==''?$base.'/project':''; }
function loom_instance_project_exists(string $project): bool { $dir=loom_instance_project_runtime_path($project);return $dir!==''&&is_file($dir.'/project.default.json'); }
function loom_project_source(string $project): ?string {
  $slug=safe_slug($project);if($slug==='')return null;
  if(loom_instance_project_exists($slug))return 'instance';
  $base=realpath(root_dir().'/projects');if(!$base)return null;$target=realpath($base.'/'.$slug);
  return ($target&&str_starts_with($target,$base.DIRECTORY_SEPARATOR)&&is_file($target.'/project.default.json'))?'release':null;
}
function loom_project_is_instance_owned(string $project): bool { return loom_project_source($project)==='instance'; }
function project_dir(string $project): ?string {
  $slug=safe_slug($project); if($slug==='')return null;
  $instance=loom_instance_project_runtime_path($slug);
  if($instance!==''&&is_file($instance.'/project.default.json')){ $real=realpath($instance); if($real)return $real; }
  $base=realpath(root_dir().'/projects'); if(!$base)return null;
  $target=realpath($base.'/'.$slug); if(!$target || !str_starts_with($target,$base.DIRECTORY_SEPARATOR)||!is_file($target.'/project.default.json'))return null;
  return $target;
}
function loom_all_project_slugs(): array {
  $slugs=[];$release=root_dir().'/projects';
  foreach(glob($release.'/*',GLOB_ONLYDIR)?:[] as $dir){$slug=safe_slug(basename($dir));if($slug!==''&&$slug[0]!=='_'&&is_file($dir.'/project.default.json'))$slugs[$slug]=true;}
  $instance=loom_instance_projects_root_path();if(is_dir($instance))foreach(scandir($instance)?:[] as $name){if($name==='.'||$name==='..')continue;$slug=safe_slug($name);if($slug!==''&&is_file($instance.'/'.$slug.'/project/project.default.json'))$slugs[$slug]=true;}
  $out=array_keys($slugs);sort($out,SORT_NATURAL|SORT_FLAG_CASE);return $out;
}
function loom_project_app_url(string $project): string {
  $slug=safe_slug($project);if($slug===''||!project_dir($slug))return '#';$base=rtrim(web_base_path(),'/');
  // Public project URLs are deliberately route-first. The shared/release shell
  // remains an implementation detail injected by project.php.
  return ($base===''?'':$base).'/'.rawurlencode($slug).'/';
}
function json_out(array $payload, int $status=200): never { http_response_code($status); echo json_encode($payload, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT); exit; }
function web_base_path(): string {
  $script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??'/api/index.php'));
  // Prefer a filesystem-relative calculation so this helper works from root
  // gateways (index.php/project.php), /home/, /admin/, and /api/ equally.
  $scriptFile=str_replace('\\','/',(string)($_SERVER['SCRIPT_FILENAME']??''));
  $root=str_replace('\\','/',rtrim(root_dir(),DIRECTORY_SEPARATOR));
  if($scriptFile!==''&&$root!==''&&str_starts_with($scriptFile,$root.'/')){
    $relative=substr($scriptFile,strlen($root)+1);$segments=array_values(array_filter(explode('/',$relative),'strlen'));
    $urlSegments=array_values(array_filter(explode('/',trim($script,'/')),'strlen'));
    if(count($urlSegments)>=count($segments)){$baseSegments=array_slice($urlSegments,0,count($urlSegments)-count($segments));$base='/'.implode('/',$baseSegments);return $base==='/'?'':rtrim($base,'/');}
  }
  // Fallback for environments that do not expose SCRIPT_FILENAME.
  foreach(['/api/','/admin/','/home/','/pegboard/','/registry/','/projects/'] as $marker){$pos=strpos($script,$marker);if($pos!==false){$base=substr($script,0,$pos);return $base==='/'?'':rtrim($base,'/');}}
  $base=str_replace('\\','/',dirname($script));return $base==='/'?'':rtrim($base,'/');
}
function rel_url(string $absolute): string {
  $root=root_dir();
  $relative=str_replace('\\','/',substr($absolute,strlen($root)));
  return web_base_path().$relative;
}

function file_cache_version(string $absolute): string {
  if(!is_file($absolute)) return 'missing';
  $hash=@hash_file('sha256',$absolute);
  if(is_string($hash)&&$hash!=='') return substr($hash,0,16);
  $mtime=@filemtime($absolute);$size=@filesize($absolute);
  return substr(hash('sha256',(string)$mtime.'|'.(string)$size),0,16);
}
function append_cache_version(string $url,string $version): string {
  return $url.((str_contains($url,'?'))?'&':'?').'v='.rawurlencode($version);
}
function versioned_rel_url(string $absolute): string {
  return append_cache_version(rel_url($absolute),file_cache_version($absolute));
}
function read_json_file(string $file): ?array { $x=json_decode((string)@file_get_contents($file),true); return is_array($x)?$x:null; }
function loom_release_version(string $fallback='0.15.62'): string { $m=read_json_file(root_dir().'/.loom-deployment.json'); $v=trim((string)($m['loom_release']??'')); return $v!==''?$v:$fallback; }
function server_epoch_ms(): int { return (int)round(microtime(true)*1000); }
function server_timestamp(): string {
  $dt=DateTimeImmutable::createFromFormat('U.u',sprintf('%.6F',microtime(true)),new DateTimeZone('UTC'));
  return $dt ? $dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.v\Z') : gmdate('c');
}
function event_id(string $prefix='evt'): string { return $prefix.'_'.bin2hex(random_bytes(8)).'_'.base_convert((string)server_epoch_ms(),10,36); }
function log_dir(string $project): string { return loom_data_dir().'/logs/'.safe_slug($project); }
function presence_dir(string $project): string { return loom_data_dir().'/presence/'.safe_slug($project); }
function presence_file(string $project,string $session): string { return presence_dir($project).'/'.safe_token($session).'.json'; }
function append_project_event(string $project,array $event): array {
  $slug=safe_slug($project);
  if(function_exists('loom_project_record_visible')&&!loom_project_record_visible($slug,(string)($event['clientId']??''),(string)($event['userId']??''))){$event['project']=$slug;$event['droppedForModeration']=true;return $event;} $dir=log_dir($slug); if(!is_dir($dir))@mkdir($dir,0775,true);
  if(empty($event['id']))$event['id']=event_id();
  if(empty($event['serverTimestamp']))$event['serverTimestamp']=server_timestamp();
  if(empty($event['serverEpochMs']))$event['serverEpochMs']=server_epoch_ms();
  $event['project']=$slug;
  $file=$dir.'/'.gmdate('Y-m-d').'.jsonl';
  @file_put_contents($file,json_encode($event,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n",FILE_APPEND|LOCK_EX);
  if(function_exists('loom_db_append_event'))loom_db_append_event($event);
  return $event;
}
function write_presence(string $project,string $session,array $payload): void {
  if(function_exists('loom_project_record_visible')&&!loom_project_record_visible($project,(string)($payload['clientId']??''),(string)($payload['userId']??'')))return;
  $dir=presence_dir($project); if(!is_dir($dir))@mkdir($dir,0775,true);
  @file_put_contents(presence_file($project,$session),json_encode($payload,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
  if(function_exists('loom_db_write_presence'))loom_db_write_presence($project,$session,$payload);
}
function action_snapshot(array $payload): array {
  $out=[];
  foreach(($payload['activeActions']??[]) as $a){
    if(!is_array($a))continue; $id=safe_token((string)($a['id']??'')); if($id==='')continue;
    $out[]=['id'=>$id,'name'=>(string)($a['name']??$id),'kind'=>(string)($a['kind']??'system'),'behavior'=>(string)($a['behavior']??'stateful')];
  }
  if(!$out){foreach(($payload['activeActionIds']??[]) as $id){$id=safe_token((string)$id);if($id!=='')$out[]=['id'=>$id,'name'=>$id,'kind'=>'system','behavior'=>'stateful'];}}
  $seen=[];return array_values(array_filter($out,function($a)use(&$seen){if(isset($seen[$a['id']]))return false;$seen[$a['id']]=true;return true;}));
}
function base_identity(array $p): array {
  return [
    'runtimeId'=>$p['runtimeId']??null,'clientId'=>$p['clientId']??null,'sessionId'=>$p['sessionId']??null,
    'userId'=>$p['userId']??null,'userLabel'=>$p['userLabel']??null
  ];
}
function emit_inactive_snapshot(string $project,array $presence,string $reason,bool $inferred): void {
  $base=base_identity($presence);
  foreach(action_snapshot($presence) as $a){
    append_project_event($project,$base+[
      'type'=>'action.state','actionId'=>$a['id'],'state'=>'inactive','name'=>$a['name'],'kind'=>$a['kind'],'behavior'=>$a['behavior'],
      'reason'=>$reason,'inferred'=>$inferred,'source'=>$inferred?'presence-reaper':'lifecycle-close'
    ]);
  }
}
function mark_stale_sessions(string $project): int {
  $slug=safe_slug($project); if($slug==='')return 0; $dir=presence_dir($slug); if(!is_dir($dir))return 0;
  $now=server_epoch_ms();$count=0;
  foreach(glob($dir.'/*.json')?:[] as $file){
    $p=read_json_file($file);if(!$p||($p['status']??'')!=='active')continue;
    $expires=(int)($p['leaseExpiresEpochMs']??0); if($expires<=0||$expires>$now)continue;
    $session=safe_token((string)($p['sessionId']??''));if($session==='')continue;
    $snapshot=action_snapshot($p);$ids=array_values(array_map(fn($a)=>$a['id'],$snapshot));$base=base_identity($p);
    $staleAt=server_timestamp();
    append_project_event($slug,$base+[
      'type'=>'session.stale','reason'=>'heartbeat-missed','inferred'=>true,
      'lastHeartbeatAt'=>$p['lastHeartbeatAt']??$p['serverTimestamp']??null,
      'staleAt'=>$staleAt,'heldActionIds'=>$ids,
      'note'=>'Heartbeat freshness lapsed. Session is retained and may resume.'
    ]);
    // IMPORTANT: stale is uncertainty, not termination. Preserve the last-known held-action snapshot.
    $p['status']='stale';$p['staleAt']=$staleAt;$p['staleEpochMs']=$now;$p['staleReason']='heartbeat-missed';
    $p['leaseExpiresEpochMs']=$expires;
    write_presence($slug,$session,$p);$count++;
  }
  return $count;
}

// Backward-compatible alias for older callers. v0.8.1 no longer expires/ends sessions on lease timeout.
function reap_expired_sessions(string $project): int { return mark_stale_sessions($project); }



// ---- LOOM privilege + administrator foundation (v0.11.12) ----
function loom_admin_dir(): string { $d=loom_data_dir().'/admin';if(!is_dir($d))@mkdir($d,0775,true);return $d; }
function loom_admin_identity_file(): string { return loom_admin_dir().'/identity.json'; }
function loom_admin_settings_dir(): string { return loom_admin_dir().'/settings'; }
function loom_admin_cookie_name(): string { return 'loom_admin_token'; }

function loom_admin_identity(): ?array {
  if(function_exists('loom_db_read_admin_state')&&function_exists('loom_db_ready')&&loom_db_ready()){
    $db=loom_db_read_admin_state();if(is_array($db)&&(!empty($db['clientId'])||!empty($db['userId'])))return $db;
  }
  $p=read_json_file(loom_admin_identity_file());
  return is_array($p)&&(!empty($p['clientId'])||!empty($p['userId']))?$p:null;
}
function loom_write_admin_identity(array $state): void {
  ensure_dir(loom_admin_dir());
  @file_put_contents(loom_admin_identity_file(),json_encode($state,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
  if(function_exists('loom_db_write_admin_state')&&function_exists('loom_db_ready')&&loom_db_ready())loom_db_write_admin_state($state);
}
function loom_set_admin_cookie(string $token): void {
  $secure=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off');
  setcookie(loom_admin_cookie_name(),$token,[
    'expires'=>time()+60*60*24*365*5,
    'path'=>'/',
    'secure'=>$secure,
    'httponly'=>true,
    'samesite'=>'Strict'
  ]);
}
function loom_admin_cookie_token(): string {
  return safe_token((string)($_COOKIE[loom_admin_cookie_name()]??''));
}
function loom_admin_cookie_valid(): bool {
  $state=loom_admin_identity(); if(!$state||empty($state['tokenHash']))return false;
  $token=loom_admin_cookie_token(); if($token==='')return false;
  return password_verify($token,(string)$state['tokenHash']);
}

// Short-lived, signed navigation credential for LOOM-native Admin pages.
// This bridges LOOM's browser client identity authorization into normal page
// requests without putting clientId values into every protected URL.
function loom_admin_nav_cookie_name(): string { return 'loom_admin_nav'; }
function loom_admin_nav_secret_file(): string { return loom_admin_dir().'/navigation-signing.key'; }
function loom_admin_nav_b64e(string $raw): string { return rtrim(strtr(base64_encode($raw),'+/','-_'),'='); }
function loom_admin_nav_b64d(string $raw): string|false {
  $pad=strlen($raw)%4;if($pad)$raw.=str_repeat('=',4-$pad);
  return base64_decode(strtr($raw,'-_','+/'),true);
}
function loom_admin_nav_secret(): string {
  $file=loom_admin_nav_secret_file();$secret=is_file($file)?trim((string)@file_get_contents($file)):'';
  if(strlen($secret)>=48)return $secret;
  ensure_dir(dirname($file));$secret=bin2hex(random_bytes(32));
  if(@file_put_contents($file,$secret,LOCK_EX)===false)throw new RuntimeException('Could not initialize Admin navigation signing key.');
  @chmod($file,0600);return $secret;
}
function loom_issue_admin_navigation_cookie(string $clientId,int $ttl=900): bool {
  $clientId=safe_token($clientId);if($clientId===''||!str_starts_with($clientId,'client_'))return false;
  if(!loom_client_is_admin($clientId))return false;
  $payload=json_encode(['v'=>1,'cid'=>$clientId,'iat'=>time(),'exp'=>time()+max(120,min(3600,$ttl))],JSON_UNESCAPED_SLASHES);
  if(!is_string($payload))return false;
  $body=loom_admin_nav_b64e($payload);$sig=loom_admin_nav_b64e(hash_hmac('sha256',$body,loom_admin_nav_secret(),true));
  $secure=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off');
  setcookie(loom_admin_nav_cookie_name(),$body.'.'.$sig,[
    'expires'=>time()+max(120,min(3600,$ttl)),'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Strict'
  ]);
  $_COOKIE[loom_admin_nav_cookie_name()]=$body.'.'.$sig;return true;
}
function loom_admin_navigation_cookie_client(): string {
  $raw=(string)($_COOKIE[loom_admin_nav_cookie_name()]??'');if($raw===''||substr_count($raw,'.')!==1)return '';
  [$body,$sig]=explode('.',$raw,2);$expected=loom_admin_nav_b64e(hash_hmac('sha256',$body,loom_admin_nav_secret(),true));
  if(!hash_equals($expected,$sig))return '';
  $decoded=loom_admin_nav_b64d($body);if($decoded===false)return '';$payload=json_decode($decoded,true);if(!is_array($payload))return '';
  if((int)($payload['exp']??0)<time())return '';$clientId=safe_token((string)($payload['cid']??''));if($clientId==='')return '';
  // Re-check current authorization on every page request so revocation wins over cookie TTL.
  return loom_client_is_admin($clientId)?$clientId:'';
}
function loom_admin_navigation_cookie_valid(): bool { return loom_admin_navigation_cookie_client()!==''; }
function loom_client_is_admin(string $clientId): bool {
  $clientId=safe_token($clientId);
  $auth=function_exists('loom_auth_user')?loom_auth_user():null;
  $authId=(string)($auth['user_id']??$auth['userId']??'');
  $authPrivilege=(string)($auth['privilege']??'User');

  // Durable Admin accounts are LOOM Admins. v0.15.18 also consults the
  // delegated-access registry so capability grants remain authoritative even
  // before/without a legacy privilege mirror.
  if($authId!=='' && strcasecmp($authPrivilege,'Admin')===0)return true;
  if(function_exists('loom_access_client_is_loom_admin')&&loom_access_client_is_loom_admin($clientId))return true;

  $state=loom_admin_identity();if(!$state)return false;
  $uid=(string)($state['userId']??'');
  $stateClient=(string)($state['clientId']??'');

  // Backward-compatible linked-account path.
  if($uid!=='' && $authId!=='' && hash_equals($uid,$authId))return true;

  // Preserve the original bootstrap browser as a recovery path. The secret
  // HttpOnly Admin credential is still required. If another permanent user is
  // actively authenticated on this browser, do not let the bootstrap cookie
  // elevate that different account.
  if($stateClient!=='' && $clientId!=='' && hash_equals($stateClient,$clientId) && loom_admin_cookie_valid()){
    if($uid==='' || $authId==='' || hash_equals($uid,$authId))return true;
  }
  return false;
}
function loom_bootstrap_or_privilege(string $clientId,bool $allowBootstrap=false): array {
  $clientId=safe_token($clientId);
  if($clientId===''||!str_starts_with($clientId,'client_'))return ['privilege'=>'User','isAdmin'=>false,'bootstrapped'=>false,'bootstrapAvailable'=>loom_admin_identity()===null];
  $state=loom_admin_identity();
  if(!$state && $allowBootstrap){
    ensure_dir(loom_admin_dir());
    $lock=@fopen(loom_admin_dir().'/bootstrap.lock','c+');
    if($lock)@flock($lock,LOCK_EX);
    $state=loom_admin_identity(); // re-check while holding the bootstrap lock
    if(!$state){
      $token='adm_'.bin2hex(random_bytes(32));
      $auth=function_exists('loom_auth_user')?loom_auth_user():null;
      $authId=(string)($auth['user_id']??$auth['userId']??'');
      $state=[
        'schemaVersion'=>'1.0',
        'clientId'=>$clientId,
        'userId'=>$authId!==''?$authId:null,
        'tokenHash'=>password_hash($token,PASSWORD_DEFAULT),
        'createdAt'=>server_timestamp(),
        'createdEpochMs'=>server_epoch_ms(),
        'bootstrapMethod'=>'explicit-first-admin'
      ];
      loom_write_admin_identity($state);
      loom_set_admin_cookie($token);
      if($authId!==''&&function_exists('loom_link_admin_to_user_if_applicable'))loom_link_admin_to_user_if_applicable($clientId,$authId);
      if($lock){@flock($lock,LOCK_UN);@fclose($lock);}
      return ['privilege'=>'Admin','isAdmin'=>true,'bootstrapped'=>true,'bootstrapAvailable'=>false,'linkedUserId'=>$authId!==''?$authId:null];
    }
    if($lock){@flock($lock,LOCK_UN);@fclose($lock);}
  }
  $admin=loom_client_is_admin($clientId);
  return [
    'privilege'=>$admin?'Admin':'User',
    'isAdmin'=>$admin,
    'bootstrapped'=>false,
    'adminIdentityMatch'=>$state?hash_equals((string)$state['clientId'],$clientId):false,
    'credentialPresent'=>loom_admin_cookie_token()!=='',
    'bootstrapAvailable'=>$state===null
  ];
}
function loom_require_admin(string $clientId): void {
  if(!loom_client_is_admin(safe_token($clientId))) json_out(['ok'=>false,'error'=>'admin-access-required','privilege'=>'User'],403);
}

function loom_native_admin_page_guard(string $pageTitle='Admin',string $rootPrefix='../'): void {
  if(loom_request_is_admin())return;
  http_response_code(403);header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store, no-cache, must-revalidate');
  $prefix=rtrim($rootPrefix,'/').'/';$title=htmlspecialchars($pageTitle,ENT_QUOTES,'UTF-8');$api=htmlspecialchars($prefix.'api',ENT_QUOTES,'UTF-8');$home=htmlspecialchars($prefix.'home/',ENT_QUOTES,'UTF-8');$engine=htmlspecialchars($prefix.'engine/',ENT_QUOTES,'UTF-8');
  echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>'.$title.' · LOOM</title><style>*{box-sizing:border-box}body{margin:0;min-height:100vh;display:grid;grid-template-rows:auto 1fr auto;font-family:Inter,system-ui;background:#eef5ef;color:#18311f}.loom-admin-gate{display:grid;place-items:center;padding:30px}.loom-admin-gate-card{width:min(620px,100%);padding:30px;background:#fff;border:1px solid #d8e6da;border-radius:24px;box-shadow:0 22px 65px #153b2112}.loom-admin-gate-card h1{margin:0 0 8px}.loom-admin-gate-card p{color:#65766b;line-height:1.55}.loom-admin-gate-state{font-size:11px;font-weight:800;color:#4d6a56}</style></head><body><div id="loomShellHeader"></div><main class="loom-admin-gate"><section class="loom-admin-gate-card"><h1>Checking Administrator access…</h1><p>LOOM is verifying this browser against the same Administrator identity used by the Admin console.</p><div id="loomAdminGateState" class="loom-admin-gate-state">Authorizing…</div></section></main><div id="loomShellFooter"></div><script src="'.$engine.'deployment-guard.js?v=0.15.62"></script><script src="'.$engine.'identity.js?v=0.15.62"></script><script src="'.$engine.'identity-entry.js?v=0.15.62"></script><script src="'.$engine.'loom-brand.js?v=0.15.62"></script><script src="'.$engine.'loom-global-profile.js?v=0.15.62"></script><script src="'.$engine.'loom-toast.js?v=0.15.62"></script><script src="'.$engine.'share-referrals.js?v=0.15.62"></script><script src="'.$engine.'loom-shell.js?v=0.15.62"></script><script>(async()=>{const state=document.getElementById("loomAdminGateState");await window.LoomIdentityEntry?.ensure?.();const identity=window.LoomIdentity?.get?.("loom-admin-page-gate");await window.LoomShell?.mount?.({apiBase:"'.$api.'",identity,pageTitle:"'.$title.'",links:[{label:"LOOM Home",href:"'.$home.'"}],adminTools:false});const status=await window.LoomShell?.refreshAdminStatus?.("'.$api.'",identity,"");const retryKey="loom:admin-page-gate:"+location.pathname,lastRetry=Number(sessionStorage.getItem(retryKey)||0);if(status?.isAdmin){if(Date.now()-lastRetry>5000){sessionStorage.setItem(retryKey,String(Date.now()));state.textContent="Administrator confirmed. Opening protected page…";location.reload();return}state.textContent="Administrator was confirmed, but the protected page session could not be established. Reload once or sign in again.";return}sessionStorage.removeItem(retryKey);state.textContent="Administrator access required. Switch to an authorized LOOM Admin identity, then reload this page."})().catch(e=>{document.getElementById("loomAdminGateState").textContent=e?.message||"Administrator access required."});</script></body></html>';
  exit;
}
function loom_admin_settings_file(string $project): string {
  ensure_dir(loom_admin_settings_dir());
  return loom_admin_settings_dir().'/'.safe_slug($project).'.json';
}
function loom_read_admin_settings(string $project): array {
  $slug=safe_slug($project);
  if(!isset($GLOBALS['loom_admin_settings_request_cache'])||!is_array($GLOBALS['loom_admin_settings_request_cache']))$GLOBALS['loom_admin_settings_request_cache']=[];
  if(array_key_exists($slug,$GLOBALS['loom_admin_settings_request_cache']))return $GLOBALS['loom_admin_settings_request_cache'][$slug];
  $value=null;
  if(function_exists('loom_db_read_module_settings')&&function_exists('loom_db_ready')&&loom_db_ready()){
    $db=loom_db_read_module_settings($slug);if(is_array($db))$value=$db;
  }
  if(!is_array($value))$value=read_json_file(loom_admin_settings_file($slug))?:['schemaVersion'=>'1.0','project'=>$slug,'modules'=>[]];
  $GLOBALS['loom_admin_settings_request_cache'][$slug]=$value;return $value;
}
function loom_write_admin_settings(string $project,array $settings): void {
  ensure_dir(loom_admin_settings_dir());$slug=safe_slug($project);
  $settings['schemaVersion']='1.0';$settings['project']=$slug;$settings['updatedAt']=server_timestamp();
  @file_put_contents(loom_admin_settings_file($slug),json_encode($settings,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
  if(function_exists('loom_db_write_module_settings')&&function_exists('loom_db_ready')&&loom_db_ready())loom_db_write_module_settings($slug,$settings);
  if(!isset($GLOBALS['loom_admin_settings_request_cache'])||!is_array($GLOBALS['loom_admin_settings_request_cache']))$GLOBALS['loom_admin_settings_request_cache']=[];$GLOBALS['loom_admin_settings_request_cache'][$slug]=$settings;
}
function loom_project_module_enabled(string $project,string $actionId,bool $default=true): bool {
  $s=loom_read_admin_settings($project);$row=$s['moduleStates'][$actionId]??null;
  return is_array($row)&&array_key_exists('enabled',$row)?(bool)$row['enabled']:$default;
}
function loom_set_project_module_enabled(string $project,string $actionId,bool $enabled): void {
  $s=loom_read_admin_settings($project);if(!is_array($s['moduleStates']??null))$s['moduleStates']=[];
  $row=$s['moduleStates'][$actionId]??[];if(!is_array($row))$row=[];$row['enabled']=$enabled;$row['updatedAt']=server_timestamp();$s['moduleStates'][$actionId]=$row;loom_write_admin_settings($project,$s);
}
function loom_project_module_hide_on_mobile(string $project,string $actionId,bool $default=false): bool {
  $s=loom_read_admin_settings($project);$row=$s['moduleStates'][$actionId]??null;
  return is_array($row)&&array_key_exists('hideOnMobile',$row)?(bool)$row['hideOnMobile']:$default;
}
function loom_set_project_module_hide_on_mobile(string $project,string $actionId,bool $hide): void {
  $s=loom_read_admin_settings($project);if(!is_array($s['moduleStates']??null))$s['moduleStates']=[];
  $row=$s['moduleStates'][$actionId]??[];if(!is_array($row))$row=[];$row['hideOnMobile']=$hide;$row['updatedAt']=server_timestamp();$s['moduleStates'][$actionId]=$row;loom_write_admin_settings($project,$s);
}
function loom_project_module_position_index(string $project,string $actionId): ?int {
  $s=loom_read_admin_settings($project);$row=$s['moduleStates'][$actionId]??null;$n=is_array($row)?($row['positionIndex']??null):null;if($n===null||$n===''||!is_numeric($n))return null;$n=(int)$n;return $n>=1?$n:null;
}
function loom_set_project_module_position_index(string $project,string $actionId,?int $requested,array $knownActionIds=[]): array {
  $project=safe_slug($project);$actionId=safe_token($actionId);if($project===''||$actionId==='')throw new RuntimeException('Invalid module position request.');$s=loom_read_admin_settings($project);if(!is_array($s['moduleStates']??null))$s['moduleStates']=[];$row=$s['moduleStates'][$actionId]??[];if(!is_array($row))$row=[];
  if($requested===null||$requested<1){unset($row['positionIndex']);$resolved=null;$collision=null;}else{$used=[];foreach(($s['moduleStates']??[]) as $id=>$other){if($id===$actionId||!is_array($other))continue;$v=$other['positionIndex']??null;if(is_numeric($v)&&(int)$v>=1)$used[(int)$v]=(string)$id;}$resolved=max(1,(int)$requested);$collision=$used[$resolved]??null;while(isset($used[$resolved]))$resolved++;$row['positionIndex']=$resolved;}
  $row['updatedAt']=server_timestamp();$s['moduleStates'][$actionId]=$row;loom_write_admin_settings($project,$s);return ['requested'=>$requested,'resolved'=>$resolved,'collisionWith'=>$collision,'autoIterated'=>$requested!==null&&$resolved!==$requested];
}
function loom_apply_project_module_positioning(string $project,array $modules): array {
  $baseline=$modules;usort($baseline,fn($a,$b)=>(($a['order_effective']<=>$b['order_effective'])?:strcmp((string)($a['action']['id']??''),(string)($b['action']['id']??''))));$explicit=[];$unindexed=[];$loader=[];foreach($baseline as $m){$id=(string)($m['action']['id']??'');if(($m['module']['bootstrap']['role']??'')==='loader'){$m['position_index']=null;$m['position_resolved']=-1;$m['order_effective']=-1;$loader[]=$m;continue;}$idx=loom_project_module_position_index($project,$id);$m['position_index']=$idx;if($idx!==null)$explicit[]=$m;else $unindexed[]=$m;}
  usort($explicit,fn($a,$b)=>(($a['position_index']<=>$b['position_index'])?:strcmp((string)($a['action']['id']??''),(string)($b['action']['id']??''))));$slots=[];foreach($explicit as $m){$slot=max(1,(int)$m['position_index']);while(isset($slots[$slot]))$slot++;$m['position_resolved']=$slot;$m['order_effective']=$slot;$m['order_display']=str_pad((string)$slot,5,'0',STR_PAD_LEFT);$slots[$slot]=$m;}$slot=1;foreach($unindexed as $m){while(isset($slots[$slot]))$slot++;$m['position_resolved']=$slot;$m['order_effective']=$slot;$m['order_display']=str_pad((string)$slot,5,'0',STR_PAD_LEFT);$slots[$slot]=$m;$slot++;}ksort($slots,SORT_NUMERIC);return array_merge($loader,array_values($slots));
}
function loom_global_module_enabled(string $actionId,bool $default=true): bool {
  $s=loom_read_global_settings();$row=$s['moduleStates'][$actionId]??null;return is_array($row)&&array_key_exists('enabled',$row)?(bool)$row['enabled']:$default;
}
function loom_set_global_module_enabled(string $actionId,bool $enabled): void {
  $s=loom_read_global_settings();if(!is_array($s['moduleStates']??null))$s['moduleStates']=[];
  $row=$s['moduleStates'][$actionId]??[];if(!is_array($row))$row=[];$row['enabled']=$enabled;$row['updatedAt']=server_timestamp();$s['moduleStates'][$actionId]=$row;loom_write_global_settings($s);
}
function loom_global_settings_file(): string {
  ensure_dir(loom_admin_dir());
  return loom_admin_dir().'/global-settings.json';
}
function loom_read_global_settings(): array {
  if(isset($GLOBALS['loom_global_settings_request_cache'])&&is_array($GLOBALS['loom_global_settings_request_cache']))return $GLOBALS['loom_global_settings_request_cache'];
  $value=null;
  if(function_exists('loom_db_read_global_settings')&&function_exists('loom_db_ready')&&loom_db_ready()){
    $db=loom_db_read_global_settings();if(is_array($db))$value=$db;
  }
  if(!is_array($value))$value=read_json_file(loom_global_settings_file())?:['schemaVersion'=>'1.0','modules'=>[]];
  $GLOBALS['loom_global_settings_request_cache']=$value;return $value;
}
function loom_write_global_settings(array $settings): void {
  ensure_dir(loom_admin_dir());
  $settings['schemaVersion']='1.0';$settings['updatedAt']=server_timestamp();
  @file_put_contents(loom_global_settings_file(),json_encode($settings,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
  if(function_exists('loom_db_write_global_settings')&&function_exists('loom_db_ready')&&loom_db_ready())loom_db_write_global_settings($settings);
  $GLOBALS['loom_global_settings_request_cache']=$settings;
}
function loom_global_module_effective_config(string $actionId): array {
  $defaults=[];foreach(loom_scan_global_core_modules() as $m)if(($m['actionId']??'')===$actionId){$defaults=is_array($m['defaults']??null)?$m['defaults']:[];break;}
  $saved=loom_read_global_settings();$override=$saved['modules'][$actionId]??[];
  return is_array($override)?array_replace_recursive($defaults,$override):$defaults;
}
function loom_module_presentation_global_defaults(): array {
  $cfg=loom_global_module_effective_config('loom.module-presentation');
  return [
    'collapseEnabled'=>(bool)($cfg['collapseEnabled']??false),
    'titleBarsEnabled'=>(bool)($cfg['titleBarsEnabled']??false),
    'initialState'=>in_array((string)($cfg['initialState']??'expanded'),['expanded','collapsed'],true)?(string)$cfg['initialState']:'expanded'
  ];
}
function loom_project_presentation_overrides(string $project): array {
  $s=loom_read_admin_settings($project);$p=$s['presentation']??[];return is_array($p)?$p:[];
}
function loom_project_presentation_effective(string $project): array {
  $g=loom_module_presentation_global_defaults();$p=loom_project_presentation_overrides($project);
  $collapse=(string)($p['collapseMode']??'inherit');$title=(string)($p['titleBarMode']??'inherit');$initial=(string)($p['initialState']??'inherit');
  return [
    'collapseMode'=>in_array($collapse,['inherit','enabled','disabled'],true)?$collapse:'inherit',
    'titleBarMode'=>in_array($title,['inherit','show','hide'],true)?$title:'inherit',
    'initialState'=>in_array($initial,['inherit','expanded','collapsed'],true)?$initial:'inherit',
    'collapseEnabled'=>$collapse==='enabled'?true:($collapse==='disabled'?false:$g['collapseEnabled']),
    'titleBarsEnabled'=>$title==='show'?true:($title==='hide'?false:$g['titleBarsEnabled']),
    'effectiveInitialState'=>$initial==='inherit'?$g['initialState']:$initial,
    'globalDefaults'=>$g
  ];
}
function loom_project_module_presentation_policy(string $project,string $actionId,array $presentation=[]): array {
  $projectPolicy=loom_project_presentation_effective($project);$settings=loom_read_admin_settings($project);$row=$settings['moduleStates'][$actionId]??[];if(!is_array($row))$row=[];
  $cm=(string)($row['collapseMode']??'inherit');if(!in_array($cm,['inherit','enabled','disabled'],true))$cm='inherit';
  $tm=(string)($row['titleBarMode']??'inherit');if(!in_array($tm,['inherit','show','hide'],true))$tm='inherit';
  $im=(string)($row['initialState']??'inherit');if(!in_array($im,['inherit','expanded','collapsed'],true))$im='inherit';
  $titleText=loom_clean_project_text($row['titleText']??'',100);
  $manifestAllows=($presentation['collapsible']??true)!==false;
  $collapse=$cm==='enabled'?true:($cm==='disabled'?false:($projectPolicy['collapseEnabled']&&$manifestAllows));
  $title=$tm==='show'?true:($tm==='hide'?false:($projectPolicy['titleBarsEnabled']&&($manifestAllows||$cm==='enabled')));
  if(!$title)$collapse=false; // no hidden interaction trap: hiding chrome also removes user collapse controls.
  $initial=$im==='inherit'?$projectPolicy['effectiveInitialState']:$im;
  return ['collapseMode'=>$cm,'titleBarMode'=>$tm,'initialState'=>$im,'titleText'=>$titleText,'collapseEnabled'=>$collapse,'titleBarVisible'=>$title,'initialCollapsed'=>$initial==='collapsed','manifestCollapsible'=>$manifestAllows,'project'=>$projectPolicy];
}
function loom_set_project_presentation(string $project,array $incoming): void {
  $s=loom_read_admin_settings($project);$collapse=(string)($incoming['collapseMode']??'inherit');$title=(string)($incoming['titleBarMode']??'inherit');$initial=(string)($incoming['initialState']??'inherit');
  if(!in_array($collapse,['inherit','enabled','disabled'],true)||!in_array($title,['inherit','show','hide'],true)||!in_array($initial,['inherit','expanded','collapsed'],true))throw new RuntimeException('Invalid module presentation setting');
  $s['presentation']=['collapseMode'=>$collapse,'titleBarMode'=>$title,'initialState'=>$initial];loom_write_admin_settings($project,$s);
}
function loom_set_project_module_presentation(string $project,string $actionId,array $incoming): void {
  $s=loom_read_admin_settings($project);if(!is_array($s['moduleStates']??null))$s['moduleStates']=[];$row=$s['moduleStates'][$actionId]??[];if(!is_array($row))$row=[];
  $collapse=(string)($incoming['collapseMode']??'inherit');$title=(string)($incoming['titleBarMode']??'inherit');$initial=(string)($incoming['initialState']??'inherit');$titleText=loom_clean_project_text($incoming['titleText']??'',100);
  if(!in_array($collapse,['inherit','enabled','disabled'],true)||!in_array($title,['inherit','show','hide'],true)||!in_array($initial,['inherit','expanded','collapsed'],true))throw new RuntimeException('Invalid module presentation override');
  $row['collapseMode']=$collapse;$row['titleBarMode']=$title;$row['initialState']=$initial;if($titleText!=='')$row['titleText']=$titleText;else unset($row['titleText']);$row['updatedAt']=server_timestamp();$s['moduleStates'][$actionId]=$row;loom_write_admin_settings($project,$s);
}
function loom_apply_project_module_presentation(string $project,string $actionId,array $presentation): array {
  $policy=loom_project_module_presentation_policy($project,$actionId,$presentation);$presentation['chrome']=$policy;$presentation['collapsible']=$policy['collapseEnabled'];return $presentation;
}
// ---- LOOM Domain Landing / project-at-installation-root (v0.15.08) ----
function loom_domain_routing_file(): string {
  return loom_config_dir().'/domain-routing.json';
}
function loom_domain_routing_defaults(): array {
  return [
    'schemaVersion'=>'1.0',
    'homePath'=>'home',
    'landing'=>['mode'=>'loom-home','project'=>''],
    'hostBindings'=>[]
  ];
}
function loom_read_domain_routing(): array {
  $defaults=loom_domain_routing_defaults();
  $saved=read_json_file(loom_domain_routing_file());
  if(!is_array($saved))return $defaults;
  $mode=(string)($saved['landing']['mode']??'loom-home');
  if(!in_array($mode,['loom-home','project'],true))$mode='loom-home';
  $project=safe_slug((string)($saved['landing']['project']??''));
  $homePath=safe_slug((string)($saved['homePath']??'home'))?:'home';
  if($homePath!=='home')$homePath='home'; // reserved stable recovery route in v1
  return [
    'schemaVersion'=>'1.0',
    'homePath'=>$homePath,
    'landing'=>['mode'=>$mode,'project'=>$project],
    'hostBindings'=>is_array($saved['hostBindings']??null)?$saved['hostBindings']:[],
    'updatedAt'=>$saved['updatedAt']??null
  ];
}
function loom_write_domain_routing(string $mode,string $project=''): array {
  $mode=in_array($mode,['loom-home','project'],true)?$mode:'loom-home';
  $project=safe_slug($project);
  if($mode==='project'){
    if($project===''||!project_dir($project))throw new RuntimeException('Choose an active LOOM project for the base URL.');
  }else{$project='';}
  $current=loom_read_domain_routing();
  $next=[
    'schemaVersion'=>'1.0',
    'homePath'=>'home',
    'landing'=>['mode'=>$mode,'project'=>$project],
    'hostBindings'=>is_array($current['hostBindings']??null)?$current['hostBindings']:[],
    'updatedAt'=>server_timestamp()
  ];
  $file=loom_domain_routing_file();
  $json=json_encode($next,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
  if(@file_put_contents($file,$json,LOCK_EX)===false)throw new RuntimeException('LOOM could not save Domain Landing configuration.');
  return $next;
}
function loom_domain_available_projects(): array {
  $out=[];
  foreach(loom_all_project_slugs() as $slug){
    $dir=project_dir($slug);if(!$dir)continue;
    $data=loom_project_effective_data($slug);
    $out[]=[
      'slug'=>$slug,
      'name'=>(string)($data['name']??humanize_project_slug($slug)),
      'source'=>loom_project_source($slug)??'unknown'
    ];
  }
  usort($out,fn($a,$b)=>strcasecmp((string)$a['name'],(string)$b['name'])?:strcmp((string)$a['slug'],(string)$b['slug']));
  return $out;
}
function loom_domain_routing_effective(): array {
  $saved=loom_read_domain_routing();
  $moduleEnabled=loom_global_module_enabled('loom.domain-landing',true);
  if(!$moduleEnabled)return ['mode'=>'loom-home','project'=>'','reason'=>'module-disabled','saved'=>$saved];
  $mode=(string)($saved['landing']['mode']??'loom-home');
  $project=safe_slug((string)($saved['landing']['project']??''));
  if($mode==='project'){
    if($project!==''&&project_dir($project))return ['mode'=>'project','project'=>$project,'reason'=>null,'saved'=>$saved];
    return ['mode'=>'loom-home','project'=>'','reason'=>'project-unavailable','saved'=>$saved];
  }
  return ['mode'=>'loom-home','project'=>'','reason'=>null,'saved'=>$saved];
}
function loom_domain_routing_payload(): array {
  $effective=loom_domain_routing_effective();
  $saved=$effective['saved'];
  return [
    'schemaVersion'=>'1.0',
    'saved'=>[
      'mode'=>(string)($saved['landing']['mode']??'loom-home'),
      'project'=>safe_slug((string)($saved['landing']['project']??'')),
      'updatedAt'=>$saved['updatedAt']??null
    ],
    'effective'=>[
      'mode'=>$effective['mode'],
      'project'=>$effective['project'],
      'reason'=>$effective['reason']
    ],
    'homePath'=>'home',
    'availableProjects'=>loom_domain_available_projects()
  ];
}
function loom_project_is_domain_landing(string $project): bool {
  $slug=safe_slug($project);$effective=loom_domain_routing_effective();
  return $slug!==''&&$effective['mode']==='project'&&$effective['project']===$slug;
}
function loom_project_public_url(string $project): string {
  $slug=safe_slug($project);if($slug==='')return '#';
  if(loom_project_is_domain_landing($slug)){
    $base=web_base_path();return ($base===''?'/':$base.'/');
  }
  return loom_project_app_url($slug);
}
function loom_global_core_modules_dir(): string { return root_dir().'/core-modules'; }
function loom_core_module_records(?string $scope=null): array {
  if(!isset($GLOBALS['loom_core_module_records_request_cache'])||!is_array($GLOBALS['loom_core_module_records_request_cache'])){
    $root=loom_global_core_modules_dir();$all=[];
    if(is_dir($root))foreach(glob($root.'/*/manifest.json')?:[] as $file){
      $m=read_json_file($file);if(!$m)continue;$moduleScope=(string)($m['module']['scope']??'global');$id=(string)($m['action']['id']??'');if($id==='')continue;
      $all[]=['manifest'=>$m,'manifestFile'=>$file,'folder'=>dirname($file),'scope'=>$moduleScope,'manifestEnabled'=>(bool)($m['enabled']??true)];
    }
    usort($all,fn($a,$b)=>strcmp((string)($a['manifest']['module']['order']??'50000'),(string)($b['manifest']['module']['order']??'50000'))?:strcmp((string)($a['manifest']['action']['id']??''),(string)($b['manifest']['action']['id']??'')));
    $GLOBALS['loom_core_module_records_request_cache']=$all;
  }
  $all=$GLOBALS['loom_core_module_records_request_cache'];if($scope===null)return $all;
  return array_values(array_filter($all,fn($row)=>(string)($row['scope']??'global')===$scope));
}
function loom_scan_global_core_modules(): array {
  $out=[];
  foreach(loom_core_module_records('global') as $record){$m=$record['manifest'];$id=(string)$m['action']['id'];$out[]=['actionId'=>$id,'name'=>(string)($m['action']['name']??$id),'description'=>(string)($m['action']['description']??''),'order'=>(string)($m['module']['order']??'50000'),'admin_settings'=>is_array($m['admin_settings']??null)?$m['admin_settings']:['fields'=>[]],'defaults'=>is_array($m['config']??null)?$m['config']:[],'manifestEnabled'=>(bool)($m['enabled']??true)];}
  return $out;
}
function loom_scan_project_core_modules(?string $project=null): array {
  $out=[];
  foreach(loom_core_module_records('project') as $record){$m=$record['manifest'];if($project!==null&&safe_slug($project)!=='')$m=loom_project_core_manifest_for_project(safe_slug($project),$m);$id=(string)$m['action']['id'];$out[]=['actionId'=>$id,'name'=>(string)($m['action']['name']??$id),'description'=>(string)($m['action']['description']??''),'order'=>(string)($m['module']['order']??'50000'),'admin_settings'=>is_array($m['admin_settings']??null)?$m['admin_settings']:['fields'=>[]],'presentation'=>is_array($m['presentation']??null)?$m['presentation']:[],'defaults'=>is_array($m['config']??null)?$m['config']:[],'source'=>'core-project','version'=>(string)($m['module']['version']??'core'),'manifestEnabled'=>(bool)($m['enabled']??true),'manifestHideOnMobile'=>(bool)($m['presentation']['responsive']['hideOnMobile']??false)];}
  return $out;
}
function loom_global_settings_payload(): array {
  $saved=loom_read_global_settings();$mods=loom_scan_global_core_modules();$effective=[];$states=[];
  foreach($mods as &$m){$id=$m['actionId'];$override=$saved['modules'][$id]??[];$m['overrides']=is_array($override)?$override:[];$m['values']=array_replace_recursive($m['defaults'],$m['overrides']);$m['enabled']=loom_global_module_enabled($id,(bool)($m['manifestEnabled']??true));$states[$id]=['enabled'=>$m['enabled']];$effective[$id]=$m['values'];}unset($m);
  return ['modules'=>$mods,'settings'=>$effective,'moduleStates'=>$states,'domainRouting'=>loom_domain_routing_payload(),'updatedAt'=>$saved['updatedAt']??null];
}

function loom_module_admin_overrides(string $project,string $actionId): array {
  $settings=loom_read_admin_settings($project);$override=$settings['modules'][$actionId]??[];
  return is_array($override)?$override:[];
}
function loom_module_config_with_admin_overrides(string $project,array $manifest): array {
  $base=is_array($manifest['config']??null)?$manifest['config']:[];
  $aid=(string)($manifest['action']['id']??'');
  if($aid==='')return $base;
  $settings=loom_read_admin_settings($project);
  $override=$settings['modules'][$aid]??[];
  return is_array($override)?array_replace_recursive($base,$override):$base;
}

// ---- LOOM project identity / profile management (v0.12.02) ----
function loom_clean_project_text(mixed $value,int $max=240): string {
  $s=trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',(string)$value)??'');
  return function_exists('mb_substr')?mb_substr($s,0,$max,'UTF-8'):substr($s,0,$max);
}
function loom_project_base_file(string $project): ?string {
  $slug=safe_slug($project);$dir=project_dir($slug);if(!$dir)return null;
  $file=$dir.'/project.default.json';
  return is_file($file)?$file:null;
}
function loom_project_base_data(string $project): array {
  $file=loom_project_base_file($project);return $file?(read_json_file($file)?:[]):[];
}
function loom_project_override_data(string $project): array {
  $x=read_json_file(loom_project_override_file($project));return is_array($x)?$x:[];
}
function loom_project_effective_cache_forget(string $project): void {
  $slug=safe_slug($project);if($slug==='')return;
  if(isset($GLOBALS['loom_project_effective_cache'])&&is_array($GLOBALS['loom_project_effective_cache']))unset($GLOBALS['loom_project_effective_cache'][$slug]);
}
function loom_project_effective_data(string $project): array {
  $slug=safe_slug($project);if($slug==='')return [];
  if(!isset($GLOBALS['loom_project_effective_cache'])||!is_array($GLOBALS['loom_project_effective_cache']))$GLOBALS['loom_project_effective_cache']=[];
  $cache=&$GLOBALS['loom_project_effective_cache'];$baseFile=loom_project_base_file($slug);$overrideFile=loom_project_override_file($slug);
  clearstatcache(true,$baseFile?:'');clearstatcache(true,$overrideFile);
  $sig=($baseFile&&is_file($baseFile)?((string)@filemtime($baseFile).':'.(string)@filesize($baseFile)):'0').':'.(is_file($overrideFile)?((string)@filemtime($overrideFile).':'.(string)@filesize($overrideFile)):'0');
  if(isset($cache[$slug])&&($cache[$slug]['sig']??null)===$sig)return $cache[$slug]['data'];
  $base=$baseFile?(read_json_file($baseFile)?:[]):[];$override=is_file($overrideFile)?(read_json_file($overrideFile)?:[]):[];
  $data=array_replace_recursive($base,$override);$cache[$slug]=['sig'=>$sig,'data'=>$data];return $data;
}
function loom_project_fallback_bio_from_data(array $data,string $slug=''): string {
  $name=loom_clean_project_text($data['name']??humanize_project_slug($slug),140);if($name==='')$name=humanize_project_slug($slug)?:'This project';
  return $name.' is powered by LOOM.';
}
function loom_project_effective_bio(string $project): string {
  $slug=safe_slug($project);$data=loom_project_effective_data($slug);$raw=trim((string)($data['bio']??''));return $raw!==''?$raw:loom_project_fallback_bio_from_data($data,$slug);
}
function loom_project_overlay_asset(string $project,string $relative): ?string {
  $relative=ltrim(str_replace('\\','/',$relative),'/');
  if($relative===''||str_contains($relative,'..'))return null;
  $base=loom_project_overlay_dir($project);$candidate=$base.'/'.$relative;
  ensure_dir(dirname($candidate));
  $parent=realpath(dirname($candidate));$realBase=realpath($base);
  if(!$parent||!$realBase||!str_starts_with($parent,$realBase))return null;
  return $candidate;
}
function loom_project_asset_file(string $project,string $relative): ?string {
  $slug=safe_slug($project);$relative=ltrim(str_replace('\\','/',$relative),'/');
  if($slug===''||$relative===''||str_contains($relative,'..'))return null;
  // Persistent overlay wins. This is where Admin logo/showcase uploads live.
  $overlay=loom_project_overlay_asset($slug,$relative);
  if($overlay&&is_file($overlay))return realpath($overlay)?:$overlay;
  // Imported Instance Projects live beneath /instance, which is deliberately
  // web-denied. Resolve the runtime file on disk and let project-asset.php
  // proxy it instead of ever exposing /instance as a public URL.
  $dir=project_dir($slug);if(!$dir)return null;$realDir=realpath($dir);if(!$realDir)return null;
  $candidate=realpath($realDir.'/'.ltrim($relative,'/'));
  $prefix=rtrim($realDir,DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR;
  return ($candidate&&is_file($candidate)&&str_starts_with($candidate,$prefix))?$candidate:null;
}
function loom_project_asset_url(string $project,string $relative): ?string {
  $slug=safe_slug($project);$relative=ltrim(str_replace('\\','/',$relative),'/');$file=loom_project_asset_file($slug,$relative);
  if(!$file)return null;$v=file_cache_version($file);
  // Always proxy project-owned assets. Release projects and Instance Projects
  // now share one URL contract, so migration never changes how callers load a logo.
  return web_base_path().'/api/project-asset.php?project='.rawurlencode($slug).'&path='.rawurlencode($relative).'&v='.rawurlencode($v);
}
function loom_default_project_logo_url(): ?string {
  $file=root_dir().'/assets/loom-logo.png';return is_file($file)?versioned_rel_url($file):null;
}
function loom_project_profile_payload(string $project): ?array {
  $slug=safe_slug($project);$dir=project_dir($slug);if(!$dir)return null;
  $data=loom_project_effective_data($slug);$branding=is_array($data['branding']??null)?$data['branding']:[];
  $asset=ltrim(str_replace('\\','/',(string)($branding['logo_asset']??'assets/logo.png')),'/');
  $logoUrl=$asset!==''&&!str_contains($asset,'..')?loom_project_asset_url($slug,$asset):null;$logoIsLoomDefault=false;
  if(!$logoUrl){$logoUrl=loom_default_project_logo_url();$logoIsLoomDefault=$logoUrl!==null;}
  $wordmark=loom_project_brand_identity($slug);$rawBio=trim((string)($data['bio']??''));$effectiveBio=$rawBio!==''?$rawBio:loom_project_fallback_bio_from_data($data,$slug);
  return [
    'slug'=>$slug,
    'name'=>(string)($data['name']??humanize_project_slug($slug)),
    'tagline'=>(string)($data['tagline']??''),
    'description'=>(string)($data['description']??''),
    'bio'=>$effectiveBio,'bio_custom'=>$rawBio,'bio_is_fallback'=>$rawBio==='',
    'theme'=>(string)($data['theme']??'default'),
    'version'=>(string)($data['version']??'0.1.0'),
    'engine'=>(string)($data['engine']??'LOOM'),
    'brand_primary_color'=>loom_project_brand_colors($slug)['primary'],
    'brand_accent_color'=>loom_project_brand_colors($slug)['accent'],
    'social_color'=>loom_brand_hex($data['social_color']??null,loom_project_brand_colors($slug)['primary']),
    'wordmark'=>$wordmark,
    'branding'=>[
      'logo_asset'=>$asset?:'assets/logo.png',
      'logo_alt'=>(string)($branding['logo_alt']??($data['name']??humanize_project_slug($slug))),
      'logo_url'=>$logoUrl,'logo_is_loom_default'=>$logoIsLoomDefault
    ]
  ];
}
function loom_write_project_profile(string $project,array $incoming): array {
  $slug=safe_slug($project);$dir=project_dir($slug);if(!$dir)throw new RuntimeException('Project not found');
  $data=loom_project_override_data($slug);
  if(array_key_exists('name',$incoming)){$name=loom_clean_project_text($incoming['name'],140);if($name==='')throw new RuntimeException('Project name is required');$data['name']=$name;}
  if(array_key_exists('tagline',$incoming))$data['tagline']=loom_clean_project_text($incoming['tagline'],140);
  if(array_key_exists('description',$incoming))$data['description']=loom_clean_project_text($incoming['description'],500);
  if(array_key_exists('bio',$incoming))$data['bio']=loom_clean_project_text($incoming['bio'],1800);
  if(array_key_exists('version',$incoming)){$version=loom_clean_project_text($incoming['version'],40);if($version!=='')$data['version']=$version;}
  $primaryProvided=array_key_exists('brand_primary_color',$incoming);
  if($primaryProvided){$c=strtoupper(trim((string)$incoming['brand_primary_color']));if($c==='')unset($data['brand_primary_color']);elseif(!preg_match('/^#[0-9A-F]{6}$/',$c))throw new RuntimeException('Project main color must be a 6-digit hex color');else $data['brand_primary_color']=$c;}
  if(array_key_exists('brand_accent_color',$incoming)){$c=strtoupper(trim((string)$incoming['brand_accent_color']));if($c==='')unset($data['brand_accent_color']);elseif(!preg_match('/^#[0-9A-F]{6}$/',$c))throw new RuntimeException('Project accent color must be a 6-digit hex color');else $data['brand_accent_color']=$c;}
  if(array_key_exists('social_color',$incoming)){$c=strtoupper(trim((string)$incoming['social_color']));if(!preg_match('/^#[0-9A-F]{6}$/',$c))throw new RuntimeException('Project social color must be a 6-digit hex color');$data['social_color']=$c;}
  elseif($primaryProvided&&isset($data['brand_primary_color']))$data['social_color']=$data['brand_primary_color'];
  if(array_key_exists('theme',$incoming)){$theme=preg_replace('/[^a-zA-Z0-9_.-]/','',loom_clean_project_text($incoming['theme'],60));$data['theme']=$theme!==''?$theme:'default';}
  $effective=loom_project_effective_data($slug);
  $branding=is_array($data['branding']??null)?$data['branding']:[];
  $baseBrand=is_array($effective['branding']??null)?$effective['branding']:[];
  $branding['logo_asset']=(string)($branding['logo_asset']??$baseBrand['logo_asset']??'assets/logo.png');
  if(array_key_exists('logo_alt',$incoming))$branding['logo_alt']=loom_clean_project_text($incoming['logo_alt'],120);
  elseif(empty($branding['logo_alt']))$branding['logo_alt']=(string)($data['name']??$effective['name']??humanize_project_slug($slug));
  if(array_key_exists('wordmark_mode',$incoming)||array_key_exists('wordmark_line1',$incoming)||array_key_exists('wordmark_line2',$incoming)||array_key_exists('wordmark_font_family',$incoming)||array_key_exists('wordmark_font_weight',$incoming)){
    $wm=is_array($branding['wordmark']??null)?$branding['wordmark']:[];
    if(array_key_exists('wordmark_mode',$incoming)){$mode=(string)$incoming['wordmark_mode'];if(!in_array($mode,['auto','custom'],true))throw new RuntimeException('Project wordmark mode must be auto or custom');$wm['mode']=$mode;}
    if(array_key_exists('wordmark_line1',$incoming))$wm['line1']=loom_clean_project_text($incoming['wordmark_line1'],80);
    if(array_key_exists('wordmark_line2',$incoming))$wm['line2']=loom_clean_project_text($incoming['wordmark_line2'],80);
    if(array_key_exists('wordmark_font_family',$incoming)){$font=loom_clean_project_text($incoming['wordmark_font_family'],60);if(!in_array($font,['League Spartan','Arial Black','Impact','system-ui','Georgia'],true))throw new RuntimeException('Unsupported project wordmark font');$wm['font_family']=$font;}
    if(array_key_exists('wordmark_font_weight',$incoming))$wm['font_weight']=max(100,min(950,(int)$incoming['wordmark_font_weight']));
    if(($wm['mode']??'auto')==='custom'&&trim((string)($wm['line1']??''))===''&&trim((string)($wm['line2']??''))==='')throw new RuntimeException('Custom project wordmark needs at least one line');
    $branding['wordmark']=$wm;
  }
  $data['branding']=$branding;$data['updated_at']=server_timestamp();
  $file=loom_project_override_file($slug);ensure_dir(dirname($file));
  $json=json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  if($json===false||@file_put_contents($file,$json."\n",LOCK_EX)===false)throw new RuntimeException('Could not save project profile overrides');
  loom_project_effective_cache_forget($slug);
  return loom_project_profile_payload($slug)?:[];
}
function loom_decode_png_payload(string $payload): string {
  if(str_contains($payload,','))$payload=substr($payload,strpos($payload,',')+1);
  $png=base64_decode($payload,true);if($png===false||strlen($png)<16||substr($png,0,8)!=="\x89PNG\r\n\x1a\n")throw new RuntimeException('Expected PNG image payload');
  if(strlen($png)>8*1024*1024)throw new RuntimeException('Project logo must be 8 MB or smaller');
  return $png;
}
function loom_save_project_logo(string $project,string $pngPayload): array {
  $slug=safe_slug($project);if(!project_dir($slug))throw new RuntimeException('Project not found');
  $png=loom_decode_png_payload($pngPayload);$target=loom_project_overlay_asset($slug,'assets/logo.png');
  if(!$target)throw new RuntimeException('Could not resolve persistent project logo path');
  ensure_dir(dirname($target));
  if(@file_put_contents($target,$png,LOCK_EX)===false)throw new RuntimeException('Could not store persistent project logo');
  $data=loom_project_override_data($slug);$effective=loom_project_effective_data($slug);
  $branding=is_array($data['branding']??null)?$data['branding']:[];
  $branding['logo_asset']='assets/logo.png';
  $branding['logo_alt']=(string)($branding['logo_alt']??($effective['name']??humanize_project_slug($slug)));
  $data['branding']=$branding;$data['updated_at']=server_timestamp();
  $file=loom_project_override_file($slug);ensure_dir(dirname($file));
  @file_put_contents($file,json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n",LOCK_EX);
  loom_project_effective_cache_forget($slug);
  return loom_project_profile_payload($slug)?:[];
}

// ---- LOOM project archive lifecycle (v0.9.1) ----
function archives_root(): string { $d=loom_instance_root().'/archives/projects';ensure_dir($d);return $d; }
function archive_project_dir(string $archiveSlug): string { return archives_root().'/'.safe_slug($archiveSlug); }
function ensure_dir(string $dir): void { if(!is_dir($dir)) @mkdir($dir,0775,true); }
function recursive_copy(string $src,string $dst): bool {
  if(!is_dir($src)) return false; ensure_dir($dst);
  $items=scandir($src); if($items===false)return false;
  foreach($items as $item){ if($item==='.'||$item==='..')continue; $s=$src.'/'.$item;$d=$dst.'/'.$item;
    if(is_dir($s)){ if(!recursive_copy($s,$d))return false; }
    else { ensure_dir(dirname($d)); if(!@copy($s,$d))return false; }
  }
  return true;
}
function recursive_remove(string $path): void {
  if(!file_exists($path))return;
  if(is_file($path)||is_link($path)){@unlink($path);return;}
  foreach(scandir($path)?:[] as $item){if($item==='.'||$item==='..')continue;recursive_remove($path.'/'.$item);} @rmdir($path);
}
function move_tree(string $src,string $dst): bool {
  if(!file_exists($src))return false; ensure_dir(dirname($dst));
  if(@rename($src,$dst))return true;
  if(is_dir($src) && recursive_copy($src,$dst)){recursive_remove($src);return true;}
  return false;
}
function project_exists_anywhere(string $slug, ?string $ignoreArchive=null): bool {
  $slug=safe_slug($slug); if($slug==='')return true;
  if(is_dir(root_dir().'/projects/'.$slug)||loom_instance_project_exists($slug))return true;
  $a=archive_project_dir($slug); if(is_dir($a) && $slug!==safe_slug((string)$ignoreArchive))return true;
  return false;
}
function loom_project_slug_conflicts_with_public_route(string $slug): bool {
  $slug=safe_slug($slug);if($slug===''||str_starts_with($slug,'_'))return true;
  $root=rtrim(root_dir(),DIRECTORY_SEPARATOR);
  // Real LOOM routes/files always own their public path. This automatically
  // covers /home, /admin, /api, /registry, /pegboard and future root tools.
  foreach([$root.'/'.$slug,$root.'/'.$slug.'.php',$root.'/'.$slug.'.html'] as $candidate){if(file_exists($candidate))return true;}
  return in_array($slug,['projects','project','instance','assets','engine','templates','docs','database'],true);
}
function loom_project_unique_public_slug(string $requested): string {
  $base=trim(safe_slug($requested),'-_');if($base==='')$base='project';
  if(!project_exists_anywhere($base)&&!loom_project_slug_conflicts_with_public_route($base))return $base;
  for($n=2;$n<10000;$n++){
    $candidate=$base.'-'.$n;
    if(!project_exists_anywhere($candidate)&&!loom_project_slug_conflicts_with_public_route($candidate))return $candidate;
  }
  return $base.'-'.substr(hash('sha256',microtime(true).random_bytes(8)),0,8);
}
function project_base_slug(string $slug): string {
  $slug=safe_slug($slug); $base=preg_replace('/-old-\d+$/','',$slug); return $base!==''?$base:$slug;
}
function next_old_slug(string $slug, ?string $ignoreArchive=null): string {
  $base=project_base_slug($slug); $max=0;
  foreach([root_dir().'/projects',loom_instance_projects_root_path(),archives_root()] as $dir){
    if(!is_dir($dir))continue;
    foreach(scandir($dir)?:[] as $name){
      if($name==='.'||$name==='..'||$name===safe_slug((string)$ignoreArchive))continue;
      if(preg_match('/^'.preg_quote($base,'/').'-old-(\d+)$/',$name,$m))$max=max($max,(int)$m[1]);
    }
  }
  return $base.'-old-'.($max+1);
}
function humanize_project_slug(string $slug): string {
  $s=str_replace(['-','_'],' ',safe_slug($slug)); return ucwords($s);
}
function write_archive_meta(string $archiveSlug,array $meta): void {
  $dir=archive_project_dir($archiveSlug); ensure_dir($dir);
  @file_put_contents($dir.'/archive.json',json_encode($meta,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT),LOCK_EX);
}
function archive_active_project(string $slug,string $reason='user-archive'): array {
  $slug=safe_slug($slug);if($slug===''||!project_dir($slug))return ['ok'=>false,'error'=>'active-project-not-found'];
  $projectData=loom_project_effective_data($slug);$source=loom_project_source($slug);$archiveSlug=next_old_slug($slug);$archiveDir=archive_project_dir($archiveSlug);ensure_dir($archiveDir);
  if($source==='instance'){
    $src=loom_instance_project_storage_path($slug);$dst=$archiveDir.'/instance-project';
    if(file_exists($dst)||!move_tree($src,$dst))return ['ok'=>false,'error'=>'archive-move-failed'];
  }else{
    $src=root_dir().'/projects/'.$slug;$dst=$archiveDir.'/project';
    if(file_exists($dst)||!move_tree($src,$dst))return ['ok'=>false,'error'=>'archive-move-failed'];
    foreach(['logs','presence'] as $kind){$sourcePath=loom_data_dir().'/'.$kind.'/'.$slug;if(is_dir($sourcePath))move_tree($sourcePath,$archiveDir.'/data/'.$kind);}
  }
  $meta=['archive_slug'=>$archiveSlug,'original_slug'=>$slug,'original_name'=>$projectData['name']??humanize_project_slug($slug),'archived_name'=>humanize_project_slug($archiveSlug),'reason'=>$reason,'archived_at'=>server_timestamp(),'archived_epoch_ms'=>server_epoch_ms(),'project_version'=>$projectData['version']??null,'source_type'=>$source?:'release'];
  write_archive_meta($archiveSlug,$meta);return ['ok'=>true,'archive_slug'=>$archiveSlug,'original_slug'=>$slug,'meta'=>$meta];
}
function rewrite_project_identity(string $projectDir,string $slug): void {
  $file=$projectDir.'/project.default.json';
  if(!is_file($file))throw new RuntimeException('Archived project is missing project.default.json');
  $data=read_json_file($file)?:[];$data['slug']=$slug;
  @file_put_contents($file,json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n",LOCK_EX);
  $override=loom_project_override_data($slug);$override['name']=humanize_project_slug($slug);$override['restored_from_archive']=$override['restored_from_archive']??null;
  @file_put_contents(loom_project_override_file($slug),json_encode($override,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n",LOCK_EX);
}
function restore_archived_project(string $archiveSlug): array {
  $archiveSlug=safe_slug($archiveSlug);$archiveDir=archive_project_dir($archiveSlug);if($archiveSlug===''||!is_dir($archiveDir))return ['ok'=>false,'error'=>'archive-not-found'];
  $meta=read_json_file($archiveDir.'/archive.json')?:[];$isInstance=is_dir($archiveDir.'/instance-project');$desired=$archiveSlug;$target=$desired;if(project_exists_anywhere($target,$archiveSlug))$target=next_old_slug($desired,$archiveSlug);
  if($isInstance){
    $src=$archiveDir.'/instance-project';$dst=loom_instance_projects_root_path().'/'.$target;if(!move_tree($src,$dst))return ['ok'=>false,'error'=>'restore-move-failed'];
    $runtime=$dst.'/project';rewrite_project_identity($runtime,$target);$override=read_json_file($dst.'/project-overrides.json')?:[];$override['restored_from_archive']=$archiveSlug;$override['restored_at']=server_timestamp();@file_put_contents($dst.'/project-overrides.json',json_encode($override,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n",LOCK_EX);
  }else{
    $src=$archiveDir.'/project';if(!is_dir($src))return ['ok'=>false,'error'=>'archive-not-found'];$dst=root_dir().'/projects/'.$target;if(!move_tree($src,$dst))return ['ok'=>false,'error'=>'restore-move-failed'];rewrite_project_identity($dst,$target);$pd=loom_project_override_data($target);$pd['restored_from_archive']=$archiveSlug;$pd['restored_at']=server_timestamp();@file_put_contents(loom_project_override_file($target),json_encode($pd,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n",LOCK_EX);foreach(['logs','presence'] as $kind){$sp=$archiveDir.'/data/'.$kind;if(is_dir($sp))move_tree($sp,loom_data_dir().'/'.$kind.'/'.$target);}
  }
  recursive_remove($archiveDir);return ['ok'=>true,'archive_slug'=>$archiveSlug,'restored_slug'=>$target,'meta'=>$meta];
}
function list_archived_projects(): array {
  $out=[];$base=archives_root();if(!is_dir($base))return $out;
  foreach(scandir($base)?:[] as $slug){if($slug==='.'||$slug==='..')continue;$dir=$base.'/'.$slug;if(!is_dir($dir))continue;$meta=read_json_file($dir.'/archive.json')?:[];$pf=is_file($dir.'/instance-project/project/project.default.json')?$dir.'/instance-project/project/project.default.json':$dir.'/project/project.default.json';$pd=read_json_file($pf)?:[];$out[]=['slug'=>$slug,'name'=>$meta['archived_name']??humanize_project_slug($slug),'description'=>$pd['description']??'Archived LOOM project','version'=>$pd['version']??($meta['project_version']??'0.0.0'),'archived_at'=>$meta['archived_at']??null,'original_slug'=>$meta['original_slug']??null,'reason'=>$meta['reason']??null,'source_type'=>$meta['source_type']??(is_dir($dir.'/instance-project')?'instance':'release')];}
  usort($out,fn($a,$b)=>strcmp((string)($b['archived_at']??''),(string)($a['archived_at']??'')));return $out;
}
function install_project_template(string $templateSlug,string $targetSlug): bool {
  $slug=safe_slug($targetSlug);$src=root_dir().'/templates/projects/'.safe_slug($templateSlug);$storage=loom_instance_projects_root_path().'/'.$slug;$dst=$storage.'/project';
  if($slug===''||!is_dir($src)||project_exists_anywhere($slug))return false;
  if(!is_dir(loom_instance_projects_root_path())&&!@mkdir(loom_instance_projects_root_path(),0775,true))return false;
  if(!recursive_copy($src,$dst)){recursive_remove($storage);return false;}
  $marker=['schema'=>'loom-instance-project/v1','slug'=>$slug,'createdAt'=>server_timestamp(),'runtime'=>'project','releaseManaged'=>false];
  @file_put_contents($storage.'/.loom-instance-project.json',json_encode($marker,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n",LOCK_EX);
  return true;
}
// Database and account helpers are loaded after the shared filesystem/lifecycle primitives.
require_once __DIR__.'/_database.php';
require_once __DIR__.'/_accounts.php';
require_once __DIR__.'/_global_profiles.php';
require_once __DIR__.'/_project_identities.php';
require_once __DIR__.'/_project_state.php';
require_once __DIR__.'/_avatars.php';
require_once __DIR__.'/_moderation.php';
require_once __DIR__.'/_geo.php';
require_once __DIR__.'/_guest_identities.php';
require_once __DIR__.'/_integrity.php';
require_once __DIR__.'/_guest_profiles.php';
require_once __DIR__.'/_continuity.php';
require_once __DIR__.'/_access.php';
require_once __DIR__.'/_migrations.php';
require_once __DIR__.'/_capabilities.php';
require_once __DIR__.'/_sandbox.php';
require_once __DIR__.'/_audit.php';
require_once __DIR__.'/_email.php';
require_once __DIR__.'/_referrals.php';
require_once __DIR__.'/_identity_cleanup.php';
loom_migration_bootstrap();

// ---- SEO / Social Metadata (v0.15.18) -------------------------------------
function loom_request_origin(): string {
  $https=(!empty($_SERVER['HTTPS'])&&strtolower((string)$_SERVER['HTTPS'])!=='off')||((string)($_SERVER['SERVER_PORT']??'')==='443');
  $proto=$https?'https':'http';
  $host=(string)($_SERVER['HTTP_HOST']??$_SERVER['SERVER_NAME']??'localhost');
  $host=preg_replace('/[^a-zA-Z0-9.\-:\[\]]/','',$host)?:'localhost';
  return $proto.'://'.$host;
}
function loom_absolute_web_url(?string $url): ?string {
  $url=trim((string)$url);if($url==='')return null;
  if(preg_match('~^https?://~i',$url))return $url;
  if(!str_starts_with($url,'/'))$url=rtrim(web_base_path(),'/').'/'.ltrim($url,'/');
  return rtrim(loom_request_origin(),'/').'/'.ltrim($url,'/');
}
function loom_project_absolute_public_url(string $project): string {
  $slug=safe_slug($project);if($slug==='')return loom_absolute_web_url(rtrim(web_base_path(),'/').'/')?:'/';
  // Canonicals use the short public route; shell location never leaks into links.
  $relative=loom_project_is_domain_landing($slug)?(rtrim(web_base_path(),'/').'/'):(rtrim(web_base_path(),'/').'/'.rawurlencode($slug).'/');
  return loom_absolute_web_url($relative)?:$relative;
}
function loom_project_social_meta(string $project,?string $canonicalUrl=null): array {
  $slug=safe_slug($project);$profile=loom_project_profile_payload($slug)?:[];
  $cfg=loom_project_core_effective_config($slug,'core.seo.social')?:[];
  $titleMode=(string)($cfg['titleMode']??'project');$descMode=(string)($cfg['descriptionMode']??'project');$imageMode=(string)($cfg['imageMode']??'auto');
  $title=$titleMode==='custom'&&trim((string)($cfg['customTitle']??''))!==''?loom_clean_project_text($cfg['customTitle'],120):(string)($profile['name']??humanize_project_slug($slug));
  $description=$descMode==='custom'&&trim((string)($cfg['customDescription']??''))!==''?loom_clean_project_text($cfg['customDescription'],240):loom_project_effective_bio($slug);
  $showcase=loom_project_asset_url($slug,'assets/showcase.png');$logo=$profile['branding']['logo_url']??null;$loom=loom_default_project_logo_url();
  $image=null;
  if($imageMode==='loom')$image=$loom;
  elseif($imageMode==='logo')$image=$logo?:$loom;
  elseif($imageMode==='showcase')$image=$showcase?:$logo?:$loom;
  else $image=$showcase?:$logo?:$loom;
  $robots=match((string)($cfg['robotsMode']??'index-follow')){'noindex-follow'=>'noindex,follow','noindex-nofollow'=>'noindex,nofollow',default=>'index,follow'};
  return ['title'=>$title?:'LOOM Project','description'=>$description?:($title.' is powered by LOOM.'),'image'=>loom_absolute_web_url($image),'url'=>$canonicalUrl?:loom_project_absolute_public_url($slug),'robots'=>$robots,'project'=>$slug];
}
function loom_social_meta_html(array $meta,bool $project=true): string {
  $e=fn($v)=>htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
  $title=$e($meta['title']??'LOOM');$desc=$e($meta['description']??'LOOM modular application engine.');$url=$e($meta['url']??loom_absolute_web_url(web_base_path().'/'));$image=$e($meta['image']??loom_absolute_web_url(loom_default_project_logo_url()));$robots=$e($meta['robots']??'index,follow');
  $type=$project?'website':'website';
  return '<title>'.$title.'</title><meta name="description" content="'.$desc.'"><meta name="robots" content="'.$robots.'"><link rel="canonical" href="'.$url.'"><meta property="og:type" content="'.$type.'"><meta property="og:site_name" content="LOOM"><meta property="og:title" content="'.$title.'"><meta property="og:description" content="'.$desc.'"><meta property="og:url" content="'.$url.'">'.($image!==''?'<meta property="og:image" content="'.$image.'"><meta name="twitter:card" content="summary_large_image"><meta name="twitter:image" content="'.$image.'">':'<meta name="twitter:card" content="summary">').'<meta name="twitter:title" content="'.$title.'"><meta name="twitter:description" content="'.$desc.'">';
}
function loom_generic_social_meta(?string $canonicalUrl=null,string $title='LOOM',string $description='LOOM modular application engine.'): array {
  return ['title'=>$title,'description'=>$description,'image'=>loom_absolute_web_url(loom_default_project_logo_url()),'url'=>$canonicalUrl?:loom_absolute_web_url(rtrim(web_base_path(),'/').'/'),'robots'=>'index,follow'];
}
