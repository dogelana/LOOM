<?php
// Admin API for LOOM HTML Framer.
declare(strict_types=1);
require __DIR__.'/_common.php';
require __DIR__.'/_html_framer.php';

$method=$_SERVER['REQUEST_METHOD']??'GET';
$rawBody=(string)file_get_contents('php://input');
$jsonBody=json_decode($rawBody,true);if(!is_array($jsonBody))$jsonBody=[];
$action=(string)($_REQUEST['action']??$jsonBody['action']??'list');
$clientId=safe_token((string)($_REQUEST['clientId']??$jsonBody['clientId']??''));
$project=safe_slug((string)($_REQUEST['project']??$jsonBody['project']??'green-beans'));
if(!$project||!project_dir($project))json_out(['ok'=>false,'error'=>'project-not-found'],404);
loom_require_admin($clientId);

function html_framer_payload(string $project): array {
  return ['ok'=>true,'project'=>$project,'frames'=>loom_html_framer_public_frames($project),'zipAvailable'=>loom_html_framer_zip_supported()];
}
if($method==='GET'||$action==='list')json_out(html_framer_payload($project));

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
    $settings=loom_read_admin_settings($project);
    $framerCfg=$settings['modules']['loom.html-framer']??[];
    $defaultHeight=max(240,min(1200,(int)($framerCfg['defaultFrameHeight']??520)));
    $replaceId=isset($_POST['replaceId'])&&$_POST['replaceId']!==''?(string)$_POST['replaceId']:null;
    $result=loom_html_framer_import($project,$tmp,$name,$entry,$defaultHeight,$replaceId);
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
    if(array_key_exists('height',$raw))$frame['height']=max(200,min(1600,(int)$raw['height']));
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
