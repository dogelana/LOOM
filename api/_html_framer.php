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
  $auto=(string)($x['autoFullscreenFrameId']??'');
  $x['autoFullscreenFrameId']=preg_match('/^hf_[a-f0-9]{14}$/',$auto)?$auto:'';
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

function loom_html_framer_reader_clean(string $value,string $fallback='Control'): string {
  $value=html_entity_decode(strip_tags($value),ENT_QUOTES|ENT_HTML5,'UTF-8');
  $value=preg_replace('/\s+/u',' ',trim($value))??'';
  if($value==='')$value=$fallback;
  return substr($value,0,80);
}
function loom_html_framer_reader_slug(string $value): string {
  $value=strtolower($value);
  $value=preg_replace('/[^a-z0-9]+/','-',trim($value))??'';
  return trim(substr($value,0,32),'-')?:'control';
}
function loom_html_framer_action_reader_scan_html(string $html): array {
  $actions=[];$functions=[];$warnings=[];$ordinal=0;
  if(class_exists('DOMDocument')){
    $prev=libxml_use_internal_errors(true);
    try{
      $doc=new DOMDocument();
      $ok=@$doc->loadHTML($html,LIBXML_NOWARNING|LIBXML_NOERROR|LIBXML_NONET);
      if($ok){
        $xp=new DOMXPath($doc);
        $nodes=$xp->query('//button | //a[@href] | //form | //input[not(translate(@type,"HIDDEN","hidden")="hidden")] | //select | //textarea | //*[@role="button"]');
        if($nodes){
          foreach($nodes as $node){
            if(!($node instanceof DOMElement))continue;
            $ordinal++;
            $tag=strtolower($node->tagName);
            $type=strtolower(trim($node->getAttribute('type')));
            $role=strtolower(trim($node->getAttribute('role')));
            $id=trim($node->getAttribute('id'));
            $name=trim($node->getAttribute('name'));
            $event='click';
            if($tag==='form')$event='submit';
            elseif($tag==='a')$event='navigate';
            elseif(in_array($tag,['select','textarea'],true))$event='change';
            elseif($tag==='input'&&!in_array($type,['button','submit','reset','image'],true))$event='change';
            $label=trim($node->getAttribute('aria-label'));
            if($label==='')$label=trim($node->getAttribute('title'));
            if($label===''&&in_array($tag,['button','a'],true))$label=trim($node->textContent??'');
            if($label===''&&$tag==='input'&&in_array($type,['button','submit','reset'],true))$label=trim($node->getAttribute('value'));
            if($label==='')$label=$id?:($name?:ucfirst($tag));
            $label=loom_html_framer_reader_clean($label,ucfirst($tag));
            $handler='';
            foreach(['click','submit','change'] as $ev){
              $raw=trim($node->getAttribute('on'.$ev));
              if($raw!==''&&preg_match('/([A-Za-z_$][A-Za-z0-9_$]*)\s*\(/',$raw,$m)){
                $handler=$m[1];$functions[$handler]=true;break;
              }
            }
            $seed=implode('|',[$event,$tag,$id,$name,$type,$role,$label,(string)$ordinal]);
            $key=$event.'.'.loom_html_framer_reader_slug($label).'.'.substr(hash('sha256',$seed),0,8);
            $verb=['click'=>'Click','navigate'=>'Open','submit'=>'Submit','change'=>'Change'][$event]??'Use';
            $actions[]=[
              'key'=>$key,'event'=>$event,'name'=>$verb.' '.$label,
              'description'=>'Auto-discovered framed HTML interaction.',
              'match'=>['tag'=>$tag,'id'=>$id?:null,'name'=>$name?:null,'type'=>$type?:null,'role'=>$role?:null,'ordinal'=>$ordinal],
              'handler'=>$handler?:null
            ];
            if(count($actions)>=160)break;
          }
        }
      }else $warnings[]='Action Reader could not parse the entry HTML DOM; generic interaction capture remains available.';
    }catch(Throwable $e){$warnings[]='Action Reader DOM scan fell back to generic interaction capture.';}
    finally{libxml_clear_errors();libxml_use_internal_errors($prev);}
  }else{
    // Minimal regex fallback for hosts without the PHP DOM extension.
    // Runtime generic capture would still work without this, but the fallback
    // lets LOOM predeclare common controls in Action Registry too.
    if(preg_match_all('~<(button|a|form|input|select|textarea)\b([^>]*)>(.*?)</\1\s*>|<(input)\b([^>]*)/?>~is',$html,$matches,PREG_SET_ORDER)){
      foreach($matches as $m){
        if(count($actions)>=160)break;
        $tag=strtolower((string)($m[1]?:$m[4]?:''));
        $attrs=(string)($m[2]?:$m[5]?:'');
        $inner=(string)($m[3]??'');
        $attr=function(string $name)use($attrs): string {
          if(preg_match('~\b'.preg_quote($name,'~').'\s*=\s*(["\'])(.*?)\1~is',$attrs,$mm))return html_entity_decode($mm[2],ENT_QUOTES|ENT_HTML5,'UTF-8');
          if(preg_match('~\b'.preg_quote($name,'~').'\s*=\s*([^\s>]+)~is',$attrs,$mm))return trim($mm[1],"'\"");
          return '';
        };
        $type=strtolower(trim($attr('type')));if($tag==='input'&&$type==='hidden')continue;
        $role=strtolower(trim($attr('role')));$id=trim($attr('id'));$name=trim($attr('name'));$ordinal++;
        $event='click';
        if($tag==='form')$event='submit';
        elseif($tag==='a')$event='navigate';
        elseif(in_array($tag,['select','textarea'],true))$event='change';
        elseif($tag==='input'&&!in_array($type,['button','submit','reset','image'],true))$event='change';
        $label=trim($attr('aria-label'));if($label==='')$label=trim($attr('title'));
        if($label===''&&in_array($tag,['button','a'],true))$label=trim(strip_tags($inner));
        if($label===''&&$tag==='input'&&in_array($type,['button','submit','reset'],true))$label=trim($attr('value'));
        if($label==='')$label=$id?:($name?:ucfirst($tag));
        $label=loom_html_framer_reader_clean($label,ucfirst($tag));
        $handler='';
        foreach(['click','submit','change'] as $ev){
          $raw=trim($attr('on'.$ev));
          if($raw!==''&&preg_match('/([A-Za-z_$][A-Za-z0-9_$]*)\s*\(/',$raw,$hm)){$handler=$hm[1];$functions[$handler]=true;break;}
        }
        $seed=implode('|',[$event,$tag,$id,$name,$type,$role,$label,(string)$ordinal]);
        $key=$event.'.'.loom_html_framer_reader_slug($label).'.'.substr(hash('sha256',$seed),0,8);
        $verb=['click'=>'Click','navigate'=>'Open','submit'=>'Submit','change'=>'Change'][$event]??'Use';
        $actions[]=['key'=>$key,'event'=>$event,'name'=>$verb.' '.$label,'description'=>'Auto-discovered framed HTML interaction.','match'=>['tag'=>$tag,'id'=>$id?:null,'name'=>$name?:null,'type'=>$type?:null,'role'=>$role?:null,'ordinal'=>$ordinal],'handler'=>$handler?:null];
      }
    }
    $warnings[]='PHP DOMDocument is unavailable; Action Reader used its compatibility HTML scanner.';
  }

  return [
    'schema'=>'loom-framed-action-reader/v1',
    'trackableActions'=>$actions,
    'functionCandidates'=>array_keys($functions),
    'listenerEvents'=>[],
    'warnings'=>$warnings,
    'trackableCount'=>count($actions)
  ];
}
function loom_html_framer_action_reader_scan_js(array $paths,array $inventory,string $zipPath,array $base): array {
  $functions=array_fill_keys(array_values($base['functionCandidates']??[]),true);
  $listeners=[];
  foreach($paths as $path){
    if(!loom_html_framer_is_js($path)||!isset($inventory[$path]))continue;
    $raw=loom_html_framer_zip_read($zipPath,$inventory[$path]);
    if(!loom_html_framer_text_ok($raw))continue;
    if(preg_match_all('/\bfunction\s+([A-Za-z_$][A-Za-z0-9_$]*)\s*\(/',$raw,$m))
      foreach($m[1] as $name)if(count($functions)<160)$functions[$name]=true;
    if(preg_match_all('/\b(?:const|let|var)\s+([A-Za-z_$][A-Za-z0-9_$]*)\s*=\s*(?:async\s*)?(?:function\b|\([^)]*\)\s*=>|[A-Za-z_$][A-Za-z0-9_$]*\s*=>)/',$raw,$m))
      foreach($m[1] as $name)if(count($functions)<160)$functions[$name]=true;
    if(preg_match_all('/addEventListener\s*\(\s*["\']([A-Za-z0-9:_-]+)["\']/',$raw,$m))
      foreach($m[1] as $event)$listeners[$event]=($listeners[$event]??0)+1;
  }
  ksort($listeners);
  $base['functionCandidates']=array_slice(array_keys($functions),0,160);
  $base['listenerEvents']=$listeners;
  $base['functionCandidateCount']=count($base['functionCandidates']);
  $base['listenerEventCount']=array_sum($listeners);
  return $base;
}
function loom_html_framer_action_reader_enabled(string $project,array $frame): bool {
  if(($frame['actionReaderEnabled']??true)===false)return false;
  $manager=read_json_file(root_dir().'/core-modules/html-framer/manifest.json')?:[];
  $cfg=loom_module_config_with_admin_overrides($project,$manager);
  return ($cfg['actionReaderEnabled']??true)!==false;
}
function loom_html_framer_action_reader_user_actions(array $frame): array {
  $id=(string)($frame['id']??'');if($id==='')return [];
  $reader=is_array($frame['actionReader']??null)?$frame['actionReader']:[];
  $out=[];
  foreach(($reader['trackableActions']??[]) as $a){
    if(!is_array($a)||empty($a['key']))continue;
    $out[]=[
      'id'=>'html.frame.'.$id.'.ui.'.preg_replace('/[^a-zA-Z0-9._-]+/','-',(string)$a['key']),
      'name'=>(string)($a['name']??'Framed interaction'),
      'description'=>(string)($a['description']??'Auto-discovered framed HTML interaction.'),
      'behavior'=>'transient','events'=>[]
    ];
  }
  foreach(['click'=>'Dynamic click','navigate'=>'Dynamic navigation','submit'=>'Dynamic form submit','change'=>'Dynamic field change'] as $event=>$name)
    $out[]=['id'=>'html.frame.'.$id.'.dynamic.'.$event,'name'=>$name,'description'=>'Runtime-observed framed HTML interaction not present in the import-time static catalog.','behavior'=>'transient','events'=>[]];
  return $out;
}
function loom_html_framer_action_reader_client_config(array $frame): array {
  $id=(string)($frame['id']??'');$reader=is_array($frame['actionReader']??null)?$frame['actionReader']:[];
  $catalog=[];
  foreach(($reader['trackableActions']??[]) as $a){
    if(!is_array($a)||empty($a['key']))continue;
    $catalog[]=[
      'actionId'=>'html.frame.'.$id.'.ui.'.preg_replace('/[^a-zA-Z0-9._-]+/','-',(string)$a['key']),
      'event'=>(string)($a['event']??'click'),'name'=>(string)($a['name']??'Framed interaction'),
      'match'=>is_array($a['match']??null)?$a['match']:[]
    ];
  }
  $generic=[];foreach(['click','navigate','submit','change'] as $e)$generic[$e]='html.frame.'.$id.'.dynamic.'.$e;
  return ['schema'=>'loom-framed-action-reader/v1','frameId'=>$id,'catalog'=>$catalog,'generic'=>$generic];
}
function loom_html_framer_layout_bridge_script(array $frame): string {
  $id=json_encode((string)($frame['id']??''),JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
  if($id===false)$id='""';
  $desktopAuto=strtolower((string)($frame['heightMode']??'auto'))!=='fixed';
  $mobileAuto=strtolower((string)($frame['mobileHeightMode']??($frame['heightMode']??'auto')))!=='fixed';
  $cfg=json_encode(['desktopAuto'=>$desktopAuto,'mobileAuto'=>$mobileAuto,'breakpoint'=>760],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
  if($cfg===false)$cfg='{"desktopAuto":true,"mobileAuto":true,"breakpoint":760}';
  return '<script data-loom-framed-layout="4">(function(){' .
    'const ID='.$id.',C='.$cfg.';let lastH=0,lastKind="",raf=0,timer=0,ro=null,mq=null,started=false,lastVW=innerWidth,lastVH=innerHeight,suppressUntil=0;' .
    'function isAuto(){return (mq&&mq.matches)?C.mobileAuto:C.desktopAuto}' .
    'function safeStyle(el){try{return getComputedStyle(el)}catch{return null}}' .
    'function viewportApp(){if(!isAuto())return false;const de=document.documentElement,b=document.body;if(!de||!b)return false;const ds=safeStyle(de),bs=safeStyle(b);if(!ds||!bs)return false;const locked=/(hidden|clip)/.test(bs.overflowY)||/(hidden|clip)/.test(ds.overflowY);if(!locked)return false;const br=b.getBoundingClientRect(),dr=de.getBoundingClientRect(),near=Math.abs(br.height-innerHeight)<=8||Math.abs(dr.height-innerHeight)<=8;if(!near)return false;const sh=Math.max(de.scrollHeight||0,b.scrollHeight||0);if(sh>innerHeight+18)return false;let fill=false,n=0;for(const el of b.children){if(++n>24)break;if(/^(SCRIPT|STYLE|LINK)$/i.test(el.tagName||""))continue;const cs=safeStyle(el);if(!cs||cs.position==="fixed")continue;const r=el.getBoundingClientRect();if(r.height>=innerHeight*.72&&r.height<=innerHeight*1.28){fill=true;break}}return fill||b.children.length>0}' .
    'function naturalHeight(){const de=document.documentElement,b=document.body;let h=Math.max(de?.scrollHeight||0,de?.offsetHeight||0,b?.scrollHeight||0,b?.offsetHeight||0);if(b){let n=0;for(const el of b.children){if(++n>400)break;const cs=safeStyle(el);if(!cs||cs.position==="fixed")continue;const r=el.getBoundingClientRect();h=Math.max(h,Math.ceil(r.bottom+(scrollY||0)))}}return Math.max(1,Math.ceil(h))}' .
    'function measure(){raf=0;if(!isAuto())return;const kind=viewportApp()?"viewport":"document",h=kind==="viewport"?Math.max(1,Math.round(innerHeight)):naturalHeight();if(kind===lastKind&&Math.abs(h-lastH)<4)return;lastKind=kind;lastH=h;parent.postMessage({__loomFramedLayout:"v4",frameId:ID,height:h,layoutKind:kind,viewportWidth:innerWidth,viewportHeight:innerHeight,auto:true},"*")}' .
    'function schedule(delay=24){clearTimeout(timer);timer=setTimeout(()=>{timer=0;if(!raf)raf=requestAnimationFrame(measure)},delay)}' .
    'function viewportResize(){const w=innerWidth,h=innerHeight,dw=Math.abs(w-lastVW),dh=Math.abs(h-lastVH);lastVW=w;lastVH=h;if(dw>1){suppressUntil=0;lastH=0;lastKind="";schedule(44);return}if(dh>1){suppressUntil=performance.now()+260;return}if(performance.now()>=suppressUntil)schedule(44)}' .
    'function start(){if(started)return;started=true;mq=matchMedia("(max-width:"+C.breakpoint+"px)");const profileChange=()=>{lastH=0;lastKind="";schedule(40)};try{mq.addEventListener("change",profileChange)}catch{mq.addListener&&mq.addListener(profileChange)}try{ro=new ResizeObserver(()=>{const w=innerWidth,h=innerHeight,dw=Math.abs(w-lastVW),dh=Math.abs(h-lastVH);if(dw<=1&&dh>1){lastVH=h;suppressUntil=performance.now()+260;return}lastVW=w;lastVH=h;if(performance.now()<suppressUntil)return;schedule(42)});if(document.documentElement)ro.observe(document.documentElement);if(document.body)ro.observe(document.body)}catch{}if(document.fonts&&document.fonts.ready)document.fonts.ready.then(()=>schedule(20)).catch(()=>{});[0,90,280,800].forEach(t=>setTimeout(()=>schedule(0),t))}' .
    'addEventListener("message",e=>{const m=e.data;if(!m||typeof m!=="object"||m.__loomFramedLayoutRequest!=="measure"||String(m.frameId||"")!==String(ID))return;suppressUntil=0;lastH=0;lastKind="";schedule(20)});' .
    'addEventListener("load",()=>schedule(20));addEventListener("resize",viewportResize,{passive:true});addEventListener("transitionend",()=>schedule(40),true);addEventListener("animationend",()=>schedule(40),true);addEventListener("input",()=>schedule(40),true);addEventListener("change",()=>schedule(40),true);' .
    'addEventListener("keydown",e=>{if(e.key==="Escape")parent.postMessage({__loomFramedFullscreen:"exit",frameId:ID},"*")},true);' .
    'if(document.readyState==="loading")addEventListener("DOMContentLoaded",start,{once:true});else start();' .
    '})();</script>';
}
function loom_html_framer_action_reader_script(array $frame): string {
  $cfg=loom_html_framer_action_reader_client_config($frame);
  $json=json_encode($cfg,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
  if($json===false)$json='{}';
  return '<script data-loom-framed-action-reader="1">(function(){' .
    'const C='.$json.';const SEL="button,a[href],form,input:not([type=hidden]),select,textarea,[role=button]";' .
    'const clean=v=>String(v||"").replace(/\\s+/g," ").trim().slice(0,80);' .
    'function meta(el){if(!el||el.nodeType!==1)return null;const tag=el.tagName.toLowerCase(),type=String(el.getAttribute("type")||"").toLowerCase();return{tag,id:el.id||null,name:el.getAttribute("name")||null,type:type||null,role:el.getAttribute("role")||null,ordinal:[...document.querySelectorAll(SEL)].indexOf(el)+1}}' .
    'function label(el){if(!el)return"Control";let v=el.getAttribute("aria-label")||el.getAttribute("title")||"";if(!v&&(el.matches("button,a[href]")))v=el.textContent||"";if(!v&&el.id)v=el.id;if(!v&&el.getAttribute("name"))v=el.getAttribute("name");return clean(v)||el.tagName.toLowerCase()}' .
    'function pick(type,el){const m=meta(el)||{};let best=null,score=-1;for(const a of C.catalog||[]){if(a.event!==type)continue;const x=a.match||{};let s=0;if(x.id&&m.id===x.id)s+=100;else if(x.id)continue;if(x.name&&m.name===x.name)s+=40;if(x.tag&&m.tag===x.tag)s+=10;if(x.type&&m.type===x.type)s+=8;if(x.role&&m.role===x.role)s+=6;if(x.ordinal&&m.ordinal===Number(x.ordinal))s+=2;if(s>score){score=s;best=a}}return best||{actionId:(C.generic||{})[type]||"",name:"Framed "+type}}' .
    'function hrefInfo(el){try{const raw=el&&el.href?new URL(el.href,location.href):null;if(!raw)return null;return{host:raw.host,path:raw.pathname.slice(0,180),external:raw.origin!==location.origin}}catch{return null}}' .
    'function emit(type,el,extra){const a=pick(type,el),m=meta(el);if(!a.actionId)return;parent.postMessage({__loomFramedAction:"v1",frameId:C.frameId,actionId:a.actionId,eventType:type,actionName:a.name,label:label(el),target:m,href:type==="navigate"?hrefInfo(el):null,...(extra||{})},"*")}' .
    'addEventListener("click",e=>{const el=e.target&&e.target.closest?e.target.closest("button,a[href],[role=button],input[type=button],input[type=submit],input[type=reset],input[type=image]"):null;if(!el)return;emit(el.matches("a[href]")?"navigate":"click",el,{x:Math.round(e.clientX||0),y:Math.round(e.clientY||0),button:Number(e.button||0)})},true);' .
    'addEventListener("submit",e=>{if(e.target&&e.target.matches&&e.target.matches("form"))emit("submit",e.target,{method:String(e.target.method||"get").toUpperCase()})},true);' .
    'addEventListener("change",e=>{const el=e.target;if(!el||!el.matches||!el.matches("input,select,textarea"))return;const x={};if(el.matches("input[type=checkbox],input[type=radio]"))x.checked=!!el.checked;if(el.matches("select"))x.selectedIndex=el.selectedIndex;if(el.matches("input[type=file]"))x.fileCount=el.files?el.files.length:0;emit("change",el,x)},true);' .
    'parent.postMessage({__loomFramedAction:"v1",frameId:C.frameId,eventType:"reader-ready",catalogCount:(C.catalog||[]).length},"*");' .
    '})();</script>';
}

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
  $fatal=[];$actionReader=['schema'=>'loom-framed-action-reader/v1','trackableActions'=>[],'functionCandidates'=>[],'listenerEvents'=>[],'warnings'=>[],'trackableCount'=>0];
  if($entry){
    $entryData=loom_html_framer_zip_read($zipPath,$inv['files'][$entry]);
    if(trim($entryData)===''||!preg_match('~<\s*[a-zA-Z][^>]*>~',$entryData))$fatal[]='Selected HTML entrypoint does not contain recognizable HTML markup.';
    else $actionReader=loom_html_framer_action_reader_scan_js($js,$inv['files'],$zipPath,loom_html_framer_action_reader_scan_html($entryData));
  }
  return [
    'entrypoint'=>$entry,'entrypointReason'=>$pick['reason'],'needsEntrypoint'=>(bool)$pick['ambiguous'],
    'htmlCandidates'=>$inv['html'],'fileCount'=>count($paths),'totalBytes'=>$inv['totalBytes'],
    'cssCount'=>count($css),'jsCount'=>count($js),'autoAttachCss'=>$autoCss,'autoAttachJs'=>$autoJs,
    'relationships'=>$relationships,'repairs'=>$repairs,'missingRefs'=>$missing,
    'warnings'=>array_values(array_unique(array_merge($inv['warnings'],$syntaxWarnings,$actionReader['warnings']??[]))),
    'actionReader'=>$actionReader,
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
function loom_html_framer_import(string $project,string $zipPath,string $zipName,?string $entrypoint,int $defaultHeight=520,int $defaultWidthPercent=100,?string $replaceId=null,?array $presentationDefaults=null): array {
  $presentationDefaults=is_array($presentationDefaults)?$presentationDefaults:[];
  $normalizeMode=static fn($v)=>strtolower((string)$v)==='fixed'?'fixed':'auto';
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
      'entrypoint'=>$analysis['entrypoint'],
      'heightMode'=>$normalizeMode($existing['heightMode']??$presentationDefaults['heightMode']??'auto'),
      'height'=>max(200,min(2400,(int)($existing['height']??$presentationDefaults['height']??$defaultHeight))),
      'widthPercent'=>max(50,min(100,(int)($existing['widthPercent']??$presentationDefaults['widthPercent']??$defaultWidthPercent))),
      'mobileHeightMode'=>$normalizeMode($existing['mobileHeightMode']??$presentationDefaults['mobileHeightMode']??'auto'),
      'mobileHeight'=>max(200,min(2400,(int)($existing['mobileHeight']??$presentationDefaults['mobileHeight']??$defaultHeight))),
      'mobileWidthPercent'=>max(50,min(100,(int)($existing['mobileWidthPercent']??$presentationDefaults['mobileWidthPercent']??$defaultWidthPercent))),
      'fullscreenEnabled'=>array_key_exists('fullscreenEnabled',$existing)?(bool)$existing['fullscreenEnabled']:(bool)($presentationDefaults['fullscreenEnabled']??true),
      'order'=>$order,'revision'=>$revision,'zipName'=>basename($zipName),'fileCount'=>$analysis['fileCount'],
      'cssCount'=>$analysis['cssCount'],'jsCount'=>$analysis['jsCount'],'autoAttachCss'=>$analysis['autoAttachCss'],
      'autoAttachJs'=>$analysis['autoAttachJs'],'repairs'=>$analysis['repairs'],'missingRefs'=>$analysis['missingRefs'],
      'warnings'=>$analysis['warnings'],'actionReader'=>$analysis['actionReader']??[],'actionReaderEnabled'=>array_key_exists('actionReaderEnabled',$existing)?(bool)$existing['actionReaderEnabled']:true,
      'createdAt'=>$existing['createdAt']??server_timestamp(),'updatedAt'=>server_timestamp()
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
    // Imported projects may contain an older LOOM-injected bridge from a historical export.
    // Strip those implementation details at serve time so exactly one current bridge owns layout/action telemetry.
    $data=preg_replace('~<script\b[^>]*\bdata-loom-framed-(?:layout|action-reader)\s*=\s*([\"\']).*?\1[^>]*>.*?</script>~is','',$data)??$data;
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
    $bridges=loom_html_framer_layout_bridge_script($frame);
    if(loom_html_framer_action_reader_enabled($project,$frame))$bridges.=loom_html_framer_action_reader_script($frame);
    if(stripos($data,'</body>')!==false)$data=preg_replace('~</body>~i',$bridges.'</body>',$data,1)??$data;
    else $data.=$bridges;
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
  $registry=loom_html_framer_registry($project);$autoFullscreenFrameId=(string)($registry['autoFullscreenFrameId']??'');
  $cfg=loom_module_config_with_admin_overrides($project,$manager);
  if(($cfg['enabled']??true)===false)return [];
  $entry=root_dir().'/core-modules/html-framer/frame-action.js';
  if(!is_file($entry))return [];
  $out=[];
  foreach(loom_html_framer_public_frames($project) as $frame){
    if(($frame['enabled']??true)===false)continue;
    $id=(string)$frame['id'];$order=max(1,(int)($frame['order']??60000));$revision=max(1,(int)($frame['revision']??1));
    $actionId='html.frame.'.$id;$readerEnabled=loom_html_framer_action_reader_enabled($project,$frame);$readerActions=$readerEnabled?loom_html_framer_action_reader_user_actions($frame):[];
    $src=loom_html_framer_endpoint_url($project,$id,(string)$frame['entrypoint'],$clientId).'&r='.$revision;
    $finger=substr(hash('sha256',implode('|',[$project,$id,(string)$revision,hash_file('sha1',$entry)?:'',json_encode($frame)])),0,16);
    $out[]=[
      'schema_version'=>'1.8','enabled'=>true,
      'action'=>[
        'id'=>$actionId,'name'=>(string)($frame['title']??'HTML Frame'),'description'=>'Sandboxed HTML Framer package: '.(string)($frame['zipName']??''),
        'kind'=>'system','behavior'=>'stateful','autostart'=>true,'parent'=>'loom.html-framer','category'=>'project/html-frame',
        'tags'=>['html-framer','html','sandbox','interop'],'steps'=>[['id'=>'mount-frame','name'=>'Mount sandboxed HTML frame']]
      ],
      'user_actions'=>$readerActions,
      'module'=>['entry'=>'frame-action.js','version'=>'1.9.0','dependencies'=>[],'styles'=>[],'order'=>(string)$order],
      'config'=>[
        'frameId'=>$id,'src'=>$src,
        'heightMode'=>strtolower((string)($frame['heightMode']??'auto'))==='fixed'?'fixed':'auto',
        'height'=>max(200,min(2400,(int)($frame['height']??520))),
        'widthPercent'=>max(50,min(100,(int)($frame['widthPercent']??100))),
        'mobileHeightMode'=>strtolower((string)($frame['mobileHeightMode']??'auto'))==='fixed'?'fixed':'auto',
        'mobileHeight'=>max(200,min(2400,(int)($frame['mobileHeight']??($frame['height']??520)))),
        'mobileWidthPercent'=>max(50,min(100,(int)($frame['mobileWidthPercent']??($frame['widthPercent']??100)))),
        'fullscreenEnabled'=>array_key_exists('fullscreenEnabled',$frame)?(bool)$frame['fullscreenEnabled']:true,
        'autoFullscreenOnLoad'=>$autoFullscreenFrameId!==''&&hash_equals($autoFullscreenFrameId,$id),
        'entrypoint'=>(string)$frame['entrypoint'],'tracking'=>$readerEnabled?'action-reader-v1':'boundary-only',
        'actionReaderEnabled'=>$readerEnabled,'actionReaderSummary'=>[
          'declaredActionCount'=>count($readerActions),
          'trackableCount'=>(int)($frame['actionReader']['trackableCount']??0),
          'functionCandidateCount'=>(int)($frame['actionReader']['functionCandidateCount']??0),
          'listenerEventCount'=>(int)($frame['actionReader']['listenerEventCount']??0)
        ]
      ],
      'admin_overrides'=>new stdClass(),'admin_settings'=>['fields'=>[]],
      'extensions'=>new stdClass(),'capabilities'=>['provides'=>[],'requires'=>[],'permissions'=>[]],
      'presentation'=>loom_apply_project_module_presentation($project,$actionId,[
        'role'=>'content','collapsible'=>true,'mount'=>['region'=>'root'],
        'layout'=>['width'=>'full','widthScope'=>'page','align'=>'center','position'=>'flow','order'=>$order,'className'=>'loom-html-framer-runtime-module']
      ]),
      'pegboard'=>['preferredDepth'=>2,'accent'=>'blue'],
      'order_effective'=>$order,'order_display'=>str_pad((string)$order,5,'0',STR_PAD_LEFT),'order_locked'=>false,
      'bootstrap'=>new stdClass(),'entry_url'=>rel_url($entry),'styles'=>[],'manifest_url'=>rel_url($managerFile),
      'fingerprint'=>$finger,'folder'=>'instance/projects/'.$project.'/html-framer/'.$id,'source'=>'html-framer'
    ];
  }
  return $out;
}

// ---- URL Snapshot Import (v0.15.18) ---------------------------------------
// Captures a bounded, same-origin static snapshot into the existing HTML Framer
// package pipeline. This is intentionally NOT a live remote iframe.
function loom_html_framer_public_ip(string $ip): bool {
  if(filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)===false)return false;
  return !in_array($ip,['0.0.0.0','127.0.0.1','::1'],true);
}
function loom_html_framer_validate_remote_url(string $url): array {
  $url=trim($url);$p=parse_url($url);if(!$p||!in_array(strtolower((string)($p['scheme']??'')),['http','https'],true))throw new RuntimeException('Capture URL must use http:// or https://.');
  $host=strtolower(rtrim((string)($p['host']??''),'.'));if($host===''||$host==='localhost'||str_ends_with($host,'.local'))throw new RuntimeException('Local/private hosts cannot be captured.');
  if(filter_var($host,FILTER_VALIDATE_IP)){$ips=[$host];}
  else{
    $ips=[];
    if(function_exists('dns_get_record')){foreach((array)@dns_get_record($host,DNS_A|DNS_AAAA) as $r){$ip=(string)($r['ip']??$r['ipv6']??'');if($ip!=='')$ips[]=$ip;}}
    if(!$ips){foreach((array)@gethostbynamel($host) as $ip)$ips[]=$ip;}
  }
  if(!$ips)throw new RuntimeException('Capture host could not be resolved.');
  foreach(array_unique($ips) as $ip)if(!loom_html_framer_public_ip($ip))throw new RuntimeException('Private/reserved network targets cannot be captured.');
  $normalized=$url;if(!isset($p['path'])||$p['path']==='')$normalized.='/';
  return ['url'=>$normalized,'host'=>$host,'scheme'=>strtolower((string)$p['scheme']),'port'=>(int)($p['port']??0)];
}
function loom_html_framer_absolute_url(string $base,string $ref): ?string {
  $ref=trim(html_entity_decode($ref,ENT_QUOTES|ENT_HTML5,'UTF-8'));if($ref===''||str_starts_with($ref,'#')||preg_match('~^(?:data|blob|mailto|tel|javascript):~i',$ref))return null;
  if(preg_match('~^https?://~i',$ref))return $ref;
  $b=parse_url($base);if(!$b||empty($b['host']))return null;$scheme=$b['scheme']??'https';$authority=$scheme.'://'.$b['host'].(isset($b['port'])?':'.$b['port']:'');
  if(str_starts_with($ref,'//'))return $scheme.':'.$ref;
  // Resolve only the path component. Query/fragment text must never become part
  // of the filesystem-style path normalization (for example style.css?v=2).
  $rp=parse_url($ref);if($rp===false)return null;$refPath=(string)($rp['path']??'');
  if($refPath==='')$path=(string)($b['path']??'/');
  elseif(str_starts_with($refPath,'/'))$path=$refPath;
  else{$dir=preg_replace('~/[^/]*$~','/',(string)($b['path']??'/'));$path=$dir.$refPath;}
  $parts=[];foreach(explode('/',$path) as $part){if($part===''||$part==='.')continue;if($part==='..'){array_pop($parts);continue;}$parts[]=$part;}
  $full=$authority.'/'.implode('/',$parts);if(isset($rp['query'])&&$rp['query']!=='')$full.='?'.$rp['query'];return $full;
}
function loom_html_framer_fetch_remote(string $url,int $maxBytes=8388608,int $redirects=3): array {
  $check=loom_html_framer_validate_remote_url($url);$url=$check['url'];
  if(!function_exists('curl_init'))throw new RuntimeException('URL capture requires the PHP cURL extension on this server.');
  for($hop=0;$hop<=$redirects;$hop++){
    $ch=curl_init($url);$headers=[];$body='';$tooLarge=false;
    curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>false,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_CONNECTTIMEOUT=>6,CURLOPT_TIMEOUT=>18,CURLOPT_USERAGENT=>'LOOM-HTML-Framer-Snapshot/0.15.18',CURLOPT_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_REDIR_PROTOCOLS=>CURLPROTO_HTTP|CURLPROTO_HTTPS,CURLOPT_ENCODING=>'',CURLOPT_HEADERFUNCTION=>function($ch,$line)use(&$headers){$len=strlen($line);$line=trim($line);if($line!==''&&str_contains($line,':')){[$k,$v]=array_map('trim',explode(':',$line,2));$headers[strtolower($k)]=$v;}return $len;},CURLOPT_WRITEFUNCTION=>function($ch,$chunk)use(&$body,&$tooLarge,$maxBytes){if(strlen($body)+strlen($chunk)>$maxBytes){$tooLarge=true;return 0;}$body.=$chunk;return strlen($chunk);}]);
    $ok=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_RESPONSE_CODE);$type=(string)curl_getinfo($ch,CURLINFO_CONTENT_TYPE);$err=curl_error($ch);curl_close($ch);
    if($tooLarge)throw new RuntimeException('Remote resource exceeded the URL snapshot size limit.');
    if($ok===false&&$body==='')throw new RuntimeException('Remote capture failed: '.($err?:'network error'));
    if(in_array($code,[301,302,303,307,308],true)&&isset($headers['location'])){$next=loom_html_framer_absolute_url($url,$headers['location']);if(!$next)throw new RuntimeException('Remote redirect could not be resolved.');loom_html_framer_validate_remote_url($next);$url=$next;continue;}
    if($code<200||$code>=300)throw new RuntimeException('Remote server returned HTTP '.$code.'.');
    return ['url'=>$url,'body'=>$body,'contentType'=>$type,'headers'=>$headers,'status'=>$code];
  }
  throw new RuntimeException('Remote capture exceeded the redirect limit.');
}
function loom_html_framer_snapshot_asset_name(string $url,string $contentType=''): string {
  $path=(string)(parse_url($url,PHP_URL_PATH)??'');$ext=strtolower(pathinfo($path,PATHINFO_EXTENSION));
  $allowed=['css','js','mjs','json','png','jpg','jpeg','gif','webp','svg','ico','woff','woff2','ttf','otf','mp4','webm','mp3','wav'];
  if(!in_array($ext,$allowed,true)){
    $map=['text/css'=>'css','application/javascript'=>'js','text/javascript'=>'js','image/png'=>'png','image/jpeg'=>'jpg','image/svg+xml'=>'svg','image/webp'=>'webp','font/woff2'=>'woff2'];$base=strtolower(trim(explode(';',$contentType)[0]??''));$ext=$map[$base]??'bin';
  }
  return '_snapshot/'.substr(hash('sha256',$url),0,24).'.'.$ext;
}
function loom_html_framer_capture_url(string $project,string $url,int $defaultHeight=520,int $defaultWidthPercent=100,?array $presentationDefaults=null): array {
  if(!loom_html_framer_zip_supported())throw new RuntimeException('URL capture requires ZipArchive because snapshots enter LOOM through the same validated package pipeline as ZIP imports.');
  $main=loom_html_framer_fetch_remote($url,8*1024*1024,3);$final=$main['url'];
  if(!preg_match('~text/html|application/xhtml\+xml~i',$main['contentType'])&&!preg_match('~<html\b|<!doctype\s+html~i',$main['body']))throw new RuntimeException('Capture URL did not return an HTML document.');
  $origin=parse_url($final);$originKey=strtolower(($origin['scheme']??'').':'.($origin['host']??'').':'.($origin['port']??(($origin['scheme']??'')==='https'?443:80)));
  $tmpBase=loom_html_framer_root($project).'/.capture-'.bin2hex(random_bytes(5));$files=$tmpBase.'/files';ensure_dir($files.'/_snapshot');$html=$main['body'];$saved=[];$total=strlen($html);$maxTotal=30*1024*1024;$maxAssets=80;
  if(class_exists('DOMDocument')){
    $dom=new DOMDocument();$prev=libxml_use_internal_errors(true);@$dom->loadHTML($html,LIBXML_HTML_NOIMPLIED|LIBXML_HTML_NODEFDTD);libxml_clear_errors();libxml_use_internal_errors($prev);
    $targets=[['img','src'],['script','src'],['link','href'],['source','src'],['video','poster'],['audio','src']];
    foreach($targets as [$tag,$attr])foreach(iterator_to_array($dom->getElementsByTagName($tag)) as $el){
      if(count($saved)>=$maxAssets)break 2;$raw=$el->getAttribute($attr);$abs=loom_html_framer_absolute_url($final,$raw);if(!$abs)continue;$u=parse_url($abs);if(!$u||!in_array(strtolower((string)($u['scheme']??'')),['http','https'],true))continue;$key=strtolower(($u['scheme']??'').':'.($u['host']??'').':'.($u['port']??(($u['scheme']??'')==='https'?443:80)));if($key!==$originKey)continue;
      try{$res=loom_html_framer_fetch_remote($abs,6*1024*1024,2);}catch(Throwable $e){continue;}$bytes=$res['body'];if($total+strlen($bytes)>$maxTotal)break 2;$name=loom_html_framer_snapshot_asset_name($res['url'],$res['contentType']);
      if(str_ends_with($name,'.css')){$base=$res['url'];$bytes=preg_replace_callback('~url\(\s*(["\']?)(.*?)\1\s*\)~i',function($m)use($base){$a=loom_html_framer_absolute_url($base,$m[2]);return $a?'url("'.$a.'")':$m[0];},$bytes)??$bytes;}
      @file_put_contents($files.'/'.$name,$bytes,LOCK_EX);$saved[$abs]=$name;$total+=strlen($bytes);$el->setAttribute($attr,$name);
    }
    $html=$dom->saveHTML()?:$html;
  }
  @file_put_contents($files.'/index.html',$html,LOCK_EX);
  $zipPath=$tmpBase.'/snapshot.zip';$zip=new ZipArchive();if($zip->open($zipPath,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true){loom_html_framer_remove_tree($tmpBase);throw new RuntimeException('Could not create snapshot package.');}
  $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($files,FilesystemIterator::SKIP_DOTS));foreach($it as $f)if($f->isFile())$zip->addFile($f->getPathname(),str_replace('\\','/',substr($f->getPathname(),strlen($files)+1)));$zip->close();
  try{$result=loom_html_framer_import($project,$zipPath,'URL Snapshot · '.parse_url($final,PHP_URL_HOST), 'index.html',$defaultHeight,$defaultWidthPercent,null,$presentationDefaults);if(!empty($result['frame']['id'])){$id=$result['frame']['id'];$reg=loom_html_framer_registry($project);if(isset($reg['frames'][$id])){$reg['frames'][$id]['sourceUrl']=$final;$reg['frames'][$id]['captureMode']='url-snapshot';$reg['frames'][$id]['capturedAt']=server_timestamp();$reg['frames'][$id]['snapshotAssetCount']=count($saved);loom_html_framer_write_registry($project,$reg);$result['frame']=$reg['frames'][$id];}}$result['sourceUrl']=$final;$result['captureMode']='url-snapshot';return $result;}finally{loom_html_framer_remove_tree($tmpBase);}
}
