<?php
// LOOM HTML Framer helpers.
declare(strict_types=1);

const LOOM_HTML_FRAMER_MAX_ZIP = 26214400;       // 25 MB
const LOOM_HTML_FRAMER_MAX_FILES = 800;
const LOOM_HTML_FRAMER_MAX_TOTAL = 104857600;    // 100 MB
const LOOM_HTML_FRAMER_MAX_FILE = 12582912;      // 12 MB

function loom_html_framer_root(string $project): string {
  $d=loom_instance_project_dir($project).'/html-framer';
  ensure_dir($d);ensure_dir($d.'/frames');ensure_dir($d.'/.tmp');
  return $d;
}
function loom_html_framer_registry_file(string $project): string { return loom_html_framer_root($project).'/frames.json'; }
function loom_html_framer_registry(string $project): array {
  $x=read_json_file(loom_html_framer_registry_file($project));
  if(!is_array($x))$x=[];
  if(!isset($x['schema']))$x['schema']='loom-html-framer/v1';
  if(!is_array($x['frames']??null))$x['frames']=[];
  return $x;
}
function loom_html_framer_write_registry(string $project,array $data): void {
  $data['schema']='loom-html-framer/v1';$data['updatedAt']=server_timestamp();
  $json=json_encode($data,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  if($json===false||@file_put_contents(loom_html_framer_registry_file($project),$json."\n",LOCK_EX)===false)
    throw new RuntimeException('Could not save HTML Framer registry');
}
function loom_html_framer_frame_id(): string { return 'hf_'.bin2hex(random_bytes(7)); }
function loom_html_framer_safe_rel(string $path): ?string {
  $path=str_replace('\\','/',$path);$path=preg_replace('~/+~','/',$path)??'';
  $path=ltrim($path,'/');
  if($path===''||str_contains($path,"\0"))return null;
  $parts=[];
  foreach(explode('/',$path) as $part){
    if($part===''||$part==='.')continue;
    if($part==='..')return null;
    $part=trim($part);
    if($part==='')continue;
    $parts[]=$part;
  }
  return $parts?implode('/',$parts):null;
}
function loom_html_framer_frame_dir(string $project,string $frameId): string {
  if(!preg_match('/^hf_[a-f0-9]{14}$/',$frameId))throw new RuntimeException('Invalid HTML frame id');
  return loom_html_framer_root($project).'/frames/'.$frameId;
}
function loom_html_framer_allowed_extension(string $path): bool {
  $ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
  static $allowed=[
    'html'=>1,'htm'=>1,'css'=>1,'js'=>1,'mjs'=>1,'cjs'=>1,
    'json'=>1,'txt'=>1,'xml'=>1,'webmanifest'=>1,'map'=>1,
    'png'=>1,'jpg'=>1,'jpeg'=>1,'gif'=>1,'webp'=>1,'svg'=>1,'ico'=>1,'bmp'=>1,'avif'=>1,
    'woff'=>1,'woff2'=>1,'ttf'=>1,'otf'=>1,'eot'=>1,
    'mp3'=>1,'wav'=>1,'ogg'=>1,'m4a'=>1,'mp4'=>1,'webm'=>1
  ];
  return isset($allowed[$ext]);
}
function loom_html_framer_is_html(string $path): bool { return in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['html','htm'],true); }
function loom_html_framer_is_css(string $path): bool { return strtolower(pathinfo($path,PATHINFO_EXTENSION))==='css'; }
function loom_html_framer_is_js(string $path): bool { return in_array(strtolower(pathinfo($path,PATHINFO_EXTENSION)),['js','mjs','cjs'],true); }

