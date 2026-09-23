<?php
// @loom-file release=0.12.11 revision=2 policy=package-priority
declare(strict_types=1);
function loom_audit_dir(): string {$d=loom_data_dir().'/audit';ensure_dir($d);return $d;}
function loom_audit_file(): string {return loom_audit_dir().'/'.gmdate('Y-m-d').'.jsonl';}
function loom_audit_record(string $eventType,array $context=[],string $message=''): array {
  $row=['auditId'=>event_id('audit'),'eventType'=>$eventType,'message'=>$message?:$eventType,'actor'=>['clientId'=>$context['clientId']??null,'userId'=>$context['userId']??null,'guestProfileId'=>$context['guestProfileId']??null],'project'=>$context['project']??null,'causeId'=>$context['causeId']??null,'correlationId'=>$context['correlationId']??event_id('corr'),'createdAt'=>server_timestamp(),'details'=>$context['details']??[]];
  @file_put_contents(loom_audit_file(),json_encode($row,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)."\n",FILE_APPEND|LOCK_EX);
  if(function_exists('loom_db_ready')&&loom_db_ready())try{$st=loom_db_pdo(true)->prepare("INSERT IGNORE INTO loom_audit_events(audit_id,event_type,message_text,client_id,user_id,guest_profile_id,project_slug,cause_id,correlation_id,created_at,details_json) VALUES(?,?,?,?,?,?,?,?,?,?,?)");$st->execute([$row['auditId'],$eventType,$row['message'],$row['actor']['clientId'],$row['actor']['userId'],$row['actor']['guestProfileId'],$row['project'],$row['causeId'],$row['correlationId'],loom_db_dt($row['createdAt']),json_encode($row['details'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)]);}catch(Throwable $e){}
  return $row;
}
