<?php
// @loom-file release=0.15.40 revision=10 policy=package-priority
// Admin API for LOOM HTML Framer.
declare(strict_types=1);
require __DIR__.'/_common.php';
require __DIR__.'/_html_framer.php';

$method=$_SERVER['REQUEST_METHOD']??'GET';
$rawBody=(string)file_get_contents('php://input');
$jsonBody=json_decode($rawBody,true);if(!is_array($jsonBody))$jsonBody=[];
$action=(string)($_REQUEST['action']??$jsonBody['action']??'list');
$clientId=safe_token((string)($_REQUEST['clientId']??$jsonBody['clientId']??''));
$project=safe_slug((string)($_REQUEST['project']??$jsonBody['project']??''));
if(!$project||!project_dir($project))json_out(['ok'=>false,'error'=>'project-not-found'],404);
if($method==='GET'||$action==='list')loom_require_project_capability($clientId,$project,'project.view');
else loom_require_project_capability($clientId,$project,'html-framer.manage');

function html_framer_payload(string $project): array {
  return ['ok'=>true,'project'=>$project,'frames'=>loom_html_framer_public_frames($project),'zipAvailable'=>loom_html_framer_zip_supported()];
}
function html_framer_presentation_defaults(string $project): array {
  $settings=loom_read_admin_settings($project);$cfg=$settings['modules']['loom.html-framer']??[];
  $mode=static fn($v)=>strtolower((string)$v)==='fixed'?'fixed':'auto';
  return [
    'heightMode'=>$mode($cfg['defaultHeightMode']??'auto'),
    'height'=>max(200,min(2400,(int)($cfg['defaultFrameHeight']??520))),
    'widthPercent'=>max(50,min(100,(int)($cfg['defaultFrameWidthPercent']??100))),
    'mobileHeightMode'=>$mode($cfg['defaultMobileHeightMode']??'auto'),
    'mobileHeight'=>max(200,min(2400,(int)($cfg['defaultMobileFrameHeight']??($cfg['defaultFrameHeight']??520)))),
    'mobileWidthPercent'=>max(50,min(100,(int)($cfg['defaultMobileFrameWidthPercent']??($cfg['defaultFrameWidthPercent']??100)))),
    'fullscreenEnabled'=>array_key_exists('defaultFullscreenEnabled',$cfg)?(bool)$cfg['defaultFullscreenEnabled']:true
  ];
}
if($method==='GET'||$action==='list')json_out(html_framer_payload($project));

if($action==='capture-url'){
  $url=trim((string)($jsonBody['url']??''));if($url==='')json_out(['ok'=>false,'error'=>'capture-url-required'],400);
  try{
    $defaults=html_framer_presentation_defaults($project);
    $result=loom_html_framer_capture_url($project,$url,$defaults['height'],$defaults['widthPercent'],$defaults);
    json_out(html_framer_payload($project)+['capture'=>$result,'message'=>'Static URL snapshot imported. This copy does not stay synchronized with the production website.']);
  }catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
}

if($action==='analyze'||$action==='upload'){
  $file=$_FILES['zip']??null;
  if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)json_out(['ok'=>false,'error'=>'zip-upload-required'],400);
  $name=(string)($file['name']??'upload.zip');
  if(strtolower(pathinfo($name,PATHINFO_EXTENSION))!=='zip')json_out(['ok'=>false,'error'=>'HTML Framer accepts ZIP files only.'],400);
  $tmp=(string)($file['tmp_name']??'');
  try{
    $entry=isset($_POST['entrypoint'])&&$_POST['entrypoint']!==''?(string)$_POST['entrypoint']:null;
    if($action==='analyze'){
      $analysis=loom_html_framer_analyze_zip($tmp,$entry);
      json_out(['ok'=>true,'analysis'=>$analysis]);
    }
    $defaults=html_framer_presentation_defaults($project);
    $replaceId=isset($_POST['replaceId'])&&$_POST['replaceId']!==''?(string)$_POST['replaceId']:null;
    $result=loom_html_framer_import($project,$tmp,$name,$entry,$defaults['height'],$defaults['widthPercent'],$replaceId,$defaults);
    if($result['needsEntrypoint']??false)json_out(['ok'=>true]+$result);
    json_out(html_framer_payload($project)+['import'=>$result]);
  }catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
}

