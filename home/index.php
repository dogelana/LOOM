<?php
// @loom-file release=0.15.18 revision=2 policy=package-priority
declare(strict_types=1);
require dirname(__DIR__).'/api/_common.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$file=dirname(__DIR__).'/index.html';
if(!is_file($file)){http_response_code(500);echo '<h1>LOOM Home is unavailable.</h1>';exit;}
$html=(string)file_get_contents($file);
$html=preg_replace('~<title>.*?</title>~is','',$html)??$html;
$meta=loom_generic_social_meta(loom_absolute_web_url(rtrim(web_base_path(),'/').'/home/'),'LOOM','LOOM modular application engine. Build, compose, and manage modular projects from one engine.');
$count=0;
$injection='<base href="../">'.loom_social_meta_html($meta,false);
$html=preg_replace('/<head(\s[^>]*)?>/i','$0'.$injection,$html,1,$count)??$html;
if($count!==1){http_response_code(500);echo '<h1>LOOM Home shell is invalid.</h1>';exit;}
header('X-LOOM-Home-Route: reserved');
echo $html;
