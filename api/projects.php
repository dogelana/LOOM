<?php
// @loom-file release=0.15.14 revision=15 policy=package-priority
require __DIR__.'/_common.php';
$requestClientId=safe_token((string)($_GET['clientId']??''));if($requestClientId!=='')loom_capture_request_ip($requestClientId,'');

$projects=[];

function loom_home_project_branding(string $slug,string $dir): array {
  $projectMeta=loom_project_effective_data($slug);$projectBrand=is_array($projectMeta['branding']??null)?$projectMeta['branding']:[];
  $logoConfig=[];$actionRoot=$dir.'/actions';
  if(is_dir($actionRoot)){
    $it=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($actionRoot,FilesystemIterator::SKIP_DOTS));
    foreach($it as $file){if(!$file->isFile()||strtolower($file->getFilename())!=='manifest.json')continue;$manifest=read_json_file($file->getPathname());if(!$manifest||($manifest['enabled']??true)===false)continue;if((string)($manifest['action']['id']??'')==='core.ui.load-logo')$logoConfig=loom_module_config_with_admin_overrides($slug,$manifest);}
  }
  $assetPath=(string)($projectBrand['logo_asset']??($logoConfig['assetPath']??'assets/logo.png'));$logoUrl=loom_project_asset_url($slug,$assetPath);$wm=loom_project_brand_identity($slug);
  return [
    'logo_url'=>$logoUrl,'logo_alt'=>(string)($projectBrand['logo_alt']??($logoConfig['alt']??($wm['name'].' logo'))),
    'wordmark'=>[
      'line1'=>$wm['line1'],'line2'=>$wm['line2'],'font_family'=>$wm['font_family'],'font_weight'=>$wm['font_weight'],
      'color1'=>$wm['primary'],'color2'=>$wm['accent'],'font_css'=>$wm['font_css']
    ]
  ];
}

foreach(loom_all_project_slugs() as $slug){
  $dir=project_dir($slug);if(!$dir)continue;$baseFile=loom_project_base_file($slug);if(!$baseFile)continue;
  $data=loom_project_effective_data($slug);if(!$data)continue;
  if(!loom_request_is_admin()&&$requestClientId!==''&&!loom_project_access_status($slug,$requestClientId)['allowed'])continue;
  $colors=loom_project_brand_colors($slug);$socialColor=loom_brand_hex($data['social_color']??null,$colors['primary']);
  $projects[]=['slug'=>$slug,'name'=>$data['name']??humanize_project_slug($slug),'tagline'=>$data['tagline']??'','description'=>$data['description']??'','bio'=>$data['bio']??'','brand_primary_color'=>$colors['primary'],'brand_accent_color'=>$colors['accent'],'social_color'=>$socialColor,'wordmark'=>loom_project_brand_identity($slug),'theme'=>$data['theme']??'default','version'=>$data['version']??'0.0.0','source'=>loom_project_source($slug),'branding'=>loom_home_project_branding($slug,$dir),'app_url'=>loom_project_public_url($slug),'canonical_app_url'=>loom_project_app_url($slug),'domain_landing'=>loom_project_is_domain_landing($slug),'pegboard_url'=>"pegboard/?project=$slug&v=".rawurlencode(loom_release_version()),'registry_url'=>"registry/?project=$slug&v=".rawurlencode(loom_release_version())];
}
usort($projects,fn($a,$b)=>strcasecmp($a['name'],$b['name']));
$isAdmin=function_exists('loom_request_is_admin')&&loom_request_is_admin();
json_out(['engine'=>'LOOM','engine_version'=>loom_release_version(),'domain_routing'=>loom_domain_routing_payload(),'projects'=>$projects,'archived_projects'=>$isAdmin?list_archived_projects():[],'admin'=>$isAdmin]);
