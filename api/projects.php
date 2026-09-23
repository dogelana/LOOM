<?php
// @loom-file release=0.15.20 revision=18 policy=package-priority
require __DIR__.'/_common.php';
$requestClientId=safe_token((string)($_GET['clientId']??''));if($requestClientId!=='')loom_capture_request_ip($requestClientId,'');
$onlyProject=safe_slug((string)($_GET['project']??''));
$projects=[];$release=loom_release_version();

function loom_home_project_branding(string $slug,string $dir,array $projectMeta=[]): array {
  $projectBrand=is_array($projectMeta['branding']??null)?$projectMeta['branding']:[];
  $assetPath=ltrim(str_replace('\\','/',(string)($projectBrand['logo_asset']??'assets/logo.png')),'/');
  $logoUrl=$assetPath!==''&&!str_contains($assetPath,'..')?loom_project_asset_url($slug,$assetPath):null;
  // Compatibility only: if an old project kept a nonstandard explicit logo path
  // in the canonical legacy module location, honor it without recursively scanning
  // the entire action tree for every card on LOOM Home.
  if(!$logoUrl&&!array_key_exists('logo_asset',$projectBrand)){
    $legacyFile=$dir.'/actions/core/ui/load-logo/manifest.json';$legacy=is_file($legacyFile)?read_json_file($legacyFile):null;
    $legacyPath=is_array($legacy)?ltrim(str_replace('\\','/',(string)($legacy['config']['assetPath']??'')),'/'):'';
    if($legacyPath!==''&&!str_contains($legacyPath,'..')){$candidate=loom_project_asset_url($slug,$legacyPath);if($candidate){$assetPath=$legacyPath;$logoUrl=$candidate;}}
  }
  $isDefault=false;if(!$logoUrl){$logoUrl=loom_default_project_logo_url();$isDefault=$logoUrl!==null;}
  $wm=loom_project_brand_identity($slug);$name=(string)($projectMeta['name']??$wm['name']);
  return [
    'logo_url'=>$logoUrl,'logo_alt'=>(string)($projectBrand['logo_alt']??($name.' logo')),'logo_asset'=>$assetPath?:'assets/logo.png','logo_is_loom_default'=>$isDefault,
    'wordmark'=>[
      'line1'=>$wm['line1'],'line2'=>$wm['line2'],'font_family'=>$wm['font_family'],'font_weight'=>$wm['font_weight'],
      'color1'=>$wm['primary'],'color2'=>$wm['accent'],'font_css'=>$wm['font_css']
    ]
  ];
}

$slugs=$onlyProject!==''?($onlyProject&&project_dir($onlyProject)?[$onlyProject]:[]):loom_all_project_slugs();
foreach($slugs as $slug){
  $dir=project_dir($slug);if(!$dir)continue;$baseFile=loom_project_base_file($slug);if(!$baseFile)continue;
  $data=loom_project_effective_data($slug);if(!$data)continue;
  if(!loom_request_is_admin()&&$requestClientId!==''&&!loom_project_access_status($slug,$requestClientId)['allowed'])continue;
  $colors=loom_project_brand_colors($slug);$socialColor=loom_brand_hex($data['social_color']??null,$colors['primary']);$rawBio=trim((string)($data['bio']??''));$bio=$rawBio!==''?$rawBio:loom_project_fallback_bio_from_data($data,$slug);
  $projectRole=$requestClientId!==''?loom_access_project_role($requestClientId,$slug):(loom_request_is_admin()?'system-owner':'member');
  $projectCaps=$requestClientId!==''?loom_access_effective_capabilities($requestClientId,$slug):[];
  $canProjectAdmin=in_array($projectRole,['system-owner','loom-admin','project-admin','project-manager'],true)||array_intersect($projectCaps,['project.settings','project.modules','project.content','project.users','project.access']);
  $projects[]=['slug'=>$slug,'name'=>$data['name']??humanize_project_slug($slug),'tagline'=>$data['tagline']??'','description'=>$data['description']??'','bio'=>$bio,'bio_custom'=>$rawBio,'bio_is_fallback'=>$rawBio==='','brand_primary_color'=>$colors['primary'],'brand_accent_color'=>$colors['accent'],'social_color'=>$socialColor,'wordmark'=>loom_project_brand_identity($slug),'theme'=>$data['theme']??'default','version'=>$data['version']??'0.0.0','source'=>loom_project_source($slug),'branding'=>loom_home_project_branding($slug,$dir,$data),'app_url'=>loom_project_public_url($slug),'canonical_app_url'=>loom_project_app_url($slug),'domain_landing'=>loom_project_is_domain_landing($slug),'project_role'=>$projectRole,'project_capabilities'=>$projectCaps,'can_project_admin'=>(bool)$canProjectAdmin,'pegboard_url'=>"pegboard/?project=$slug&v=".rawurlencode($release),'registry_url'=>"registry/?project=$slug&v=".rawurlencode($release)];
}
usort($projects,fn($a,$b)=>strcasecmp($a['name'],$b['name']));
$isAdmin=function_exists('loom_request_is_admin')&&loom_request_is_admin();
json_out(['engine'=>'LOOM','engine_version'=>$release,'domain_routing'=>loom_domain_routing_payload(),'projects'=>$projects,'archived_projects'=>($isAdmin&&$onlyProject==='')?list_archived_projects():[],'admin'=>$isAdmin]);
