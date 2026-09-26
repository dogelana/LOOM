<?php
// @loom-file release=0.15.18 revision=1 policy=package-priority
// Public project gateway: injects crawler-visible SEO/social metadata before serving the existing project shell.
declare(strict_types=1);
require __DIR__.'/api/_common.php';
header('Content-Type: text/html; charset=utf-8');header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
$slug=safe_slug((string)($_GET['project']??''));$dir=project_dir($slug);if($slug===''||!$dir){http_response_code(404);echo '<h1>Project not found.</h1>';exit;}
// Private projects are rejected before any project HTML/metadata is emitted. Permanent accounts
// can authorize through their signed-in session; Guest access uses the signed Home entry URL.
if(function_exists('loom_access_project_is_private')&&loom_access_project_is_private($slug)){
  $allowed=false;$auth=loom_auth_user();$uid=safe_token((string)($auth['user_id']??$auth['userId']??''));
  if($uid!==''&&loom_access_subject_can_view_private_project($slug,'user',$uid))$allowed=true;
  $entryClient=safe_token((string)($_GET['clientId']??''));$entrySig=(string)($_GET['pa']??'');
  if(!$allowed&&$entryClient!==''&&loom_access_verify_project_entry_signature($slug,$entryClient,$entrySig)&&loom_access_can_view_project($entryClient,$slug))$allowed=true;
  if(!$allowed){http_response_code(404);header('Cache-Control: no-store');echo '<h1>Project not found.</h1>';exit;}
}
$instance=loom_project_is_instance_owned($slug);$shell=$instance?__DIR__.'/projects/_instance/app/index.html':$dir.'/app/index.html';if(!is_file($shell)){http_response_code(404);echo '<h1>Project shell unavailable.</h1>';exit;}
$html=(string)file_get_contents($shell);$mountBase=$instance?'projects/_instance/app/':'projects/'.rawurlencode($slug).'/app/';$canonical=loom_project_absolute_public_url($slug);$meta=loom_project_social_meta($slug,$canonical);
$ctx=['project'=>$slug,'loomBase'=>rtrim(web_base_path(),'/').'/', 'publicBase'=>rtrim(web_base_path(),'/').'/', 'homeUrl'=>rtrim(web_base_path(),'/').'/home/','canonicalProjectUrl'=>$canonical,'rootMounted'=>false,'source'=>$instance?'instance':'release'];
$ctxJson=json_encode($ctx,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT);
$inject='<base href="'.htmlspecialchars(rtrim(web_base_path(),'/').'/'.$mountBase,ENT_QUOTES,'UTF-8').'">'.loom_social_meta_html($meta,true).'<script>window.LOOM_MOUNT_CONTEXT='.$ctxJson.';</script>';
// Remove static title/description/canonical/OG/Twitter tags to avoid stale duplicates.
$html=preg_replace('~<title>.*?</title>~is','',$html)??$html;
$html=preg_replace('~<meta\s+(?:name|property)=["\'](?:description|robots|og:[^"\']+|twitter:[^"\']+)["\'][^>]*>~is','',$html)??$html;
$html=preg_replace('~<link\s+rel=["\']canonical["\'][^>]*>~is','',$html)??$html;
$html=preg_replace('/<head(\s[^>]*)?>/i','$0'.$inject,$html,1,$count)??$html;if(($count??0)!==1){http_response_code(500);echo '<h1>Invalid project shell.</h1>';exit;}
header('X-LOOM-Project: '.$slug);header('Content-Security-Policy: frame-ancestors \'self\'');echo $html;
