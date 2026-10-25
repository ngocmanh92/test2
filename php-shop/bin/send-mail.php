<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli') exit;
$from=getenv('MAIL_FROM')?:'';
if(!filter_var($from,FILTER_VALIDATE_EMAIL) || strpbrk($from,"\r\n")) { fwrite(STDERR,"Cấu hình MAIL_FROM hợp lệ trước khi chạy.\n"); exit(1); }
if(!function_exists('mail')) { fwrite(STDERR,"Mail transport không khả dụng trong PHP.\n"); exit(1); }
$lock=fopen(STORAGE.'/mail.lock','c'); if(!flock($lock,LOCK_EX|LOCK_NB)) exit;
$maintenance=fopen(STORAGE.'/maintenance.lock','c'); flock($maintenance,LOCK_SH);
foreach(query("SELECT * FROM notifications WHERE status='pending' ORDER BY id LIMIT 100")->fetchAll() as $message) {
    if(!filter_var($message['recipient'],FILTER_VALIDATE_EMAIL) || strpbrk($message['recipient'],"\r\n")) { query("UPDATE notifications SET status='failed',body='' WHERE id=?",[$message['id']]); continue; }
    $subject='=?UTF-8?B?'.base64_encode($message['subject']).'?=';
    $ok=mail($message['recipient'],$subject,$message['body'],['From'=>$from,'Content-Type'=>'text/plain; charset=UTF-8']);
    query('UPDATE notifications SET status=?,body=? WHERE id=?',[$ok?'sent':'failed','',$message['id']]);
    echo '#'.$message['id'].' '.($ok?'accepted':'failed')."\n";
}
