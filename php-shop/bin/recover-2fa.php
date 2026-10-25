<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli') exit;
if(count($argv)!==3 || $argv[2]!=='--confirm') { fwrite(STDERR,"Sau khi xác minh chủ tài khoản, chạy: php bin/recover-2fa.php email --confirm\n"); exit(1); }
$lock=fopen(STORAGE.'/maintenance.lock','c'); flock($lock,LOCK_SH);
query("UPDATE users SET totp_secret='',totp_last=0,auth_version=auth_version+1 WHERE email=?",[$argv[1]]);
audit('security.2fa.cli-recovery',$argv[1]); echo "Đã tắt 2FA và thu hồi phiên cho tài khoản được chỉ định.\n";