$raw=$jsonBody;
$frameId=(string)($raw['frameId']??'');
if(!preg_match('/^hf_[a-f0-9]{14}$/',$frameId))json_out(['ok'=>false,'error'=>'invalid-frame-id'],400);
$registry=loom_html_framer_registry($project);$frame=$registry['frames'][$frameId]??null;
if(!is_array($frame))json_out(['ok'=>false,'error'=>'frame-not-found'],404);

try{
  if($action==='update'){
    if(array_key_exists('title',$raw)){$title=loom_clean_project_text($raw['title'],80);if($title==='')throw new RuntimeException('Frame title cannot be empty.');$frame['title']=$title;}
    if(array_key_exists('enabled',$raw))$frame['enabled']=(bool)$raw['enabled'];
    if(array_key_exists('actionReaderEnabled',$raw))$frame['actionReaderEnabled']=(bool)$raw['actionReaderEnabled'];
    if(array_key_exists('heightMode',$raw))$frame['heightMode']=strtolower((string)$raw['heightMode'])==='fixed'?'fixed':'auto';
    if(array_key_exists('height',$raw))$frame['height']=max(200,min(2400,(int)$raw['height']));
    if(array_key_exists('widthPercent',$raw))$frame['widthPercent']=max(50,min(100,(int)$raw['widthPercent']));
    if(array_key_exists('mobileHeightMode',$raw))$frame['mobileHeightMode']=strtolower((string)$raw['mobileHeightMode'])==='fixed'?'fixed':'auto';
    if(array_key_exists('mobileHeight',$raw))$frame['mobileHeight']=max(200,min(2400,(int)$raw['mobileHeight']));
    if(array_key_exists('mobileWidthPercent',$raw))$frame['mobileWidthPercent']=max(50,min(100,(int)$raw['mobileWidthPercent']));
    if(array_key_exists('fullscreenEnabled',$raw))$frame['fullscreenEnabled']=(bool)$raw['fullscreenEnabled'];
    $frame['revision']=max(1,(int)($frame['revision']??1)+1);$frame['updatedAt']=server_timestamp();
    $registry['frames'][$frameId]=$frame;loom_html_framer_write_registry($project,$registry);
  }elseif($action==='move'){
    $direction=(string)($raw['direction']??'');$frames=loom_html_framer_public_frames($project);
    $idx=null;foreach($frames as $i=>$f)if(($f['id']??'')===$frameId){$idx=$i;break;}
    if($idx!==null){
      $swap=$direction==='up'?$idx-1:($direction==='down'?$idx+1:$idx);
      if($swap>=0&&$swap<count($frames)&&$swap!==$idx){
        $a=$frames[$idx];$b=$frames[$swap];$ao=(int)($a['order']??0);$bo=(int)($b['order']??0);
        $registry['frames'][$a['id']]['order']=$bo;$registry['frames'][$b['id']]['order']=$ao;
        $registry['frames'][$a['id']]['revision']=max(1,(int)($registry['frames'][$a['id']]['revision']??1)+1);
        $registry['frames'][$b['id']]['revision']=max(1,(int)($registry['frames'][$b['id']]['revision']??1)+1);
        loom_html_framer_write_registry($project,$registry);
      }
    }
  }elseif($action==='delete'){
    unset($registry['frames'][$frameId]);loom_html_framer_write_registry($project,$registry);
    loom_html_framer_remove_tree(loom_html_framer_frame_dir($project,$frameId));
  }else json_out(['ok'=>false,'error'=>'unsupported-action'],400);
  json_out(html_framer_payload($project));
}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],400);}
