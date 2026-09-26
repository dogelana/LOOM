<?php
// @loom-file release=0.12.08 revision=1 policy=package-priority
require __DIR__.'/_common.php';if(!loom_request_is_admin())json_out(['ok'=>false,'error'=>'admin-access-required'],403);json_out(['ok'=>true,'ledger'=>loom_migration_bootstrap(),'definitions'=>loom_migration_definitions()]);
