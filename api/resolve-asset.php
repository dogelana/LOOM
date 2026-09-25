<?php
// @loom-file release=0.15.46 revision=11 policy=package-priority
declare(strict_types=1);
require __DIR__.'/_common.php';

$project = (string)($_GET['project'] ?? '');
$scope   = (string)($_GET['scope'] ?? 'project');
$path    = (string)($_GET['path'] ?? '');
$name    = (string)($_GET['name'] ?? ''); // legacy compatibility only

/**
 * Resolve an explicit relative path beneath $base without ever searching
 * neighboring folders. Matching is case-insensitive segment-by-segment so
 * assets/logo.png can still find Assets/Logo.PNG, but it can never fall back
 * to project-root/logo.png or another same-named file.
 */
function resolve_relative_ci(string $base, string $relative): ?string {
    $baseReal = realpath($base);
    if ($baseReal === false || !is_dir($baseReal)) return null;

    $relative = trim(str_replace('\\', '/', $relative));
    $relative = ltrim($relative, '/');
    if ($relative === '') return null;

    $parts = array_values(array_filter(explode('/', $relative), fn($p) => $p !== ''));
    if (!$parts) return null;

    $current = $baseReal;
    foreach ($parts as $part) {
        if ($part === '.' || $part === '..' || str_contains($part, "\0")) return null;
        if (!is_dir($current)) return null;

        $match = null;
        foreach (scandir($current) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') continue;
            if (strcasecmp($entry, $part) === 0) { $match = $entry; break; }
        }
        if ($match === null) return null;
        $current .= DIRECTORY_SEPARATOR . $match;
    }

    $resolved = realpath($current);
    if ($resolved === false || !is_file($resolved)) return null;

    $prefix = rtrim($baseReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
    if (!str_starts_with($resolved, $prefix)) return null;
    return $resolved;
}

$base = null;
$requestedPath = $path;

if ($scope === 'server') {
    $base = root_dir();
} else {
    $base = project_dir($project);
    if (!$base) json_out(['found'=>false,'error'=>'Project not found'],404);
}

// Backward compatibility: old callers may still send name/scope. This is an
// exact location, NOT a filename hunt. project-assets explicitly means assets/.
if ($requestedPath === '' && $name !== '') {
    $safeName = basename($name);
    if ($safeName === '') json_out(['found'=>false,'error'=>'Invalid name'],400);
    $requestedPath = ($scope === 'project-assets') ? 'assets/'.$safeName : $safeName;
}

if ($requestedPath === '') json_out(['found'=>false,'error'=>'Missing path'],400);

$overlayFound=null;$overlayUrl=null;
if($scope!=='server'){
    $overlay=loom_project_overlay_asset($project,$requestedPath);
    if($overlay&&is_file($overlay)){
        $overlayFound=$overlay;
        $overlayUrl=web_base_path().'/api/project-asset.php?project='.rawurlencode(safe_slug($project)).'&path='.rawurlencode($requestedPath).'&v='.rawurlencode(file_cache_version($overlay));
    }
}
$found=$overlayFound?:resolve_relative_ci($base,$requestedPath);
$projectProxyUrl=null;
if($found&&$scope!=='server'&&str_starts_with(strtolower(ltrim(str_replace('\\','/',$requestedPath),'/')),'assets/')){
    // Project assets may physically live in the protected Instance Vault.
    // Never return a direct /instance URL; use the canonical asset proxy.
    $projectProxyUrl=loom_project_asset_url($project,$requestedPath);
}
$loomDefaultLogo=false;
if(!$found&&$scope!=='server'&&strtolower(trim(str_replace('\\','/',$requestedPath),'/'))==='assets/logo.png'){
    $candidate=root_dir().'/assets/loom-logo.png';
    if(is_file($candidate)){$found=$candidate;$loomDefaultLogo=true;}
}
$assetVersion=$found?file_cache_version($found):null;
json_out([
    'found'=>(bool)$found,
    'project'=>safe_slug($project),
    'scope'=>$scope,
    'requestedPath'=>$requestedPath,
    'url'=>$overlayUrl?:($projectProxyUrl?:($found ? versioned_rel_url($found) : null)),
    'asset_version'=>$assetVersion,
    'cache_busted'=>(bool)$found,
    'resolution'=>$overlayFound?'persistent-instance-overlay':($projectProxyUrl?'project-asset-proxy':($loomDefaultLogo?'loom-default-project-logo':'explicit-relative-path'))
]);
