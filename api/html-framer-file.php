<?php
// Sandboxed static file server for LOOM HTML Framer.
declare(strict_types=1);
require __DIR__.'/_common.php';
require __DIR__.'/_html_framer.php';

$project=safe_slug((string)($_GET['project']??''));
$frameId=(string)($_GET['frame']??'');
$path=loom_html_framer_safe_rel((string)($_GET['path']??''));
$clientId=safe_token((string)($_GET['clientId']??''));
if(!$project||!project_dir($project)||!preg_match('/^hf_[a-f0-9]{14}$/',$frameId)||!$path){http_response_code(400);exit;}
loom_enforce_project_access($project,$clientId);
$frame=loom_html_framer_frame($project,$frameId);
if(!$frame||($frame['enabled']??true)===false){http_response_code(404);exit;}
$base=loom_html_framer_frame_dir($project,$frameId).'/files';
$file=$base.'/'.$path;
$realBase=realpath($base);$real=realpath($file);
if(!$realBase||!$real||!is_file($real)||!str_starts_with($real,$realBase.DIRECTORY_SEPARATOR)){http_response_code(404);exit;}

$registry=loom_html_framer_frame_dir($project,$frameId).'/files';
$paths=[];
$it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($registry,FilesystemIterator::SKIP_DOTS));
foreach($it as $f)if($f->isFile())$paths[]=str_replace('\\','/',substr($f->getPathname(),strlen($registry)+1));

$mime=loom_html_framer_mime($path);
header('Content-Type: '.$mime);
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cross-Origin-Resource-Policy: cross-origin');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, must-revalidate');
if(loom_html_framer_is_html($path)){
  header("Content-Security-Policy: default-src 'none'; script-src 'unsafe-inline' 'unsafe-eval' https: http: data: blob:; style-src 'unsafe-inline' https: http: data: blob:; img-src https: http: data: blob:; font-src https: http: data:; media-src https: http: data: blob:; connect-src https: http:; frame-src https: http:; child-src https: http: blob:; form-action https: http:; base-uri 'none'");
}
$data=(string)file_get_contents($real);
if(loom_html_framer_is_html($path)||loom_html_framer_is_css($path)||loom_html_framer_is_js($path))
  $data=loom_html_framer_serve_transform($project,$frameId,$path,$data,$frame,$paths,$clientId);
header('Content-Length: '.strlen($data));
echo $data;
