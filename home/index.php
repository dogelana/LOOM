<?php
// @loom-file release=0.15.08 revision=1 policy=package-priority
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');
$file=dirname(__DIR__).'/index.html';
if(!is_file($file)){http_response_code(500);echo '<h1>LOOM Home is unavailable.</h1>';exit;}
$html=(string)file_get_contents($file);
$count=0;
$html=preg_replace('/<head(\\s[^>]*)?>/i','$0<base href="../">',$html,1,$count)??$html;
if($count!==1){http_response_code(500);echo '<h1>LOOM Home shell is invalid.</h1>';exit;}
header('X-LOOM-Home-Route: reserved');
echo $html;
