<?php
// @loom-file release=0.12.08 revision=1 policy=package-priority
require __DIR__.'/_common.php';if(!loom_request_is_admin())json_out(['ok'=>false,'error'=>'admin-access-required'],403);$limit=max(1,min(500,(int)($_GET['limit']??100)));$rows=[];foreach(array_reverse(glob(loom_audit_dir().'/*.jsonl')?:[]) as $file){$lines=array_reverse(@file($file,FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES)?:[]);foreach($lines as $line){$r=json_decode($line,true);if(is_array($r))$rows[]=$r;if(count($rows)>=$limit)break 2;}}json_out(['ok'=>true,'rows'=>$rows]);
