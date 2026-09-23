<?php
// @loom-file release=0.15.01 revision=11 policy=package-priority
require __DIR__.'/_common.php';

$manifestPath=root_dir().'/.loom-deployment.json';
$manifest=is_file($manifestPath)?read_json_file($manifestPath):[];
$canonical=loom_release_version('0.15.01');

function loom_extract_version(string $file,string $pattern): ?string {
  if(!is_file($file))return null;
  $text=(string)file_get_contents($file);
  return preg_match($pattern,$text,$m)?(string)$m[1]:null;
}

$engine=loom_extract_version(root_dir().'/engine/config.js',"/engineVersion\\s*:\\s*['\\\"]([^'\\\"]+)['\\\"]/u");
$manifestOk=$canonical!=='';
$engineOk=$manifestOk&&$engine===$canonical;

json_out([
  'ok'=>$manifestOk,
  'canonicalVersion'=>$canonical?:null,
  'source'=>'.loom-deployment.json',
  'deploymentComplete'=>$manifestOk,
  'health'=>[
    'status'=>($manifestOk&&$engineOk)?'healthy':'mixed-release',
    'engineVersion'=>$engine,
    'engineMatchesCanonical'=>$engineOk,
    'brandVersionSource'=>'canonical-api',
    'brandMatchesCanonical'=>$manifestOk,
    'packageFileCount'=>is_array($manifest['files']??null)?count($manifest['files']):0,
    'manifestUpdatedAt'=>is_file($manifestPath)?gmdate('c',(int)filemtime($manifestPath)):null,
  ],
]);
