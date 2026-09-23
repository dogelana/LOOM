<?php
// @loom-file release=0.15.15 revision=25 policy=package-priority
require __DIR__.'/_common.php';
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$manifestPath=root_dir().'/.loom-deployment.json';
$manifest=is_file($manifestPath)?read_json_file($manifestPath):[];
$canonical=loom_release_version('0.15.15');

function loom_extract_version(string $file,string $pattern): ?string {
  if(!is_file($file))return null;
  $text=(string)file_get_contents($file);
  return preg_match($pattern,$text,$m)?(string)$m[1]:null;
}

$engine=loom_extract_version(root_dir().'/engine/config.js',"/engineVersion\\s*:\\s*['\\\"]([^'\\\"]+)['\\\"]/u");
$manifestOk=$canonical!=='';
$engineOk=$manifestOk&&$engine===$canonical;
$deploymentFingerprint=is_file($manifestPath)?substr((string)hash_file('sha256',$manifestPath),0,24):null;

json_out([
  'ok'=>$manifestOk,
  'canonicalVersion'=>$canonical?:null,
  'source'=>'.loom-deployment.json',
  'deploymentComplete'=>$manifestOk,
  'deploymentFingerprint'=>$deploymentFingerprint,
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
