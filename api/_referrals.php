<?php
// @loom-file release=0.15.51 revision=6 policy=package-priority
declare(strict_types=1);

/** LOOM Sharing + Referrals durable Instance Vault store. */
function loom_referral_dir(): string { $d=loom_instance_root().'/referrals';ensure_dir($d);return $d; }
function loom_referral_file(): string { return loom_referral_dir().'/referrals.json'; }
function loom_referral_default_store(): array { return ['schemaVersion'=>'1.1','shares'=>[],'acceptances'=>[],'updatedAt'=>null]; }
function loom_referral_read_store(): array { return array_replace(loom_referral_default_store(),read_json_file(loom_referral_file())?:[]); }
function loom_referral_mutate(callable $fn): mixed {
  $file=loom_referral_file();ensure_dir(dirname($file));$fh=@fopen($file,'c+');if(!$fh)throw new RuntimeException('Referral store is unavailable.');
  try{
    if(!flock($fh,LOCK_EX))throw new RuntimeException('Referral store lock failed.');
    rewind($fh);$raw=stream_get_contents($fh);$store=$raw!==false&&trim($raw)!==''?json_decode($raw,true):null;if(!is_array($store))$store=loom_referral_default_store();$store=array_replace(loom_referral_default_store(),$store);
    $result=$fn($store);$store['schemaVersion']='1.1';$store['updatedAt']=server_timestamp();$json=json_encode($store,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";
    rewind($fh);ftruncate($fh,0);fwrite($fh,$json);fflush($fh);flock($fh,LOCK_UN);return $result;
  } finally { fclose($fh); }
}
function loom_referral_actor(string $clientId,string $project=''): array {
  $clientId=safe_token($clientId);if($clientId==='')throw new RuntimeException('A LOOM identity is required to share.');
  $guest=loom_guest_ensure_for_client($clientId);$gid=(string)($guest['guestId']??'');$user=loom_account_user_for_client($clientId);$uid=(string)($user['user_id']??$user['userId']??$guest['attachedUserId']??'');
  $username='';
  if($uid==='')try{$guestProfile=loom_guest_profile_for_client($clientId);if(is_array($guestProfile))$username=(string)(loom_guest_profile_public($guestProfile)['displayName']??'');}catch(Throwable $e){}
  try{if($username===''&&$project!==''&&project_dir($project)){$pi=loom_project_identity_ensure($project,$clientId,true);$username=(string)($pi['effectiveUsername']??'');}}catch(Throwable $e){}
  if($username==='')try{$username=(string)(loom_global_profile_ensure($clientId)['username']??'');}catch(Throwable $e){}
  return ['clientId'=>$clientId,'guestId'=>$gid?:null,'userId'=>$uid?:null,'username'=>$username?:'LOOM User'];
}
function loom_referral_owner_aliases(array $actor): array {
  $guests=[];$gid=(string)($actor['guestId']??'');if($gid!=='')$guests[$gid]=true;$uid=(string)($actor['userId']??'');
  if($uid!=='')foreach(loom_guest_list_for_user($uid) as $g){$x=(string)($g['canonicalGuestId']??$g['guestId']??'');if($x!=='')$guests[$x]=true;}
  return ['userId'=>$uid?:null,'guestIds'=>array_keys($guests)];
}
function loom_referral_same_owner(array $ref,array $actor): bool {
  $aliases=loom_referral_owner_aliases($actor);$ruid=(string)($ref['userId']??'');$rgid=(string)($ref['guestId']??'');
  return ($aliases['userId']&&$ruid!==''&&hash_equals((string)$aliases['userId'],$ruid))||($rgid!==''&&in_array($rgid,$aliases['guestIds'],true));
}
function loom_referral_safe_target(?string $url,string $project=''): string {
  $project=safe_slug($project);if($project!==''&&project_dir($project))return loom_project_absolute_public_url($project);
  $fallback=loom_absolute_web_url(rtrim(web_base_path(),'/').'/home/')?:'/home/';$url=trim((string)$url);if($url==='')return $fallback;
  $absolute=loom_absolute_web_url($url);if(!$absolute)return $fallback;$p=parse_url($absolute);$origin=parse_url(loom_request_origin());
  if(!is_array($p)||strcasecmp((string)($p['host']??''),(string)($origin['host']??''))!==0)return $fallback;
  $base=rtrim(web_base_path(),'/');$path=(string)($p['path']??'/');if($base!==''&&$base!=='/'&&!str_starts_with($path,$base.'/')&&$path!==$base)return $fallback;
  parse_str((string)($p['query']??''),$q);unset($q['loom_ref']);$clean=loom_request_origin().$path.($q?'?'.http_build_query($q):'').(isset($p['fragment'])?'#'.$p['fragment']:'');return $clean;
}
function loom_referral_new_code(): string { return 'lr_'.bin2hex(random_bytes(12)); }
function loom_referral_create(string $clientId,string $project='',?string $targetUrl=null): array {
  $project=safe_slug($project);$actor=loom_referral_actor($clientId,$project);$target=loom_referral_safe_target($targetUrl,$project);$key=hash('sha256',json_encode([$actor['userId']?:$actor['guestId'],$project,$target]));
  return loom_referral_mutate(function(array &$s)use($actor,$project,$target,$key){
    foreach($s['shares'] as &$row){if(($row['stableKey']??'')===$key&&loom_referral_same_owner((array)($row['referrer']??[]),$actor)){return loom_referral_public_share($row);}}unset($row);
    $code=loom_referral_new_code();$row=['code'=>$code,'stableKey'=>$key,'project'=>$project?:null,'targetUrl'=>$target,'referrer'=>$actor,'clicks'=>0,'uniqueVisitors'=>0,'referrals'=>0,'visitorKeys'=>[],'createdAt'=>server_timestamp(),'updatedAt'=>server_timestamp(),'lastClickedAt'=>null];$s['shares'][$code]=$row;
    loom_audit_record('sharing.link.created',['clientId'=>$actor['clientId'],'userId'=>$actor['userId'],'project'=>$project?:null,'details'=>['code'=>$code,'targetUrl'=>$target]]);return loom_referral_public_share($row);
  });
}
function loom_referral_public_share(array $row): array {
  $ref=(array)($row['referrer']??[]);$base=(string)($row['targetUrl']??'');$sep=str_contains($base,'?')?'&':'?';
  return ['code'=>(string)($row['code']??''),'project'=>$row['project']??null,'targetUrl'=>$base,'shareUrl'=>$base.$sep.'loom_ref='.rawurlencode((string)($row['code']??'')),'referrerUsername'=>(string)($ref['username']??'LOOM User'),'clicks'=>(int)($row['clicks']??0),'uniqueVisitors'=>(int)($row['uniqueVisitors']??0),'referrals'=>(int)($row['referrals']??0),'selfVisits'=>(int)($row['selfVisits']??0),'createdAt'=>$row['createdAt']??null];
}
function loom_referral_hit(string $code,string $visitKey=''): ?array {
  $code=safe_token($code);if($code==='')return null;$visitKey=preg_replace('/[^a-zA-Z0-9_.-]/','',$visitKey)?:'';
  return loom_referral_mutate(function(array &$s)use($code,$visitKey){if(!isset($s['shares'][$code]))return null;$r=&$s['shares'][$code];$r['clicks']=(int)($r['clicks']??0)+1;$r['lastClickedAt']=server_timestamp();$r['updatedAt']=server_timestamp();if($visitKey!==''&&!isset($r['visitorKeys'][$visitKey])){$r['visitorKeys'][$visitKey]=server_timestamp();$r['uniqueVisitors']=(int)($r['uniqueVisitors']??0)+1;}return loom_referral_public_share($r);});
}
function loom_referral_accept(string $code,string $clientId,string $project='',string $path=''): array {
  $code=safe_token($code);$project=safe_slug($project);$actor=loom_referral_actor($clientId,$project);if($code==='')return ['accepted'=>false,'reason'=>'invalid-code'];
  $result=loom_referral_mutate(function(array &$s)use($code,$actor,$project,$path){
    if(!isset($s['shares'][$code]))return ['accepted'=>false,'reason'=>'not-found'];$share=&$s['shares'][$code];$ref=(array)($share['referrer']??[]);if(loom_referral_same_owner($ref,$actor)){$share['selfVisits']=(int)($share['selfVisits']??0)+1;$share['updatedAt']=server_timestamp();loom_audit_record('referral.self-visit',['clientId'=>$actor['clientId'],'userId'=>$actor['userId'],'project'=>$project?:($share['project']??null),'details'=>['shareCode'=>$code]],'Referral owner opened their own reusable share link; no referral credit was consumed.');return ['accepted'=>false,'reason'=>'self-referral','share'=>loom_referral_public_share($share)];}
    $visitorKey=(string)($actor['guestId']?:$actor['userId']?:$actor['clientId']);$acceptKey=hash('sha256',$code.'|'.$visitorKey);if(isset($s['acceptances'][$acceptKey]))return ['accepted'=>false,'reason'=>'already-attributed','share'=>loom_referral_public_share($share),'referrerUsername'=>(string)($ref['username']??'LOOM User')];
    $row=['id'=>'ref_'.substr($acceptKey,0,24),'shareCode'=>$code,'project'=>$project?:($share['project']??null),'path'=>substr($path,0,500),'referrer'=>$ref,'visitor'=>$actor,'createdAt'=>server_timestamp()];$s['acceptances'][$acceptKey]=$row;$share['referrals']=(int)($share['referrals']??0)+1;$share['updatedAt']=server_timestamp();
    loom_audit_record('referral.attributed',['clientId'=>$actor['clientId'],'userId'=>$actor['userId'],'project'=>$row['project'],'details'=>['referralId'=>$row['id'],'shareCode'=>$code,'referrerGuestId'=>$ref['guestId']??null,'referrerUserId'=>$ref['userId']??null]]);
    return ['accepted'=>true,'referral'=>$row,'share'=>loom_referral_public_share($share),'referrerUsername'=>(string)($ref['username']??'LOOM User')];
  });
  if(!empty($result['accepted'])&&function_exists('loom_email_notify_referral'))try{loom_email_notify_referral((array)($result['referral']['referrer']??[]),(array)($result['referral']['visitor']??[]));}catch(Throwable $e){}
  return $result;
}
function loom_referral_stats(string $clientId): array {
  $actor=loom_referral_actor($clientId,'');$aliases=loom_referral_owner_aliases($actor);$s=loom_referral_read_store();$shares=[];$clicks=0;$unique=0;$refs=0;
  foreach($s['shares'] as $r){if(!loom_referral_same_owner((array)($r['referrer']??[]),$actor))continue;$pub=loom_referral_public_share($r);$shares[]=$pub;$clicks+=$pub['clicks'];$unique+=$pub['uniqueVisitors'];$refs+=$pub['referrals'];}
  $referredBy=null;foreach($s['acceptances'] as $a){$v=(array)($a['visitor']??[]);$match=($aliases['userId']&&($v['userId']??null)===$aliases['userId'])||in_array((string)($v['guestId']??''),$aliases['guestIds'],true);if($match){$referredBy=['username'=>(string)($a['referrer']['username']??'LOOM User'),'project'=>$a['project']??null,'createdAt'=>$a['createdAt']??null,'shareCode'=>$a['shareCode']??null];break;}}
  usort($shares,fn($a,$b)=>strcmp((string)($b['createdAt']??''),(string)($a['createdAt']??'')));
  return ['shareLinks'=>count($shares),'clicks'=>$clicks,'uniqueVisitors'=>$unique,'referrals'=>$refs,'selfVisits'=>array_sum(array_column($shares,'selfVisits')),'shares'=>$shares,'referredBy'=>$referredBy];
}
function loom_referral_admin_summary(): array {
  $s=loom_referral_read_store();$shares=[];foreach($s['shares'] as $r)$shares[]=loom_referral_public_share($r);usort($shares,fn($a,$b)=>strcmp((string)($b['createdAt']??''),(string)($a['createdAt']??'')));
  $acc=array_values($s['acceptances']??[]);usort($acc,fn($a,$b)=>strcmp((string)($b['createdAt']??''),(string)($a['createdAt']??'')));
  return ['shareLinks'=>count($shares),'clicks'=>array_sum(array_column($shares,'clicks')),'uniqueVisitors'=>array_sum(array_column($shares,'uniqueVisitors')),'referrals'=>count($acc),'selfVisits'=>array_sum(array_column($shares,'selfVisits')),'shares'=>array_slice($shares,0,250),'recentReferrals'=>array_slice($acc,0,250)];
}
