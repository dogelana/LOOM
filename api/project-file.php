<?php
// Project static-file gateway for Instance Projects.
declare(strict_types=1);
require __DIR__.'/_common.php';
$project=safe_slug((string)($_GET['project']??''));$path=ltrim(str_replace('\\','/',(string)($_GET['path']??'')),'/');$clientId=safe_token((string)($_GET['clientId']??''));
if($project===''||$path===''||str_contains($path,'..')||!loom_project_is_instance_owned($project)){http_response_code(404);exit;}
loom_enforce_project_access($project,$clientId);
$root=project_dir($project);$candidate=$root.'/'.$path;$realRoot=realpath($root);$real=realpath($candidate);
if(!$realRoot||!$real||!is_file($real)||!str_starts_with($real,$realRoot.DIRECTORY_SEPARATOR)){http_response_code(404);exit;}
$ext=strtolower(pathinfo($real,PATHINFO_EXTENSION));$allow=['js','mjs','cjs','css','json','svg','png','jpg','jpeg','gif','webp','ico','woff','woff2','ttf','otf','txt','xml','webmanifest'];if(!in_array($ext,$allow,true)){http_response_code(403);exit;}
$mime=['js'=>'application/javascript; charset=utf-8','mjs'=>'application/javascript; charset=utf-8','cjs'=>'application/javascript; charset=utf-8','css'=>'text/css; charset=utf-8','json'=>'application/json; charset=utf-8','svg'=>'image/svg+xml','png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg','gif'=>'image/gif','webp'=>'image/webp','ico'=>'image/x-icon','woff'=>'font/woff','woff2'=>'font/woff2','ttf'=>'font/ttf','otf'=>'font/otf','txt'=>'text/plain; charset=utf-8','xml'=>'application/xml; charset=utf-8','webmanifest'=>'application/manifest+json; charset=utf-8'];
header('Content-Type: '.($mime[$ext]??'application/octet-stream'));header('X-Content-Type-Options: nosniff');header('Cache-Control: no-cache, must-revalidate');readfile($real);
