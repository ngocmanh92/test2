<?php
declare(strict_types=1);
function canAccess(string $name): bool {
    $u=user(); if(!$u || $u['role']!=='admin') return false;
    $permissions=['owner'=>['dashboard','products','categories','media','content','quotes','orders','customers','coupons','settings','users','audit','backup','notifications'],'editor'=>['dashboard','products','categories','media','content'],'sales'=>['dashboard','quotes','orders','customers','coupons']];
    return in_array($name,$permissions[$u['access_level']]??[],true);
}
function capability(string $name): void {
    requireUser(true);
    if(!canAccess($name)) { http_response_code(403); exit('Bạn không có quyền thực hiện thao tác này.'); }
}
function audit(string $event,string $target=''): void { query('INSERT INTO audit_log(user_id,event,target) VALUES(?,?,?)',[$_SESSION['uid']??null,$event,$target]); }
function imageUrl(string $value): bool {
    return $value==='' || preg_match('~^/media\.php\?id=[1-9][0-9]*$~D',$value)===1 || (filter_var($value,FILTER_VALIDATE_URL) && parse_url($value,PHP_URL_SCHEME)==='https');
}
function privateDir(string $name): string {
    $path=STORAGE.'/'.$name;
    if(!is_dir($path) && !mkdir($path,0700,true) && !is_dir($path)) throw new RuntimeException('Cannot create private directory');
    return $path;
}
function mediaUpload(array $file): int {
    if(($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK || ($file['size']??0)>5*1024*1024) throw new DomainException('Ảnh tối đa 5 MB; kiểm tra tệp tải lên.');
    $tmp=(string)($file['tmp_name']??'');
    $info=@getimagesize($tmp); $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmp);
    $types=['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
    if(!$info || !isset($types[$mime]) || $info['mime']!==$mime || $info[0]*$info[1]>24000000) throw new DomainException('Chỉ nhận JPEG, PNG, WebP hợp lệ tối đa 24 megapixel.');
    $name=bin2hex(random_bytes(24)).'.'.$types[$mime]; $path=privateDir('media').'/'.$name;
    if(!is_uploaded_file($tmp) || !move_uploaded_file($tmp,$path)) throw new RuntimeException('Upload failed');
    chmod($path,0600);
    try { query('INSERT INTO media(filename,original_name,mime,size) VALUES(?,?,?,?)',[$name,substr(basename((string)$file['name']),0,200),$mime,filesize($path)]); return (int)db()->lastInsertId(); }
    catch(Throwable $e) { unlink($path); throw $e; }
}
function base32encode(string $bytes): string {
    $bits=''; foreach(str_split($bytes) as $b) $bits.=str_pad(decbin(ord($b)),8,'0',STR_PAD_LEFT);
    $result=''; foreach(str_split($bits,5) as $chunk) $result.='ABCDEFGHIJKLMNOPQRSTUVWXYZ234567'[bindec(str_pad($chunk,5,'0'))]; return $result;
}
function totpCode(string $secret,int $counter): string {
    $bits=''; foreach(str_split($secret) as $c) { $v=strpos('ABCDEFGHIJKLMNOPQRSTUVWXYZ234567',$c); if($v===false) throw new DomainException('Secret invalid'); $bits.=str_pad(decbin($v),5,'0',STR_PAD_LEFT); }
    $key=''; foreach(str_split($bits,8) as $part) if(strlen($part)===8) $key.=chr(bindec($part));
    $hash=hash_hmac('sha1',pack('N2',0,$counter),$key,true); $offset=ord($hash[19])&15;
    $v=unpack('N',substr($hash,$offset,4))[1]&0x7fffffff;
    return str_pad((string)($v%1000000),6,'0',STR_PAD_LEFT);
}
function verifyTotp(string $secret,string $code,int $last=0): int {
    if(!preg_match('/^[0-9]{6}$/D',$code)) return 0;
    $counter=intdiv(time(),30);
    foreach([-1,0,1] as $delta) if($counter+$delta>$last && hash_equals(totpCode($secret,$counter+$delta),$code)) return $counter+$delta;
    return 0;
}
function signIn(array $u): void { session_regenerate_id(true); $_SESSION['uid']=(int)$u['id']; $_SESSION['auth_version']=(int)$u['auth_version']; unset($_SESSION['pending_login']); }
function queueMail(string $to,string $subject,string $body): void {
    if(!filter_var($to,FILTER_VALIDATE_EMAIL)) return;
    query('INSERT INTO notifications(recipient,subject,body) VALUES(?,?,?)',[$to,$subject,$body]);
}
function backupDatabase(): string {
    $path=privateDir('backups').'/backup-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(6)).'.sqlite';
    db()->exec('VACUUM INTO '.db()->quote($path)); chmod($path,0600); return $path;
}
function quoteTotals(int $id): array {
    $q=query('SELECT * FROM quote_requests WHERE id=?',[$id])->fetch();
    if(!$q) throw new DomainException('Không tìm thấy yêu cầu.');
    $lines=query('SELECT * FROM quote_lines WHERE quote_id=? ORDER BY id',[$id])->fetchAll();
    $subtotal=0; foreach($lines as $line) $subtotal+=(int)$line['qty']*(int)$line['price'];
    return [$q,$lines,$subtotal,$subtotal+(int)$q['shipping']];
}
function backupBundle(): string {
    if(!class_exists('ZipArchive')) throw new DomainException('Cần bật extension ZIP trên hosting để sao lưu cả ảnh.');
    $database=backupDatabase(); $path=substr($database,0,-7).'.zip'; $zip=new ZipArchive();
    if($zip->open($path,ZipArchive::CREATE|ZipArchive::OVERWRITE)!==true) throw new RuntimeException('Cannot create archive');
    try {
        $zip->addFile($database,'database.sqlite');
        foreach(query('SELECT filename FROM media')->fetchAll() as $m) {
            if(!preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/D',$m['filename'])) throw new RuntimeException('Invalid media filename');
            $file=STORAGE.'/media/'.$m['filename'];
            if(!is_file($file)) throw new DomainException('Có ảnh bị thiếu trên đĩa. Kiểm tra trước khi backup.');
            $zip->addFile($file,'media/'.$m['filename']);
        }
    } catch(Throwable $e) { $zip->close(); @unlink($path); throw $e; }
    if(!$zip->close()) throw new RuntimeException('Cannot finish archive'); chmod($path,0600); return $path;
}
