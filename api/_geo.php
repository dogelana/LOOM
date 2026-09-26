<?php
// @loom-file release=0.15.57 revision=5 policy=package-priority
// Admin-facing approximate IP geolocation enrichment. Geolocation is presentation
// metadata only and is never used to authenticate, merge, or select an identity.
declare(strict_types=1);

function loom_geo_settings_file(): string { ensure_dir(loom_data_dir().'/admin'); return loom_data_dir().'/admin/network-location.json'; }
function loom_geo_cache_file(): string { ensure_dir(loom_data_dir().'/users'); return loom_data_dir().'/users/ip-geo-cache.json'; }
function loom_geo_settings(): array {
  $s=read_json_file(loom_geo_settings_file())?:[];
  return array_replace(['schemaVersion'=>'1.0','remoteLookupEnabled'=>true,'provider'=>'ipwho.is','cacheDays'=>14],$s);
}
function loom_geo_write_settings(array $incoming): array {
  $s=loom_geo_settings();
  if(array_key_exists('remoteLookupEnabled',$incoming))$s['remoteLookupEnabled']=(bool)$incoming['remoteLookupEnabled'];
  if(array_key_exists('cacheDays',$incoming))$s['cacheDays']=max(1,min(90,(int)$incoming['cacheDays']));
  $s['provider']='ipwho.is';$s['updatedAt']=server_timestamp();
  @file_put_contents(loom_geo_settings_file(),json_encode($s,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n",LOCK_EX);
  return $s;
}
function loom_geo_cache(): array { return array_replace(['schemaVersion'=>'1.0','items'=>[]],read_json_file(loom_geo_cache_file())?:[]); }
function loom_geo_write_cache(array $s): void { $s['schemaVersion']='1.0';$s['updatedAt']=server_timestamp();@file_put_contents(loom_geo_cache_file(),json_encode($s,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n",LOCK_EX); }
function loom_geo_flag(string $cc): string {
  $cc=strtoupper(trim($cc));if(!preg_match('/^[A-Z]{2}$/',$cc))return '';
  $out='';foreach(str_split($cc) as $ch){$cp=127397+ord($ch);if(function_exists('mb_chr'))$out.=mb_chr($cp,'UTF-8');}
  return $out;
}
function loom_geo_normalize(array $r,string $source='headers'): array {
  $cc=strtoupper(trim((string)($r['countryCode']??$r['country_code']??'')));if(!preg_match('/^[A-Z]{2}$/',$cc))$cc='';
  $country=trim((string)($r['country']??''));$region=trim((string)($r['region']??''));$city=trim((string)($r['city']??''));
  $lat=$r['latitude']??null;$lon=$r['longitude']??null;$tz=$r['timezone']??null;$isp=$r['isp']??($r['connection']['isp']??null);$asn=$r['asn']??($r['connection']['asn']??null);
  $parts=array_values(array_filter([$city,$region,$country],fn($v)=>trim((string)$v)!==''));
  $pretty=implode(', ',$parts);$flag=loom_geo_flag($cc);if($pretty!==''&&$flag!=='')$pretty=$flag.' '.$pretty;elseif($pretty==='')$pretty=$flag!==''?$flag.' '.$cc:'Location unavailable';
  return ['countryCode'=>$cc?:null,'country'=>$country?:null,'region'=>$region?:null,'city'=>$city?:null,'latitude'=>is_numeric($lat)?(float)$lat:null,'longitude'=>is_numeric($lon)?(float)$lon:null,'timezone'=>is_string($tz)?$tz:null,'isp'=>is_string($isp)?$isp:null,'asn'=>is_string($asn)||is_numeric($asn)?(string)$asn:null,'pretty'=>$pretty,'source'=>$source];
}
function loom_geo_lookup_remote(string $ip): ?array {
  $ip=loom_valid_ip($ip);if(!$ip||!loom_ip_is_public($ip))return null;$settings=loom_geo_settings();if(empty($settings['remoteLookupEnabled']))return null;
  $cache=loom_geo_cache();$key=hash('sha256',$ip);$row=$cache['items'][$key]??null;$ttl=max(1,(int)$settings['cacheDays'])*86400;
  if(is_array($row)&&!empty($row['fetchedEpoch'])&&(time()-(int)$row['fetchedEpoch'])<$ttl&&is_array($row['geo']??null))return $row['geo'];
  $url='https://ipwho.is/'.rawurlencode($ip);
  $ctx=stream_context_create(['http'=>['timeout'=>2.5,'ignore_errors'=>true,'header'=>"User-Agent: LOOM-IP-Geolocation/0.15.57\r\nAccept: application/json\r\n"]]);
  $raw=@file_get_contents($url,false,$ctx);if(!is_string($raw)||$raw==='')return null;$j=json_decode($raw,true);if(!is_array($j)||isset($j['success'])&&$j['success']===false)return null;
  $geo=loom_geo_normalize($j,'ipwho.is');$cache['items'][$key]=['ip'=>$ip,'fetchedAt'=>server_timestamp(),'fetchedEpoch'=>time(),'geo'=>$geo];if(count($cache['items'])>2000)$cache['items']=array_slice($cache['items'],-1500,null,true);loom_geo_write_cache($cache);return $geo;
}
function loom_geo_for_ip_row(array $row,bool $allowRemote=true): array {
  $ip=loom_valid_ip((string)($row['ip']??''));$hint=loom_geo_normalize(is_array($row['geoHint']??null)?$row['geoHint']:[],'server headers');
  $hasHint=($hint['countryCode']??null)||($hint['country']??null)||($hint['region']??null)||($hint['city']??null);$geo=$hasHint?$hint:null;
  // Cached remote labels are safe to reuse on list views without making network
  // calls. Only a detail view with allowRemote=true may perform a fresh lookup.
  if($ip){$cache=loom_geo_cache();$cached=$cache['items'][hash('sha256',$ip)]['geo']??null;if(is_array($cached))$geo=$cached;}
  if($allowRemote&&$ip){$remote=loom_geo_lookup_remote($ip);if($remote)$geo=$remote;}
  return $geo?:['countryCode'=>null,'country'=>null,'region'=>null,'city'=>null,'latitude'=>null,'longitude'=>null,'timezone'=>null,'isp'=>null,'asn'=>null,'pretty'=>'Location unavailable','source'=>'none'];
}
function loom_identity_ip_history_enriched(string $ownerType,string $ownerId,bool $allowRemote=true,int $remoteLimit=5): array {
  $rows=loom_identity_ip_history($ownerType,$ownerId);$n=0;foreach($rows as &$r){$remote=$allowRemote&&$n<$remoteLimit&&loom_ip_is_public((string)($r['ip']??''));$r['geo']=loom_geo_for_ip_row($r,$remote);$r['prettyLocation']=$r['geo']['pretty']??'Location unavailable';$r['networkKey']=$r['networkKey']??loom_ip_network_key((string)($r['ip']??''));$r['networkLabel']=$r['networkLabel']??loom_ip_network_label((string)($r['ip']??''));if($remote)$n++;}unset($r);return $rows;
}
function loom_geo_status(): array { $s=loom_geo_settings();$c=loom_geo_cache();$obs=loom_request_ip_observation();return ['settings'=>$s,'cachedLocations'=>count($c['items']??[]),'requestObservation'=>['source'=>$obs['source']??null,'version'=>$obs['version']??null,'public'=>(bool)($obs['public']??false),'networkLabel'=>!empty($obs['ip'])?loom_ip_network_label((string)$obs['ip']):null],'note'=>'Approximate IP geolocation is for Admin display only. City/region/country are never used to authenticate, merge, or force guest identity.']; }
function loom_geo_clear_cache(): int { $c=loom_geo_cache();$n=count($c['items']??[]);loom_geo_write_cache(['schemaVersion'=>'1.0','items'=>[]]);return $n; }
