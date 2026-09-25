<?php
// @loom-file release=0.15.49 revision=2 policy=package-priority
declare(strict_types=1);

/** LOOM global system email + password recovery. Persistent state lives only in /instance. */
function loom_email_dir(): string { return loom_instance_ensure_dir(loom_data_dir().'/email'); }
function loom_email_config_file(): string { return loom_email_dir().'/settings.json'; }
function loom_email_log_file(): string { return loom_email_dir().'/delivery-log.json'; }
function loom_email_reset_file(): string { return loom_email_dir().'/password-resets.json'; }
function loom_email_rate_file(): string { return loom_email_dir().'/reset-rate.json'; }
function loom_email_mutate_json(string $file,array $default,callable $fn): mixed {
  ensure_dir(dirname($file));$fh=@fopen($file,'c+');if(!$fh)throw new RuntimeException('LOOM email state is unavailable.');
  try{if(!flock($fh,LOCK_EX))throw new RuntimeException('LOOM email state lock failed.');rewind($fh);$raw=stream_get_contents($fh);$state=$raw!==false&&trim($raw)!==''?json_decode($raw,true):null;if(!is_array($state))$state=$default;$state=array_replace($default,$state);$result=$fn($state);$state['updatedAt']=server_timestamp();$json=json_encode($state,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n";rewind($fh);ftruncate($fh,0);fwrite($fh,$json);fflush($fh);flock($fh,LOCK_UN);return $result;}finally{fclose($fh);}
}
function loom_email_defaults(): array {
  return [
    'schemaVersion'=>'1.0','enabled'=>false,'transport'=>'smtp','siteName'=>'LOOM','baseUrl'=>'',
    'fromName'=>'LOOM','fromEmail'=>'','replyTo'=>'','footerText'=>'Sent by LOOM. You can ignore emails you did not request.',
    'smtp'=>['host'=>'','port'=>587,'encryption'=>'starttls','username'=>'','password'=>'','auth'=>true,'timeout'=>10],
    'events'=>[
      'welcome'=>true,'passwordReset'=>true,'passwordChanged'=>true,'emailChanged'=>true,
      'referral'=>true,'newSignIn'=>false
    ],
    'subjects'=>[
      'welcome'=>'Welcome to {{site_name}}',
      'passwordReset'=>'Reset your {{site_name}} password',
      'passwordChanged'=>'Your {{site_name}} password was changed',
      'emailChanged'=>'Your {{site_name}} email address was updated',
      'referral'=>'You referred someone to {{site_name}}',
      'newSignIn'=>'New sign-in to {{site_name}}'
    ]
  ];
}
function loom_email_settings(bool $includeSecret=false): array {
  $raw=read_json_file(loom_email_config_file())?:[];$d=array_replace_recursive(loom_email_defaults(),is_array($raw)?$raw:[]);
  $d['enabled']=(bool)$d['enabled'];$d['transport']=in_array($d['transport'],['smtp','php-mail'],true)?$d['transport']:'smtp';
  $d['smtp']['port']=max(1,min(65535,(int)($d['smtp']['port']??587)));$d['smtp']['timeout']=max(3,min(30,(int)($d['smtp']['timeout']??10)));
  $d['smtp']['encryption']=in_array((string)($d['smtp']['encryption']??''),['starttls','ssl','none'],true)?(string)$d['smtp']['encryption']:'starttls';
  if(!$includeSecret){$d['smtp']['passwordSaved']=trim((string)($d['smtp']['password']??''))!=='';$d['smtp']['password']='';}
  return $d;
}
function loom_email_write_settings(array $incoming): array {
  $current=loom_email_settings(true);$defaults=loom_email_defaults();
  $transport=in_array((string)($incoming['transport']??$current['transport']),['smtp','php-mail'],true)?(string)($incoming['transport']??$current['transport']):'smtp';
  $enc=in_array((string)($incoming['smtp']['encryption']??$current['smtp']['encryption']),['starttls','ssl','none'],true)?(string)($incoming['smtp']['encryption']??$current['smtp']['encryption']):'starttls';
  $password=(string)($incoming['smtp']['password']??'');if($password==='')$password=(string)($current['smtp']['password']??'');
  $clean=[
    'schemaVersion'=>'1.0','enabled'=>(bool)($incoming['enabled']??false),'transport'=>$transport,
    'siteName'=>loom_email_clean_text((string)($incoming['siteName']??$current['siteName']??'LOOM'),80,'LOOM'),
    'baseUrl'=>loom_email_clean_url((string)($incoming['baseUrl']??$current['baseUrl']??'')),
    'fromName'=>loom_email_clean_text((string)($incoming['fromName']??$current['fromName']??'LOOM'),120,'LOOM'),
    'fromEmail'=>loom_email_clean_address((string)($incoming['fromEmail']??$current['fromEmail']??''),true),
    'replyTo'=>loom_email_clean_address((string)($incoming['replyTo']??$current['replyTo']??''),true),
    'footerText'=>loom_email_clean_text((string)($incoming['footerText']??$current['footerText']??$defaults['footerText']),500,$defaults['footerText']),
    'smtp'=>[
      'host'=>trim((string)($incoming['smtp']['host']??$current['smtp']['host']??'')),
      'port'=>max(1,min(65535,(int)($incoming['smtp']['port']??$current['smtp']['port']??587))),
      'encryption'=>$enc,'username'=>trim((string)($incoming['smtp']['username']??$current['smtp']['username']??'')),
      'password'=>$password,'auth'=>(bool)($incoming['smtp']['auth']??$current['smtp']['auth']??true),
      'timeout'=>max(3,min(30,(int)($incoming['smtp']['timeout']??$current['smtp']['timeout']??10)))
    ],
    'events'=>[],'subjects'=>[],'updatedAt'=>server_timestamp()
  ];
  foreach($defaults['events'] as $k=>$v)$clean['events'][$k]=(bool)($incoming['events'][$k]??$current['events'][$k]??$v);
  foreach($defaults['subjects'] as $k=>$v)$clean['subjects'][$k]=loom_email_clean_text((string)($incoming['subjects'][$k]??$current['subjects'][$k]??$v),180,$v);
  ensure_dir(dirname(loom_email_config_file()));if(@file_put_contents(loom_email_config_file(),json_encode($clean,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT)."\n",LOCK_EX)===false)throw new RuntimeException('Could not save email settings.');
  return loom_email_settings(false);
}
function loom_email_clean_text(string $v,int $max,string $fallback=''): string {$v=trim(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u','',$v)??'');if(function_exists('mb_substr'))$v=mb_substr($v,0,$max,'UTF-8');else $v=substr($v,0,$max);return $v!==''?$v:$fallback;}
function loom_email_clean_address(string $v,bool $allowEmpty=false): string {$v=trim($v);if($v===''&&$allowEmpty)return '';if(!filter_var($v,FILTER_VALIDATE_EMAIL))throw new RuntimeException('Enter a valid email address.');return $v;}
function loom_email_clean_url(string $v): string {$v=rtrim(trim($v),'/');if($v==='')return '';if(!filter_var($v,FILTER_VALIDATE_URL)||!preg_match('~^https?://~i',$v))throw new RuntimeException('Base URL must be an http:// or https:// URL.');return $v;}
function loom_email_base_url(array $cfg): string {if(trim((string)($cfg['baseUrl']??''))!=='')return rtrim((string)$cfg['baseUrl'],'/');$base=rtrim(web_base_path(),'/');return rtrim(loom_request_origin(),'/').($base!==''?$base:'');}
function loom_email_subject(array $cfg,string $event,array $vars=[]): string {$s=(string)($cfg['subjects'][$event]??loom_email_defaults()['subjects'][$event]??'LOOM notification');$vars=['site_name'=>(string)($cfg['siteName']??'LOOM')]+$vars;foreach($vars as $k=>$v)$s=str_replace('{{'.$k.'}}',(string)$v,$s);return loom_email_clean_text($s,180,'LOOM notification');}
function loom_email_escape(string $v): string {return htmlspecialchars($v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function loom_email_template(array $cfg,string $title,string $lead,string $body,string $buttonText='',string $buttonUrl=''): array {
  $site=loom_email_escape((string)($cfg['siteName']??'LOOM'));$titleE=loom_email_escape($title);$leadE=loom_email_escape($lead);$footer=loom_email_escape((string)($cfg['footerText']??''));$button='';
  if($buttonText!==''&&$buttonUrl!=='')$button='<p style="margin:24px 0"><a href="'.loom_email_escape($buttonUrl).'" style="display:inline-block;background:#173f27;color:#fff;text-decoration:none;padding:12px 18px;border-radius:10px;font-weight:800">'.loom_email_escape($buttonText).'</a></p>';
  $html='<!doctype html><html><body style="margin:0;background:#f3f7f4;font-family:Inter,Arial,sans-serif;color:#173021"><div style="max-width:620px;margin:0 auto;padding:28px 16px"><div style="font-size:12px;font-weight:900;letter-spacing:.12em;color:#28784a">'.$site.'</div><div style="margin-top:10px;background:#fff;border:1px solid #dce9df;border-radius:18px;padding:24px"><h1 style="margin:0 0 8px;font-size:25px">'.$titleE.'</h1><p style="color:#617066;line-height:1.55">'.$leadE.'</p>'.$body.$button.'</div><p style="color:#7b887f;font-size:11px;line-height:1.5;padding:0 8px">'.$footer.'</p></div></body></html>';
  $text=$title."\n\n".$lead."\n\n".trim(strip_tags(str_replace(['<br>','<br/>','<br />'],"\n",$body))).($buttonUrl!==''?"\n\n".$buttonText.': '.$buttonUrl:'').($footer!==''?"\n\n".$footer:'');
  return ['html'=>$html,'text'=>$text];
}
function loom_email_render_event(array $cfg,string $event,array $vars=[]): array {
  $site=(string)($cfg['siteName']??'LOOM');$user=trim((string)($vars['username']??''));$greet=$user!==''?'Hi '.$user.',':'Hello,';$base=loom_email_base_url($cfg);
  if($event==='welcome')return loom_email_template($cfg,'Welcome to '.$site,$greet.' your permanent LOOM account is ready.','<p style="line-height:1.6;color:#334a3a">Your profile, guest history, project identities, and future sign-ins can now follow your permanent account.</p>','Open '.$site,$base.'/home/');
  if($event==='passwordReset')return loom_email_template($cfg,'Reset your password',$greet.' a password reset was requested for your account.','<p style="line-height:1.6;color:#334a3a">This link expires in 30 minutes and can be used once. If you did not request it, you can ignore this email.</p>','Reset password',(string)($vars['resetUrl']??''));
  if($event==='passwordChanged')return loom_email_template($cfg,'Password changed',$greet.' your '.$site.' password was changed.','<p style="line-height:1.6;color:#334a3a">Existing sign-in sessions were revoked. If this was not you, contact the site administrator immediately and reset your password.</p>','Open '.$site,$base.'/home/');
  if($event==='emailChanged')return loom_email_template($cfg,'Email address updated',$greet.' the email address on your '.$site.' account was updated.','<p style="line-height:1.6;color:#334a3a">This address is now used for account notices and password recovery.</p>','Open '.$site,$base.'/home/');
  if($event==='referral'){$who=loom_email_escape((string)($vars['referredUsername']??'A new visitor'));return loom_email_template($cfg,'New referral',$greet.' someone used your referral link.','<p style="line-height:1.6;color:#334a3a"><b>'.$who.'</b> was attributed to your reusable referral link. The link remains active for future referrals.</p>','Open '.$site,$base.'/home/');}
  if($event==='newSignIn')return loom_email_template($cfg,'New sign-in',$greet.' your '.$site.' account was signed in.','<p style="line-height:1.6;color:#334a3a">If this was you, no action is needed. This notification can be disabled by the LOOM administrator.</p>','Open '.$site,$base.'/home/');
  return loom_email_template($cfg,$site.' notification',$greet,'<p style="line-height:1.6;color:#334a3a">There is a new notification from '.$site.'.</p>');
}
function loom_email_mask(string $email): string {$p=explode('@',$email,2);if(count($p)!==2)return '***';$name=$p[0];return substr($name,0,min(2,strlen($name))).str_repeat('*',max(2,strlen($name)-2)).'@'.$p[1];}
function loom_email_log(array $row): void {loom_email_mutate_json(loom_email_log_file(),['schemaVersion'=>'1.0','entries'=>[]],function(array &$s)use($row){$s['entries']=is_array($s['entries']??null)?$s['entries']:[];array_unshift($s['entries'],['id'=>'mail_'.bin2hex(random_bytes(6)),'createdAt'=>server_timestamp()]+$row);$s['entries']=array_slice($s['entries'],0,200);return null;});}
function loom_email_logs(int $limit=40): array {$s=read_json_file(loom_email_log_file())?:[];return array_slice(is_array($s['entries']??null)?$s['entries']:[],0,max(1,min(200,$limit)));}
function loom_email_header(string $v): string {return trim(str_replace(["\r","\n"],' ',$v));}
function loom_email_send(string $to,string $subject,string $html,string $text='',string $event='system-test'): array {
  $to=loom_email_clean_address($to);$cfg=loom_email_settings(true);$started=microtime(true);$result=['ok'=>false,'transport'=>$cfg['transport'],'message'=>'Email delivery is disabled.'];
  if(!$cfg['enabled']&&$event!=='system-test'){$result['skipped']=true;loom_email_log(['event'=>$event,'to'=>loom_email_mask($to),'success'=>false,'skipped'=>true,'message'=>$result['message']]);return $result;}
  if($cfg['fromEmail']==='')throw new RuntimeException('System email From address is not configured.');
  try{
    if($cfg['transport']==='php-mail'){
      if(!function_exists('mail'))throw new RuntimeException('PHP mail() is unavailable on this server.');
      $boundary='loom_'.bin2hex(random_bytes(10));$headers=['MIME-Version: 1.0','From: '.loom_email_header($cfg['fromName']).' <'.loom_email_header($cfg['fromEmail']).'>'];if($cfg['replyTo']!=='')$headers[]='Reply-To: '.loom_email_header($cfg['replyTo']);$headers[]='Content-Type: multipart/alternative; boundary="'.$boundary.'"';
      $body="--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$text\r\n--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$html\r\n--$boundary--\r\n";
      if(!@mail($to,loom_email_header($subject),$body,implode("\r\n",$headers)))throw new RuntimeException('PHP mail() rejected the message.');
      $result=['ok'=>true,'transport'=>'php-mail','message'=>'Accepted by PHP mail().'];
    }else $result=loom_email_smtp_send($cfg,$to,$subject,$html,$text);
  }catch(Throwable $e){$result=['ok'=>false,'transport'=>$cfg['transport'],'message'=>$e->getMessage()];}
  $result['durationMs']=(int)round((microtime(true)-$started)*1000);loom_email_log(['event'=>$event,'to'=>loom_email_mask($to),'success'=>(bool)$result['ok'],'transport'=>$result['transport'],'message'=>$result['message'],'durationMs'=>$result['durationMs']]);return $result;
}
function loom_email_smtp_read($fp,array $okCodes): string {$buf='';while(($line=fgets($fp,2048))!==false){$buf.=$line;if(strlen($line)<4||$line[3]!=='-')break;}if($buf==='')throw new RuntimeException('SMTP server closed the connection.');$code=(int)substr($buf,0,3);if(!in_array($code,$okCodes,true))throw new RuntimeException('SMTP '.$code.': '.trim(preg_replace('/^\d{3}[ -]?/m','',$buf)));return $buf;}
function loom_email_smtp_cmd($fp,string $cmd,array $okCodes): string {if(fwrite($fp,$cmd."\r\n")===false)throw new RuntimeException('SMTP write failed.');return loom_email_smtp_read($fp,$okCodes);}
function loom_email_smtp_open(array $cfg,bool $authenticate=true): array {
  $s=$cfg['smtp'];$host=trim((string)$s['host']);$port=(int)$s['port'];if($host==='')throw new RuntimeException('SMTP host is not configured.');$enc=(string)$s['encryption'];$target=($enc==='ssl'?'ssl://':'').$host; $errno=0;$err='';$fp=@stream_socket_client($target.':'.$port,$errno,$err,(float)$s['timeout'],STREAM_CLIENT_CONNECT);if(!$fp)throw new RuntimeException('SMTP connection failed: '.($err?:('error '.$errno)));stream_set_timeout($fp,(int)$s['timeout']);loom_email_smtp_read($fp,[220]);$helo=preg_replace('/[^a-zA-Z0-9.-]/','',(string)($_SERVER['SERVER_NAME']??'localhost'))?:'localhost';$caps=loom_email_smtp_cmd($fp,'EHLO '.$helo,[250]);
  if($enc==='starttls'){if(stripos($caps,'STARTTLS')===false){fclose($fp);throw new RuntimeException('SMTP server does not advertise STARTTLS.');}loom_email_smtp_cmd($fp,'STARTTLS',[220]);if(!@stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT)){fclose($fp);throw new RuntimeException('SMTP TLS negotiation failed.');}$caps=loom_email_smtp_cmd($fp,'EHLO '.$helo,[250]);}
  if($authenticate&&(bool)$s['auth']){$user=(string)$s['username'];$pass=(string)$s['password'];if($user===''||$pass===''){fclose($fp);throw new RuntimeException('SMTP authentication is enabled but username/password are incomplete.');}loom_email_smtp_cmd($fp,'AUTH LOGIN',[334]);loom_email_smtp_cmd($fp,base64_encode($user),[334]);loom_email_smtp_cmd($fp,base64_encode($pass),[235]);}
  return [$fp,$caps];
}
function loom_email_smtp_send(array $cfg,string $to,string $subject,string $html,string $text): array {[$fp]=loom_email_smtp_open($cfg,true);try{$from=(string)$cfg['fromEmail'];loom_email_smtp_cmd($fp,'MAIL FROM:<'.$from.'>',[250]);loom_email_smtp_cmd($fp,'RCPT TO:<'.$to.'>',[250,251]);loom_email_smtp_cmd($fp,'DATA',[354]);$boundary='loom_'.bin2hex(random_bytes(10));$headers=['Date: '.date(DATE_RFC2822),'From: '.loom_email_header($cfg['fromName']).' <'.$from.'>','To: <'.$to.'>','Subject: '.loom_email_header($subject),'Message-ID: <'.bin2hex(random_bytes(12)).'@'.preg_replace('/[^a-zA-Z0-9.-]/','',(string)($_SERVER['SERVER_NAME']??'loom.local')).'>','MIME-Version: 1.0','Content-Type: multipart/alternative; boundary="'.$boundary.'"'];if($cfg['replyTo']!=='')$headers[]='Reply-To: '.loom_email_header($cfg['replyTo']);$data=implode("\r\n",$headers)."\r\n\r\n--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$text\r\n--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: 8bit\r\n\r\n$html\r\n--$boundary--\r\n";$data=preg_replace('/(?m)^\./','..',$data);fwrite($fp,$data."\r\n.\r\n");loom_email_smtp_read($fp,[250]);@loom_email_smtp_cmd($fp,'QUIT',[221]);return ['ok'=>true,'transport'=>'smtp','message'=>'SMTP server accepted the message.'];}finally{if(is_resource($fp))fclose($fp);}}
function loom_email_status(bool $probe=false): array {$cfg=loom_email_settings(false);$checks=[];$checks[]=['id'=>'master','ok'=>(bool)$cfg['enabled'],'label'=>$cfg['enabled']?'System email enabled':'System email is disabled'];$checks[]=['id'=>'from','ok'=>filter_var((string)$cfg['fromEmail'],FILTER_VALIDATE_EMAIL)!==false,'label'=>$cfg['fromEmail']!==''?'From address configured':'From address required'];if(!empty($cfg['events']['passwordReset'])){$baseOk=trim((string)$cfg['baseUrl'])!==''&&filter_var((string)$cfg['baseUrl'],FILTER_VALIDATE_URL)!==false;$checks[]=['id'=>'base-url','ok'=>$baseOk,'label'=>$baseOk?'Public base URL configured for secure reset links':'Public base URL required while Forgot Password is enabled'];}if($cfg['transport']==='smtp'){$checks[]=['id'=>'smtp-host','ok'=>trim((string)$cfg['smtp']['host'])!=='','label'=>$cfg['smtp']['host']!==''?'SMTP host configured':'SMTP host required'];$checks[]=['id'=>'socket','ok'=>function_exists('stream_socket_client'),'label'=>'PHP socket client '.(function_exists('stream_socket_client')?'available':'missing')];if(in_array($cfg['smtp']['encryption'],['starttls','ssl'],true))$checks[]=['id'=>'openssl','ok'=>extension_loaded('openssl'),'label'=>'OpenSSL '.(extension_loaded('openssl')?'available':'missing')];}else $checks[]=['id'=>'php-mail','ok'=>function_exists('mail'),'label'=>'PHP mail() '.(function_exists('mail')?'available':'missing')];$probeResult=null;if($probe&&$cfg['transport']==='smtp'&&trim((string)$cfg['smtp']['host'])!==''){try{[$fp,$caps]=loom_email_smtp_open(loom_email_settings(true),true);@loom_email_smtp_cmd($fp,'QUIT',[221]);if(is_resource($fp))fclose($fp);$probeResult=['ok'=>true,'message'=>'Connected and authenticated successfully.','capabilities'=>substr(trim($caps),0,800)];}catch(Throwable $e){$probeResult=['ok'=>false,'message'=>$e->getMessage()];}}$ready=$cfg['enabled']&&count(array_filter($checks,fn($x)=>!$x['ok']))===0;if($probeResult&&!$probeResult['ok'])$ready=false;return ['ready'=>$ready,'settings'=>$cfg,'checks'=>$checks,'probe'=>$probeResult,'recent'=>loom_email_logs(30),'resetRequestsLastHour'=>loom_email_rate_count_recent()];}
function loom_email_send_event(string $event,string $to,array $vars=[]): array {$cfg=loom_email_settings(true);if(empty($cfg['events'][$event]))return ['ok'=>true,'skipped'=>true,'message'=>'Notification disabled by Admin.'];$subject=loom_email_subject($cfg,$event,$vars);$render=loom_email_render_event($cfg,$event,$vars);return loom_email_send($to,$subject,$render['html'],$render['text'],$event);}
function loom_email_user_vars(array $u): array {$uid=(string)($u['user_id']??$u['userId']??'');$username='';if($uid!=='')try{$g=loom_global_profile_get('user',$uid);$username=(string)($g['username']??'');}catch(Throwable $e){}return ['userId'=>$uid,'username'=>$username];}
function loom_email_notify_user(string $event,array $u,array $vars=[]): array {$email=(string)($u['email']??'');if(!filter_var($email,FILTER_VALIDATE_EMAIL))return ['ok'=>true,'skipped'=>true,'message'=>'No deliverable account email.'];return loom_email_send_event($event,$email,$vars+loom_email_user_vars($u));}
function loom_email_notify_referral(array $referrer,array $visitor=[]): void {$uid=(string)($referrer['userId']??'');if($uid==='')return;$u=loom_account_user_by_id($uid);if(!$u)return;try{loom_email_notify_user('referral',$u,['referredUsername'=>(string)($visitor['username']??'A new visitor')]);}catch(Throwable $e){}}
function loom_email_reset_store(): array {return array_replace(['schemaVersion'=>'1.0','tokens'=>[],'updatedAt'=>null],read_json_file(loom_email_reset_file())?:[]);}
function loom_email_write_reset_store(array $s): void {$s['schemaVersion']='1.0';$s['updatedAt']=server_timestamp();@file_put_contents(loom_email_reset_file(),json_encode($s,JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT)."\n",LOCK_EX);}
function loom_email_rate_store(): array {return array_replace(['schemaVersion'=>'1.0','requests'=>[]],read_json_file(loom_email_rate_file())?:[]);}
function loom_email_rate_count_recent(): int {$s=loom_email_rate_store();$cut=time()-3600;$n=0;foreach($s['requests'] as $r)if((int)($r['epoch']??0)>=$cut)$n++;return $n;}
function loom_email_rate_allow(string $emailNorm): bool {$ip=(string)(loom_request_ip_observation()['ip']??'unknown');$now=time();$emailKey=hash('sha256',$emailNorm);$ipKey=hash('sha256',$ip);return (bool)loom_email_mutate_json(loom_email_rate_file(),['schemaVersion'=>'1.0','requests'=>[]],function(array &$s)use($now,$emailKey,$ipKey){$s['requests']=array_values(array_filter(is_array($s['requests']??null)?$s['requests']:[],fn($r)=>(int)($r['epoch']??0)>$now-86400));$emailCount=0;$ipCount=0;foreach($s['requests'] as $r){if((int)($r['epoch']??0)<$now-3600)continue;if(($r['emailKey']??'')===$emailKey)$emailCount++;if(($r['ipKey']??'')===$ipKey)$ipCount++;}$allowed=$emailCount<4&&$ipCount<12;$s['requests'][]=['epoch'=>$now,'emailKey'=>$emailKey,'ipKey'=>$ipKey,'allowed'=>$allowed];$s['requests']=array_slice($s['requests'],-500);return $allowed;});}
function loom_email_request_password_reset(string $email): array {$norm=loom_email_norm($email);$generic=['ok'=>true,'message'=>'If that email belongs to a permanent LOOM account, a password-reset message will be sent if system email is available.'];if(!filter_var($email,FILTER_VALIDATE_EMAIL)){usleep(120000);return $generic;}if(!loom_email_rate_allow($norm)){usleep(120000);return $generic;}$u=loom_account_user_by_email($email);if(!$u){usleep(120000);return $generic;}$cfg=loom_email_settings(true);if(!$cfg['enabled']||empty($cfg['events']['passwordReset'])||trim((string)($cfg['baseUrl']??''))==='')return $generic;$uid=(string)($u['user_id']??$u['userId']??'');if($uid==='')return $generic;$token=bin2hex(random_bytes(32));$now=time();$id='rst_'.bin2hex(random_bytes(8));loom_email_mutate_json(loom_email_reset_file(),['schemaVersion'=>'1.0','tokens'=>[]],function(array &$s)use($id,$uid,$norm,$token,$now){$s['tokens']=is_array($s['tokens']??null)?$s['tokens']:[];foreach($s['tokens'] as $k=>$r)if((int)($r['expiresEpoch']??0)<$now-86400||!empty($r['usedAt']))unset($s['tokens'][$k]);$s['tokens'][$id]=['id'=>$id,'userId'=>$uid,'emailNorm'=>$norm,'tokenHash'=>hash('sha256',$token),'createdAt'=>server_timestamp(),'createdEpoch'=>$now,'expiresEpoch'=>$now+1800,'usedAt'=>null];return null;});$url=loom_email_base_url($cfg).'/reset-password.php?token='.rawurlencode($id.'.'.$token);$res=loom_email_notify_user('passwordReset',$u,['resetUrl'=>$url]);if(empty($res['ok']))loom_email_mutate_json(loom_email_reset_file(),['schemaVersion'=>'1.0','tokens'=>[]],function(array &$s)use($id){unset($s['tokens'][$id]);return null;});return $generic;}
function loom_email_validate_reset_token(string $token): ?array {$parts=explode('.',$token,2);if(count($parts)!==2)return null;[$id,$secret]=$parts;$id=safe_token($id);if($id===''||$secret==='')return null;$s=loom_email_reset_store();$r=$s['tokens'][$id]??null;if(!is_array($r)||!empty($r['usedAt'])||(int)($r['expiresEpoch']??0)<time())return null;if(!hash_equals((string)($r['tokenHash']??''),hash('sha256',$secret)))return null;$u=loom_account_user_by_id((string)$r['userId']);if(!$u)return null;return ['id'=>$id,'record'=>$r,'user'=>$u];}
function loom_email_take_reset_token(string $token): ?array {$parts=explode('.',$token,2);if(count($parts)!==2)return null;[$id,$secret]=$parts;$id=safe_token($id);if($id===''||$secret==='')return null;$now=time();return loom_email_mutate_json(loom_email_reset_file(),['schemaVersion'=>'1.0','tokens'=>[]],function(array &$s)use($id,$secret,$now){$r=$s['tokens'][$id]??null;if(!is_array($r)||!empty($r['usedAt'])||(int)($r['expiresEpoch']??0)<$now||!hash_equals((string)($r['tokenHash']??''),hash('sha256',$secret)))return null;$used=server_timestamp();$uid=(string)($r['userId']??'');foreach($s['tokens'] as &$x)if(($x['userId']??'')===$uid&&empty($x['usedAt']))$x['usedAt']=$used;unset($x);return ['id'=>$id,'record'=>$r];});}
function loom_email_consume_password_reset(string $token,string $password): array {if(strlen($password)<8)throw new RuntimeException('Password must be at least 8 characters.');$v=loom_email_take_reset_token($token);if(!$v)throw new RuntimeException('This reset link is invalid or has expired.');$uid=(string)($v['record']['userId']??'');if(!loom_account_user_by_id($uid))throw new RuntimeException('This reset link is invalid or has expired.');loom_replace_account_password($uid,$password);return ['ok'=>true,'message'=>'Password changed. You can now sign in with your new password.'];}
