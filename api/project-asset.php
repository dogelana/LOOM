<?php
// LOOM persistent project asset proxy. /instance itself remains web-denied.
declare(strict_types=1);
require __DIR__.'/_common.php';

$project=safe_slug((string)($_GET['project']??''));
$path=ltrim(str_replace('\\','/',(string)($_GET['path']??'')),'/');
if($project===''||$path===''||str_contains($path,'..')){http_response_code(400);exit;}
if(!str_starts_with($path,'assets/')){http_response_code(403);exit;}

$file=loom_project_overlay_asset($project,$path);
if(!$file||!is_file($file)){http_response_code(404);exit;}

$ext=strtolower(pathinfo($file,PATHINFO_EXTENSION));
$types=['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','webp'=>'image/webp','gif'=>'image/gif','svg'=>'image/svg+xml','ico'=>'image/x-icon'];
if(!isset($types[$ext])){http_response_code(403);exit;}

header('Content-Type: '.$types[$ext]);
header('Content-Length: '.(string)filesize($file));
header('Cache-Control: public, max-age=31536000, immutable');
header('X-Content-Type-Options: nosniff');
readfile($file);