function loom_html_framer_zip_supported(): bool {
  return class_exists('ZipArchive')||class_exists('PharData');
}
function loom_html_framer_assert_zip(string $zipPath): void {
  if(!loom_html_framer_zip_supported())throw new RuntimeException('PHP ZIP support is required for HTML Framer (ZipArchive or PharData).');
  if(!is_file($zipPath))throw new RuntimeException('ZIP upload is missing.');
  if(filesize($zipPath)>LOOM_HTML_FRAMER_MAX_ZIP)throw new RuntimeException('ZIP is larger than the 25 MB HTML Framer limit.');
}
function loom_html_framer_zip_inventory(string $zipPath): array {
  loom_html_framer_assert_zip($zipPath);
  $files=[];$total=0;$warnings=[];
  if(class_exists('ZipArchive')){
    $zip=new ZipArchive();$ok=$zip->open($zipPath);if($ok!==true)throw new RuntimeException('Could not open ZIP package.');
    try{
      if($zip->numFiles>LOOM_HTML_FRAMER_MAX_FILES)throw new RuntimeException('ZIP contains too many files.');
      for($i=0;$i<$zip->numFiles;$i++){
        $st=$zip->statIndex($i);if(!is_array($st))continue;
        $raw=(string)($st['name']??'');if(str_ends_with($raw,'/'))continue;
        $safe=loom_html_framer_safe_rel($raw);if(!$safe)throw new RuntimeException('ZIP contains an unsafe path: '.$raw);
        $size=(int)($st['size']??0);if($size>LOOM_HTML_FRAMER_MAX_FILE)throw new RuntimeException('A ZIP file exceeds the 12 MB per-file limit: '.$safe);
        $total+=$size;if($total>LOOM_HTML_FRAMER_MAX_TOTAL)throw new RuntimeException('ZIP expands beyond the 100 MB HTML Framer limit.');
        if(!loom_html_framer_allowed_extension($safe)){$warnings[]='Skipped unsupported file: '.$safe;continue;}
        $files[$safe]=['sourceType'=>'ziparchive','source'=>$i,'size'=>$size,'crc'=>(int)($st['crc']??0)];
      }
    }finally{$zip->close();}
  }else{
    try{$phar=new PharData($zipPath);}catch(Throwable $e){throw new RuntimeException('Could not open ZIP package: '.$e->getMessage());}
    $prefix='phar://'.str_replace('\\','/',$zipPath).'/';$count=0;
    $it=new RecursiveIteratorIterator($phar,RecursiveIteratorIterator::LEAVES_ONLY);
    foreach($it as $key=>$file){
      if(!$file->isFile())continue;$count++;if($count>LOOM_HTML_FRAMER_MAX_FILES)throw new RuntimeException('ZIP contains too many files.');
      $raw=str_replace('\\','/',str_starts_with((string)$key,$prefix)?substr((string)$key,strlen($prefix)):$file->getFilename());
      $safe=loom_html_framer_safe_rel($raw);if(!$safe)throw new RuntimeException('ZIP contains an unsafe path: '.$raw);
      $size=(int)$file->getSize();if($size>LOOM_HTML_FRAMER_MAX_FILE)throw new RuntimeException('A ZIP file exceeds the 12 MB per-file limit: '.$safe);
      $total+=$size;if($total>LOOM_HTML_FRAMER_MAX_TOTAL)throw new RuntimeException('ZIP expands beyond the 100 MB HTML Framer limit.');
      if(!loom_html_framer_allowed_extension($safe)){$warnings[]='Skipped unsupported file: '.$safe;continue;}
      $files[$safe]=['sourceType'=>'phar','source'=>(string)$key,'size'=>$size,'crc'=>0];
    }
  }
  if(!$files)throw new RuntimeException('ZIP contains no supported static files.');
  $html=array_values(array_filter(array_keys($files),'loom_html_framer_is_html'));
  if(!$html)throw new RuntimeException('ZIP must contain at least one .html or .htm file.');
  return ['files'=>$files,'html'=>$html,'totalBytes'=>$total,'warnings'=>$warnings];
}
function loom_html_framer_zip_read(string $zipPath,array $meta): string {
  loom_html_framer_assert_zip($zipPath);
  if(($meta['sourceType']??'')==='ziparchive'&&class_exists('ZipArchive')){
    $zip=new ZipArchive();$ok=$zip->open($zipPath);if($ok!==true)throw new RuntimeException('Could not open ZIP package.');
    try{$data=$zip->getFromIndex((int)$meta['source']);if($data===false)throw new RuntimeException('Could not read ZIP member.');return $data;}
    finally{$zip->close();}
  }
  $source=(string)($meta['source']??'');if($source==='')throw new RuntimeException('ZIP member source is missing.');
  $data=@file_get_contents($source);if($data===false)throw new RuntimeException('Could not read ZIP member.');
  return $data;
}
function loom_html_framer_entrypoint_suggestion(array $html): array {
  $rootIndex=array_values(array_filter($html,fn($p)=>in_array(strtolower($p),['index.html','index.htm'],true)));
  if(count($rootIndex)===1)return ['entrypoint'=>$rootIndex[0],'reason'=>'root-index','ambiguous'=>false];
  $indexes=array_values(array_filter($html,fn($p)=>in_array(strtolower(basename($p)),['index.html','index.htm'],true)));
  if(count($indexes)===1)return ['entrypoint'=>$indexes[0],'reason'=>'unique-index','ambiguous'=>false];
  if(count($html)===1)return ['entrypoint'=>$html[0],'reason'=>'only-html','ambiguous'=>false];
  return ['entrypoint'=>null,'reason'=>'multiple-html','ambiguous'=>true];
}
function loom_html_framer_text_ok(string $data): bool {
  return !str_contains($data,"\0") && ($data==='' || preg_match('//u',$data)===1);
}
function loom_html_framer_local_ref(string $ref): bool {
  $r=trim($ref);if($r===''||str_starts_with($r,'#')||str_starts_with($r,'//'))return false;
  return !preg_match('~^[a-z][a-z0-9+.-]*:~i',$r);
}
function loom_html_framer_ref_path(string $ref): string {
  $q=strcspn($ref,'?#');return substr($ref,0,$q);
}
function loom_html_framer_normalize_join(string $from,string $ref): ?string {
  $refPath=loom_html_framer_ref_path($ref);
  if($refPath==='')return null;
  if(str_starts_with($refPath,'/'))$candidate=ltrim($refPath,'/');
  else{
    $dir=str_replace('\\','/',dirname($from));if($dir==='.')$dir='';
    $candidate=($dir!==''?$dir.'/':'').$refPath;
  }
  $stack=[];
  foreach(explode('/',str_replace('\\','/',$candidate)) as $part){
    if($part===''||$part==='.')continue;
    if($part==='..'){if(!$stack)return null;array_pop($stack);continue;}
    $stack[]=$part;
  }
  return $stack?implode('/',$stack):null;
}
function loom_html_framer_file_maps(array $paths): array {
  $exact=[];$lower=[];$base=[];
  foreach($paths as $p){
    $exact[$p]=$p;$lower[strtolower($p)][]=$p;$base[strtolower(basename($p))][]=$p;
  }
  return [$exact,$lower,$base];
}
function loom_html_framer_resolve_ref(string $from,string $ref,array $paths): ?string {
  if(!loom_html_framer_local_ref($ref))return null;
  [$exact,$lower,$base]=loom_html_framer_file_maps($paths);
  $candidate=loom_html_framer_normalize_join($from,$ref);
  if($candidate!==null&&isset($exact[$candidate]))return $candidate;
  if($candidate!==null){
    $hits=$lower[strtolower($candidate)]??[];
    if(count($hits)===1)return $hits[0];
  }
  $name=strtolower(basename(loom_html_framer_ref_path($ref)));
  $hits=$base[$name]??[];
  return count($hits)===1?$hits[0]:null;
}
function loom_html_framer_extract_refs(string $path,string $data): array {
  $refs=[];
  if(loom_html_framer_is_html($path)){
    if(preg_match_all('~\b(?:src|href)\s*=\s*(["\'])(.*?)\1~is',$data,$m))
      foreach($m[2] as $r)if(loom_html_framer_local_ref($r))$refs[]=$r;
  }elseif(loom_html_framer_is_css($path)){
    if(preg_match_all('~url\(\s*(["\']?)(.*?)\1\s*\)~is',$data,$m))
      foreach($m[2] as $r)if(loom_html_framer_local_ref($r))$refs[]=$r;
    if(preg_match_all('~@import\s+(?:url\(\s*)?(["\'])(.*?)\1~is',$data,$m))
      foreach($m[2] as $r)if(loom_html_framer_local_ref($r))$refs[]=$r;
  }elseif(loom_html_framer_is_js($path)){
    $patterns=[
      '~(?:import|export)\s+(?:[^"\']*?\s+from\s+)?(["\'])(.*?)\1~is',
      '~import\s*\(\s*(["\'])(.*?)\1\s*\)~is',
      '~new\s+URL\s*\(\s*(["\'])(.*?)\1\s*,\s*import\.meta\.url\s*\)~is'
    ];
    foreach($patterns as $pat)if(preg_match_all($pat,$data,$m))
      foreach($m[2] as $r)if(loom_html_framer_local_ref($r))$refs[]=$r;
  }
  return array_values(array_unique($refs));
}
function loom_html_framer_analyze_zip(string $zipPath,?string $entrypoint=null): array {
  $inv=loom_html_framer_zip_inventory($zipPath);$paths=array_keys($inv['files']);$pick=loom_html_framer_entrypoint_suggestion($inv['html']);
  if($entrypoint!==null){
    $entrypoint=loom_html_framer_safe_rel($entrypoint);
    if(!$entrypoint||!isset($inv['files'][$entrypoint])||!loom_html_framer_is_html($entrypoint))
      throw new RuntimeException('Selected HTML entrypoint is not present in the ZIP.');
    $pick=['entrypoint'=>$entrypoint,'reason'=>'admin-selected','ambiguous'=>false];
  }
  $entry=$pick['entrypoint'];$relationships=[];$referenced=[];$missing=[];$repairs=[];$syntaxWarnings=[];
  foreach($paths as $p){
    if(!(loom_html_framer_is_html($p)||loom_html_framer_is_css($p)||loom_html_framer_is_js($p)))continue;
    $data=loom_html_framer_zip_read($zipPath,$inv['files'][$p]);
    if(!loom_html_framer_text_ok($data)){$syntaxWarnings[]='Text file is not valid UTF-8 or contains binary NUL bytes: '.$p;continue;}
    if(loom_html_framer_is_html($p)&&trim($data)==='')$syntaxWarnings[]='HTML file is empty: '.$p;
    foreach(loom_html_framer_extract_refs($p,$data) as $raw){
      $target=loom_html_framer_resolve_ref($p,$raw,$paths);
      if($target){
        $referenced[$target]=true;$relationships[]=['from'=>$p,'raw'=>$raw,'target'=>$target];
        $expected=loom_html_framer_normalize_join($p,$raw);
        if($expected!==$target)$repairs[]=['from'=>$p,'raw'=>$raw,'target'=>$target];
      }else $missing[]=['from'=>$p,'raw'=>$raw];
    }
  }
  $css=array_values(array_filter($paths,'loom_html_framer_is_css'));
  $js=array_values(array_filter($paths,'loom_html_framer_is_js'));
  $autoCss=[];$autoJs=[];
  if($entry){
    $entryData=loom_html_framer_zip_read($zipPath,$inv['files'][$entry]);
    $entryRefs=[];
    foreach(loom_html_framer_extract_refs($entry,$entryData) as $r){$t=loom_html_framer_resolve_ref($entry,$r,$paths);if($t)$entryRefs[$t]=true;}
    foreach($css as $p)if(!isset($entryRefs[$p])&&!isset($referenced[$p]))$autoCss[]=$p;
    foreach($js as $p)if(!isset($entryRefs[$p])&&!isset($referenced[$p]))$autoJs[]=$p;
    // A dependency cycle may leave no JavaScript root. Attach one deterministic member so the graph can start.
    if($js&&!$entryRefs&&!$autoJs){sort($js,SORT_NATURAL|SORT_FLAG_CASE);$autoJs[]=$js[0];$syntaxWarnings[]='JavaScript dependency cycle detected; LOOM selected '.$js[0].' as the root script.';}
  }
  sort($autoCss,SORT_NATURAL|SORT_FLAG_CASE);sort($autoJs,SORT_NATURAL|SORT_FLAG_CASE);
  $fatal=[];
  if($entry){
    $entryData=loom_html_framer_zip_read($zipPath,$inv['files'][$entry]);
    if(trim($entryData)===''||!preg_match('~<\s*[a-zA-Z][^>]*>~',$entryData))$fatal[]='Selected HTML entrypoint does not contain recognizable HTML markup.';
  }
  return [
    'entrypoint'=>$entry,'entrypointReason'=>$pick['reason'],'needsEntrypoint'=>(bool)$pick['ambiguous'],
    'htmlCandidates'=>$inv['html'],'fileCount'=>count($paths),'totalBytes'=>$inv['totalBytes'],
    'cssCount'=>count($css),'jsCount'=>count($js),'autoAttachCss'=>$autoCss,'autoAttachJs'=>$autoJs,
    'relationships'=>$relationships,'repairs'=>$repairs,'missingRefs'=>$missing,
    'warnings'=>array_values(array_unique(array_merge($inv['warnings'],$syntaxWarnings))),
    'fatal'=>$fatal,'files'=>$paths
  ];
}
function loom_html_framer_extract_zip(string $zipPath,string $dest,array $allowedFiles): void {
  ensure_dir($dest);$inv=loom_html_framer_zip_inventory($zipPath);
  foreach($allowedFiles as $safe){
    if(!isset($inv['files'][$safe]))continue;
    $data=loom_html_framer_zip_read($zipPath,$inv['files'][$safe]);
    $target=$dest.'/'.$safe;ensure_dir(dirname($target));
    if(@file_put_contents($target,$data,LOCK_EX)===false)throw new RuntimeException('Could not store '.$safe);
  }
}
function loom_html_framer_remove_tree(string $dir): void {
  if(!is_dir($dir))return;
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir,FilesystemIterator::SKIP_DOTS),RecursiveIteratorIterator::CHILD_FIRST);
  foreach($it as $f){$f->isDir()?@rmdir($f->getPathname()):@unlink($f->getPathname());}
  @rmdir($dir);
}
function loom_html_framer_import(string $project,string $zipPath,string $zipName,?string $entrypoint,int $defaultHeight=520,?string $replaceId=null): array {
  $analysis=loom_html_framer_analyze_zip($zipPath,$entrypoint);
  if($analysis['needsEntrypoint'])return ['needsEntrypoint'=>true,'analysis'=>$analysis];
  if($analysis['fatal'])throw new RuntimeException(implode(' ',$analysis['fatal']));
  $frameId=$replaceId?:loom_html_framer_frame_id();
  if($replaceId&&!preg_match('/^hf_[a-f0-9]{14}$/',$replaceId))throw new RuntimeException('Invalid replacement frame id');
  $root=loom_html_framer_root($project);$tmp=$root.'/.tmp/'.$frameId.'_'.bin2hex(random_bytes(3));
  ensure_dir($tmp);$filesDir=$tmp.'/files';ensure_dir($filesDir);
  try{
    loom_html_framer_extract_zip($zipPath,$filesDir,$analysis['files']);
    @copy($zipPath,$tmp.'/source.zip');
    $registry=loom_html_framer_registry($project);$existing=$registry['frames'][$frameId]??[];
    $title=trim((string)($existing['title']??''));
    if($title===''){
      $entryData=(string)@file_get_contents($filesDir.'/'.$analysis['entrypoint']);
      if(preg_match('~<title[^>]*>(.*?)</title>~is',$entryData,$m))$title=trim(html_entity_decode(strip_tags($m[1]),ENT_QUOTES|ENT_HTML5,'UTF-8'));
    }
    if($title==='')$title=pathinfo($zipName,PATHINFO_FILENAME)?:'HTML Frame';
    $title=loom_clean_project_text($title,80);if($title==='')$title='HTML Frame';
    $revision=max(1,(int)($existing['revision']??0)+1);
    $order=(int)($existing['order']??(60000+count($registry['frames'])*10));
    $frame=[
      'id'=>$frameId,'title'=>$title,'enabled'=>array_key_exists('enabled',$existing)?(bool)$existing['enabled']:true,
      'entrypoint'=>$analysis['entrypoint'],'height'=>max(200,min(1600,(int)($existing['height']??$defaultHeight))),
      'order'=>$order,'revision'=>$revision,'zipName'=>basename($zipName),'fileCount'=>$analysis['fileCount'],
      'cssCount'=>$analysis['cssCount'],'jsCount'=>$analysis['jsCount'],'autoAttachCss'=>$analysis['autoAttachCss'],
      'autoAttachJs'=>$analysis['autoAttachJs'],'repairs'=>$analysis['repairs'],'missingRefs'=>$analysis['missingRefs'],
      'warnings'=>$analysis['warnings'],'createdAt'=>$existing['createdAt']??server_timestamp(),'updatedAt'=>server_timestamp()
    ];
    $final=loom_html_framer_frame_dir($project,$frameId);$backup=null;
    if(is_dir($final)){$backup=$final.'.bak.'.bin2hex(random_bytes(2));@rename($final,$backup);}
    if(!@rename($tmp,$final)){if($backup)@rename($backup,$final);throw new RuntimeException('Could not publish HTML frame package.');}
    try{
      $registry['frames'][$frameId]=$frame;loom_html_framer_write_registry($project,$registry);
      if($backup)loom_html_framer_remove_tree($backup);
    }catch(Throwable $writeError){
      loom_html_framer_remove_tree($final);
      if($backup)@rename($backup,$final);
      throw $writeError;
    }
    return ['needsEntrypoint'=>false,'frame'=>$frame,'analysis'=>$analysis];
  }catch(Throwable $e){loom_html_framer_remove_tree($tmp);throw $e;}
}
function loom_html_framer_public_frames(string $project): array {
  $r=loom_html_framer_registry($project);$frames=array_values($r['frames']);
  usort($frames,fn($a,$b)=>(int)($a['order']??0)<=>(int)($b['order']??0)?:strcmp((string)$a['id'],(string)$b['id']));
  return $frames;
}
function loom_html_framer_frame(string $project,string $frameId): ?array {
  $r=loom_html_framer_registry($project);$f=$r['frames'][$frameId]??null;return is_array($f)?$f:null;
}
function loom_html_framer_endpoint_url(string $project,string $frameId,string $path,string $clientId=''): string {
  $base=web_base_path().'/api/html-framer-file.php?project='.rawurlencode($project).'&frame='.rawurlencode($frameId).'&path='.rawurlencode($path);
  if($clientId!=='')$base.='&clientId='.rawurlencode($clientId);
  return $base;
}
function loom_html_framer_rewrite_ref(string $project,string $frameId,string $from,string $ref,array $paths,string $clientId=''): string {
  if(!loom_html_framer_local_ref($ref))return $ref;
  $target=loom_html_framer_resolve_ref($from,$ref,$paths);if(!$target)return $ref;
  $suffix='';$pos=strpos($ref,'#');if($pos!==false)$suffix=substr($ref,$pos);
  return loom_html_framer_endpoint_url($project,$frameId,$target,$clientId).$suffix;
}
function loom_html_framer_serve_transform(string $project,string $frameId,string $path,string $data,array $frame,array $paths,string $clientId=''): string {
  $rewrite=fn(string $ref)=>loom_html_framer_rewrite_ref($project,$frameId,$path,$ref,$paths,$clientId);
  if(loom_html_framer_is_html($path)){
    $data=preg_replace_callback('~(\b(?:src|href)\s*=\s*)(["\'])(.*?)\2~is',function($m)use($rewrite){return $m[1].$m[2].htmlspecialchars($rewrite(html_entity_decode($m[3],ENT_QUOTES|ENT_HTML5,'UTF-8')),ENT_QUOTES|ENT_HTML5,'UTF-8').$m[2];},$data)??$data;
    if($path===(string)($frame['entrypoint']??'')){
      $css='';
      foreach(($frame['autoAttachCss']??[]) as $asset)$css.='<link rel="stylesheet" href="'.htmlspecialchars(loom_html_framer_endpoint_url($project,$frameId,(string)$asset,$clientId),ENT_QUOTES|ENT_HTML5,'UTF-8').'">'."\n";
      $js='';
      foreach(($frame['autoAttachJs']??[]) as $asset){
        $file=loom_html_framer_frame_dir($project,$frameId).'/files/'.$asset;$raw=is_file($file)?(string)@file_get_contents($file):'';
        $module=preg_match('~(^|\n)\s*(?:import\s|export\s)~',$raw)===1||strtolower(pathinfo((string)$asset,PATHINFO_EXTENSION))==='mjs';
        $js.='<script '.($module?'type="module" ':'').'src="'.htmlspecialchars(loom_html_framer_endpoint_url($project,$frameId,(string)$asset,$clientId),ENT_QUOTES|ENT_HTML5,'UTF-8').'"></script>'."\n";
      }
      if($css!==''){
        if(stripos($data,'</head>')!==false)$data=preg_replace('~</head>~i',$css.'</head>',$data,1)??$data;
        else $data=$css.$data;
      }
      if($js!==''){
        if(stripos($data,'</body>')!==false)$data=preg_replace('~</body>~i',$js.'</body>',$data,1)??$data;
        else $data.=$js;
      }
    }
    return $data;
  }
  if(loom_html_framer_is_css($path)){
    $data=preg_replace_callback('~url\(\s*(["\']?)(.*?)\1\s*\)~is',function($m)use($rewrite){$r=$rewrite($m[2]);return 'url("'.str_replace('"','%22',$r).'")';},$data)??$data;
    $data=preg_replace_callback('~(@import\s+(?:url\(\s*)?)(["\'])(.*?)\2~is',function($m)use($rewrite){return $m[1].$m[2].$rewrite($m[3]).$m[2];},$data)??$data;
    return $data;
  }
  if(loom_html_framer_is_js($path)){
    $patterns=[
      '~((?:import|export)\s+(?:[^"\']*?\s+from\s+)?)(["\'])(.*?)\2~is',
      '~(import\s*\(\s*)(["\'])(.*?)\2(\s*\))~is',
      '~(new\s+URL\s*\(\s*)(["\'])(.*?)\2(\s*,\s*import\.meta\.url\s*\))~is'
    ];
    foreach($patterns as $idx=>$pat){
      $data=preg_replace_callback($pat,function($m)use($rewrite,$idx){
        $tail=$m[4]??'';return $m[1].$m[2].$rewrite($m[3]).$m[2].$tail;
      },$data)??$data;
    }
  }
  return $data;
}
function loom_html_framer_mime(string $path): string {
  $ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
  $map=[
    'html'=>'text/html; charset=utf-8','htm'=>'text/html; charset=utf-8','css'=>'text/css; charset=utf-8',
    'js'=>'application/javascript; charset=utf-8','mjs'=>'application/javascript; charset=utf-8','cjs'=>'application/javascript; charset=utf-8',
    'json'=>'application/json; charset=utf-8','map'=>'application/json; charset=utf-8','xml'=>'application/xml; charset=utf-8',
    'txt'=>'text/plain; charset=utf-8','webmanifest'=>'application/manifest+json; charset=utf-8',
    'png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp','svg'=>'image/svg+xml','ico'=>'image/x-icon','bmp'=>'image/bmp','avif'=>'image/avif',
    'woff'=>'font/woff','woff2'=>'font/woff2','ttf'=>'font/ttf','otf'=>'font/otf','eot'=>'application/vnd.ms-fontobject',
    'mp3'=>'audio/mpeg','wav'=>'audio/wav','ogg'=>'audio/ogg','m4a'=>'audio/mp4','mp4'=>'video/mp4','webm'=>'video/webm'
  ];
  return $map[$ext]??'application/octet-stream';
}
function loom_html_framer_runtime_descriptors(string $project,string $clientId=''): array {
  $managerFile=root_dir().'/core-modules/html-framer/manifest.json';$manager=read_json_file($managerFile)?:[];
  $cfg=loom_module_config_with_admin_overrides($project,$manager);
  if(($cfg['enabled']??true)===false)return [];
  $entry=root_dir().'/core-modules/html-framer/frame-action.js';
  if(!is_file($entry))return [];
  $out=[];
  foreach(loom_html_framer_public_frames($project) as $frame){
    if(($frame['enabled']??true)===false)continue;
    $id=(string)$frame['id'];$order=max(1,(int)($frame['order']??60000));$revision=max(1,(int)($frame['revision']??1));
    $actionId='html.frame.'.$id;
    $src=loom_html_framer_endpoint_url($project,$id,(string)$frame['entrypoint'],$clientId).'&r='.$revision;
    $finger=substr(hash('sha256',implode('|',[$project,$id,(string)$revision,hash_file('sha1',$entry)?:'',json_encode($frame)])),0,16);
    $out[]=[
      'schema_version'=>'1.8','enabled'=>true,
      'action'=>[
        'id'=>$actionId,'name'=>(string)($frame['title']??'HTML Frame'),'description'=>'Sandboxed HTML Framer package: '.(string)($frame['zipName']??''),
        'kind'=>'system','behavior'=>'stateful','autostart'=>true,'parent'=>'loom.html-framer','category'=>'project/html-frame',
        'tags'=>['html-framer','html','sandbox','interop'],'steps'=>[['id'=>'mount-frame','name'=>'Mount sandboxed HTML frame']]
      ],
      'user_actions'=>[],
      'module'=>['entry'=>'frame-action.js','version'=>'1.0.0','dependencies'=>[],'styles'=>[],'order'=>(string)$order],
      'config'=>[
        'frameId'=>$id,'src'=>$src,'height'=>max(200,min(1600,(int)($frame['height']??520))),
        'entrypoint'=>(string)$frame['entrypoint'],'tracking'=>'boundary-only'
      ],
      'admin_overrides'=>new stdClass(),'admin_settings'=>['fields'=>[]],
      'extensions'=>new stdClass(),'capabilities'=>['provides'=>[],'requires'=>[],'permissions'=>[]],
      'presentation'=>[
        'role'=>'content','collapsible'=>true,'mount'=>['region'=>'root'],
        'layout'=>['width'=>'full','align'=>'stretch','position'=>'flow','order'=>$order,'className'=>'loom-html-framer-runtime-module']
      ],
      'pegboard'=>['preferredDepth'=>2,'accent'=>'blue'],
      'order_effective'=>$order,'order_display'=>str_pad((string)$order,5,'0',STR_PAD_LEFT),'order_locked'=>false,
      'bootstrap'=>new stdClass(),'entry_url'=>rel_url($entry),'styles'=>[],'manifest_url'=>rel_url($managerFile),
      'fingerprint'=>$finger,'folder'=>'instance/projects/'.$project.'/html-framer/'.$id,'source'=>'html-framer'
    ];
  }
  return $out;
}
