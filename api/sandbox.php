<?php
// @loom-file release=0.12.08 revision=1 policy=package-priority
require __DIR__.'/_common.php';
$project=safe_slug((string)($_GET['project']??''));if($project==='')json_out(['ok'=>false,'error'=>'Project required'],400);try{json_out(['ok'=>true,'sandbox'=>loom_project_sandbox_public($project)]);}catch(Throwable $e){json_out(['ok'=>false,'error'=>$e->getMessage()],404);}
