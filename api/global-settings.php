<?php
// @loom-file release=0.12.08 revision=7 policy=package-priority
require __DIR__.'/_common.php';
json_out(['ok'=>true,'version'=>loom_release_version()]+loom_global_settings_payload());
