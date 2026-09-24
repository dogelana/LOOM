<?php
// @loom-file release=0.15.26 revision=1 policy=package-priority
require __DIR__.'/api/_common.php';header('Content-Type: text/plain; charset=utf-8');header('Cache-Control: public, max-age=300');$base=rtrim(web_base_path(),'/');echo "User-agent: *\nAllow: /\nDisallow: {$base}/api/\nDisallow: {$base}/admin/\nDisallow: {$base}/pegboard/\nDisallow: {$base}/registry/\nDisallow: {$base}/instance/\nSitemap: ".loom_absolute_web_url($base.'/sitemap.xml')."\n";
