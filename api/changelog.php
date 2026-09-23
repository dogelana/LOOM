<?php
// @loom-file release=0.12.11 revision=5 policy=package-priority
require __DIR__.'/_common.php';

function changelog_version_heading(string $line): ?array {
  if(!preg_match('/^#{1,3}\s+(?:LOOM\s+)?v?(\d+\.\d+(?:\.\d+)?)\s*(?:[—–-]\s*)?(.*)$/u',trim($line),$m))return null;
  return ['version'=>$m[1],'title'=>trim($m[2])];
}
function changelog_blocks(array $lines): array {
  $blocks=[];$bullets=[];$para=[];$code=[];$inCode=false;
  $flushBullets=function()use(&$blocks,&$bullets){if($bullets){$blocks[]=['type'=>'list','items'=>$bullets];$bullets=[];}};
  $flushPara=function()use(&$blocks,&$para){if($para){$blocks[]=['type'=>'paragraph','text'=>trim(implode(' ',$para))];$para=[];}};
  $flushCode=function()use(&$blocks,&$code){if($code){$blocks[]=['type'=>'code','text'=>implode("\n",$code)];$code=[];}};
  foreach($lines as $raw){$line=rtrim($raw,"\r\n");$trim=trim($line);
    if(str_starts_with($trim,'```')){if($inCode){$flushCode();$inCode=false;}else{$flushBullets();$flushPara();$inCode=true;}continue;}
    if($inCode){$code[]=$line;continue;}
    if($trim===''||$trim==='---'){$flushBullets();$flushPara();continue;}
    if(preg_match('/^#{2,5}\s+(.+)$/u',$trim,$m)){$flushBullets();$flushPara();$blocks[]=['type'=>'heading','text'=>trim($m[1])];continue;}
    if(preg_match('/^[-*]\s+(.+)$/u',$trim,$m)){$flushPara();$bullets[]=trim($m[1]);continue;}
    if(preg_match('/^\d+[.)]\s+(.+)$/u',$trim,$m)){$flushPara();$bullets[]=trim($m[1]);continue;}
    $flushBullets();$para[]=$trim;
  }
  $flushBullets();$flushPara();$flushCode();
  return $blocks;
}
function parse_changelog(string $text): array {
  $lines=preg_split('/\R/u',$text)?:[];$releases=[];$current=null;
  foreach($lines as $line){$heading=changelog_version_heading($line);
    if($heading){if($current){$current['blocks']=changelog_blocks($current['_lines']);unset($current['_lines']);$releases[]=$current;}
      $current=['version'=>$heading['version'],'title'=>$heading['title']!==''?$heading['title']:'Release','_lines'=>[]];continue;}
    if($current)$current['_lines'][]=$line;
  }
  if($current){$current['blocks']=changelog_blocks($current['_lines']);unset($current['_lines']);$releases[]=$current;}
  usort($releases,fn($a,$b)=>version_compare($b['version'],$a['version']));
  return $releases;
}

$scope=strtolower((string)($_GET['scope']??'loom'));$project=safe_slug((string)($_GET['project']??''));
if($scope==='project'){
  if($project===''||!project_dir($project))json_out(['ok'=>false,'error'=>'project-not-found'],404);
  $file=project_dir($project).'/CHANGELOG.md';$product=loom_project_effective_data($project)['name']??$project;
}else{$scope='loom';$file=root_dir().'/CHANGELOG.md';$product='LOOM';}
if(!is_file($file))json_out(['ok'=>false,'error'=>$scope==='project'?'project-changelog-not-found':'changelog-not-found'],404);
$text=(string)file_get_contents($file);$releases=parse_changelog($text);$limit=max(1,min(100,(int)($_GET['limit']??30)));
$canonical=null;if($scope==='loom'){$dm=read_json_file(root_dir().'/.loom-deployment.json');$canonical=(string)($dm['loom_release']??'');}json_out(['ok'=>true,'scope'=>$scope,'project'=>$scope==='project'?$project:null,'product'=>$product,'currentVersion'=>($scope==='loom'&&$canonical!=='')?$canonical:($releases[0]['version']??null),'releaseCount'=>count($releases),'sourceUpdatedAt'=>gmdate('c',(int)filemtime($file)),'releases'=>array_slice($releases,0,$limit)]);
