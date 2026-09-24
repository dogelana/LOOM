<?php
// @loom-file release=0.12.11 revision=3 policy=package-priority
require __DIR__.'/_common.php';
if(!loom_request_is_admin())json_out(['ok'=>false,'error'=>'admin-access-required'],403);
$project=safe_slug((string)($_GET['project']??'')); $limit=max(1,min(500,(int)($_GET['limit']??100))); if($project===''||!project_dir($project))json_out(['error'=>'Invalid project'],400);
$dir=loom_data_dir().'/logs/'.$project; $events=[];
$files=is_dir($dir)?(glob($dir.'/*.jsonl')?:[]):[]; rsort($files);
foreach($files as $file){$lines=@file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[]; for($i=count($lines)-1;$i>=0 && count($events)<$limit;$i--){$x=json_decode($lines[$i],true);if(is_array($x)&&loom_project_record_visible($project,(string)($x['clientId']??''),(string)($x['userId']??'')))$events[]=$x;} if(count($events)>=$limit)break;}
json_out(['project'=>$project,'events'=>$events]);
