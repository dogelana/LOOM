<?php
// @loom-file release=0.12.11 revision=2 policy=package-priority
declare(strict_types=1);

function loom_project_sandbox_descriptor(string $project): array {
  $slug=safe_slug($project);$dir=$slug!==''?project_dir($slug):null;if(!$dir)throw new RuntimeException('Project sandbox not found.');
  return ['project'=>$slug,'isolation'=>'project-default-deny','crossProject'=>'explicit-capability-only','projectRoot'=>$dir,'stateRoot'=>loom_data_dir().'/project-state/'.$slug,'moduleRoot'=>$dir.'/actions'];
}
function loom_project_sandbox_public(string $project): array { $d=loom_project_sandbox_descriptor($project);return ['project'=>$d['project'],'isolation'=>$d['isolation'],'crossProject'=>$d['crossProject']]; }
function loom_project_sandbox_path(string $project,string $relative): string {
  $d=loom_project_sandbox_descriptor($project);$relative=str_replace('\\','/',trim($relative));if($relative===''||str_starts_with($relative,'/')||str_contains($relative,'..')||str_contains($relative,':'))throw new RuntimeException('Unsafe project sandbox path.');$root=realpath($d['projectRoot'])?:$d['projectRoot'];$candidate=$d['projectRoot'].'/'.ltrim($relative,'/');$parent=realpath(dirname($candidate))?:dirname($candidate);$rootNorm=rtrim(str_replace('\\','/',$root),'/').'/';$parentNorm=rtrim(str_replace('\\','/',$parent),'/').'/';if(!str_starts_with($parentNorm,$rootNorm))throw new RuntimeException('Project sandbox boundary denied.');return $candidate;
}
function loom_project_sandbox_cross_project_allowed(string $from,string $to,bool $isAdmin=false): bool { $from=safe_slug($from);$to=safe_slug($to);if($from!==''&&$from===$to)return true;return $isAdmin; }
