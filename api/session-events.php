<?php
// @loom-file release=0.12.08 revision=2 policy=package-priority
require __DIR__.'/_common.php';
if(!loom_request_is_admin())json_out(['ok'=>false,'error'=>'admin-access-required'],403);
$project=safe_slug((string)($_GET['project']??''));if($project===''||!project_dir($project))json_out(['error'=>'Invalid project'],400);
mark_stale_sessions($project);
$session=(string)($_GET['session_id']??'');$client=(string)($_GET['client_id']??'');$limit=max(1,min(5000,(int)($_GET['limit']??2000)));
$dir=log_dir($project);$events=[];$files=is_dir($dir)?(glob($dir.'/*.jsonl')?:[]):[];sort($files);
foreach($files as $file){$lines=@file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[];foreach($lines as $line){$x=json_decode($line,true);if(!is_array($x))continue;if(!loom_project_record_visible($project,(string)($x['clientId']??''),(string)($x['userId']??'')))continue;if($session!==''&&($x['sessionId']??'')!==$session)continue;if($client!==''&&($x['clientId']??'')!==$client)continue;$events[]=$x;if(count($events)>=$limit)array_shift($events);}}
json_out(['project'=>$project,'session_id'=>$session?:null,'client_id'=>$client?:null,'events'=>$events]);
