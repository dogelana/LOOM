<?php
// @loom-file release=0.15.18 revision=2 policy=package-priority
declare(strict_types=1);
require __DIR__.'/api/_common.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

function loom_router_install_base(): string {
  $script=str_replace('\\','/',(string)($_SERVER['SCRIPT_NAME']??'/index.php'));
  $base=str_replace('\\','/',dirname($script));
  if($base==='/'||$base==='.'||$base==='\\')return '';
  return rtrim($base,'/');
}
function loom_router_send_home(?string $reason=null): never {
  $file=__DIR__.'/index.html';
  if(!is_file($file)){http_response_code(500);echo '<h1>LOOM Home is unavailable.</h1>';exit;}
  header('X-LOOM-Domain-Landing: loom-home');if($reason)header('X-LOOM-Domain-Landing-Reason: '.$reason);
  $html=(string)file_get_contents($file);$canonical=loom_absolute_web_url((loom_router_install_base()===''?'/':loom_router_install_base().'/'));
  $meta=loom_generic_social_meta($canonical,'LOOM','LOOM modular application engine. Build, compose, and manage modular projects from one engine.');
  $html=preg_replace('~<title>.*?</title>~is','',$html)??$html;
  $inject=loom_social_meta_html($meta,false);$html=preg_replace('/<head(\s[^>]*)?>/i','$0'.$inject,$html,1)??$html;echo $html;exit;
}
function loom_router_project_shell(string $project): never {
  $slug=safe_slug($project);$dir=project_dir($slug);
  if(!$dir)loom_router_send_home('project-unavailable');
  $instance=loom_project_is_instance_owned($slug);
  $shell=$instance?__DIR__.'/projects/_instance/app/index.html':$dir.'/app/index.html';
  if(!is_file($shell))loom_router_send_home('project-shell-unavailable');
  $html=(string)file_get_contents($shell);
  if($html==='')loom_router_send_home('project-shell-empty');

  $mountBase=$instance?'projects/_instance/app/':'projects/'.rawurlencode($slug).'/app/';
  $installBase=loom_router_install_base();
  $rootUrl=($installBase===''?'/':$installBase.'/');
  $homeUrl=($installBase===''?'/home/':$installBase.'/home/');
  $canonicalProjectUrl=$instance
    ? ($installBase===''?'/':$installBase.'/').'projects/_instance/app/?project='.rawurlencode($slug)
    : ($installBase===''?'/':$installBase.'/').'projects/'.rawurlencode($slug).'/app/?project='.rawurlencode($slug);
  $ctx=[
    'project'=>$slug,
    'loomBase'=>$rootUrl,
    'publicBase'=>$rootUrl,
    'homeUrl'=>$homeUrl,
    'canonicalProjectUrl'=>$canonicalProjectUrl,
    'rootMounted'=>true,
    'source'=>$instance?'instance':'release'
  ];
  $ctxJson=json_encode($ctx,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
  $absoluteRoot=loom_absolute_web_url($rootUrl)?:$rootUrl;$meta=loom_project_social_meta($slug,$absoluteRoot);
  $html=preg_replace('~<title>.*?</title>~is','',$html)??$html;
  $html=preg_replace('~<meta\s+(?:name|property)=["\'](?:description|robots|og:[^"\']+|twitter:[^"\']+)["\'][^>]*>~is','',$html)??$html;
  $html=preg_replace('~<link\s+rel=["\']canonical["\'][^>]*>~is','',$html)??$html;
  $injection='<base href="'.htmlspecialchars($mountBase,ENT_QUOTES,'UTF-8').'">'.loom_social_meta_html($meta,true).
    '<script>window.LOOM_MOUNT_CONTEXT='.$ctxJson.';document.documentElement.dataset.loomRootMounted="1";</script>';
  $count=0;
  $html=preg_replace('/<head(\\s[^>]*)?>/i','$0'.$injection,$html,1,$count)??$html;
  if($count!==1)loom_router_send_home('project-shell-invalid');
  header('X-LOOM-Domain-Landing: project');
  header('X-LOOM-Project: '.$slug);
  header('Content-Security-Policy: frame-ancestors \'self\'');
  echo $html;exit;
}

$effective=loom_domain_routing_effective();
if(($effective['mode']??'loom-home')==='project'&&($effective['project']??'')!==''){
  loom_router_project_shell((string)$effective['project']);
}
loom_router_send_home(is_string($effective['reason']??null)?$effective['reason']:null);
