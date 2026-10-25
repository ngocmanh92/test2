<?php
declare(strict_types=1);
// Called only after CSRF and action-specific capability checks.
switch($action) {
case 'media_upload':
    $id=mediaUpload($_FILES['image']??[]); audit('media.upload',(string)$id); redirect('/?page=admin&tab=media');
case 'gallery_add':
    $pid=integer('product_id',1); $mid=integer('media_id',1);
    query('INSERT INTO product_images(product_id,media_id,sort_order) VALUES(?,?,?) ON CONFLICT(product_id,media_id) DO UPDATE SET sort_order=excluded.sort_order',[$pid,$mid,integer('sort_order',0,10000)]); audit('gallery.attach',(string)$pid); redirect('/?page=admin&tab=media');
case 'gallery_remove':
    query('DELETE FROM product_images WHERE product_id=? AND media_id=?',[integer('product_id',1),integer('media_id',1)]); audit('gallery.detach'); redirect('/?page=admin&tab=media');
case 'category_save':
    db()->exec('BEGIN IMMEDIATE');
    try {
    $id=integer('id'); $parent=integer('parent_id');
    if($parent) {
        $seen=[$id]; $cursor=$parent;
        while($cursor) { if(in_array($cursor,$seen,true)) throw new DomainException('Danh mục không được tạo vòng lặp.'); $seen[]=$cursor; $row=query('SELECT id,parent_id FROM categories WHERE id=?',[$cursor])->fetch(); if(!$row) throw new DomainException('Danh mục cha không tồn tại.'); $cursor=(int)$row['parent_id']; }
    }
    $args=[field('name',100),$parent?:null,integer('sort_order',0,10000)];
    if($id) { $args[]=$id; query('UPDATE categories SET name=?,parent_id=?,sort_order=? WHERE id=?',$args); }
    else query('INSERT INTO categories(name,parent_id,sort_order) VALUES(?,?,?)',$args);
    audit('category.save',(string)$id); db()->exec('COMMIT');
    } catch(Throwable $ex) { db()->exec('ROLLBACK'); throw $ex; }
    redirect('/?page=admin&tab=categories');
case 'category_delete':
    $id=integer('id',1);
    if(query('SELECT id FROM products WHERE category_id=? UNION ALL SELECT id FROM categories WHERE parent_id=?',[$id,$id])->fetch()) throw new DomainException('Chuyển sản phẩm và danh mục con trước khi xóa.');
    query('DELETE FROM categories WHERE id=?',[$id]); audit('category.delete',(string)$id); redirect('/?page=admin&tab=categories');
case 'content_save':
    $type=field('type',20); $slug=field('slug',100); $status=field('status',20); $image=trim((string)($_POST['image']??''));
    if(!in_array($type,['article','project','award','page'],true) || !in_array($status,['draft','published'],true) || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D',$slug) || !imageUrl($image)) throw new DomainException('Kiểu nội dung, slug hoặc ảnh không hợp lệ.');
    if($type==='page' && !in_array($slug,['about','artist','services','process'],true)) throw new DomainException('Trang tùy chỉnh dùng slug about, artist, services hoặc process.');
    $args=[$type,$slug,field('title',200),field('body',10000),$image,$status,trim((string)($_POST['seo_title']??'')),trim((string)($_POST['seo_description']??''))]; $id=integer('id');
    if($id) { $args[]=$id; query('UPDATE content SET type=?,slug=?,title=?,body=?,image=?,status=?,seo_title=?,seo_description=?,updated_at=CURRENT_TIMESTAMP WHERE id=?',$args); }
    else query('INSERT INTO content(type,slug,title,body,image,status,seo_title,seo_description) VALUES(?,?,?,?,?,?,?,?)',$args);
    audit('content.save',(string)$id); redirect('/?page=admin&tab=content');
case 'content_delete':
    query("UPDATE content SET status='draft' WHERE id=?",[integer('id',1)]); audit('content.unpublish',(string)$_POST['id']); redirect('/?page=admin&tab=content');
case 'quote_line_add':
    db()->exec('BEGIN IMMEDIATE');
    try {
    $id=integer('quote_id',1); $qty=integer('qty',1,1000); $price=integer('price',0,1000000000);
    $total=(int)query('SELECT COALESCE(SUM(qty*price),0) FROM quote_lines WHERE quote_id=?',[$id])->fetchColumn();
    if($total+$qty*$price>1000000000000) throw new DomainException('Giá trị báo giá vượt giới hạn.');
    query('INSERT INTO quote_lines(quote_id,description,qty,price) VALUES(?,?,?,?)',[$id,field('description',500),$qty,$price]); audit('quote.line.add',(string)$id); db()->exec('COMMIT');
    } catch(Throwable $ex) { db()->exec('ROLLBACK'); throw $ex; }
    redirect('/?page=admin&tab=quote_edit&id='.$id);
case 'quote_line_remove':
    db()->exec('BEGIN IMMEDIATE');
    try {
    $id=integer('quote_id',1);
    [$quote,,$subtotal]=quoteTotals($id); $line=query('SELECT qty,price FROM quote_lines WHERE id=? AND quote_id=?',[integer('line_id',1),$id])->fetch();
    if($line && $subtotal-(int)$line['qty']*(int)$line['price']+(int)$quote['shipping']<(int)$quote['deposit']) throw new DomainException('Giảm đặt cọc dự kiến trước khi xóa hạng mục.');
    query('DELETE FROM quote_lines WHERE id=? AND quote_id=?',[integer('line_id',1),$id]); audit('quote.line.remove',(string)$id); db()->exec('COMMIT');
    } catch(Throwable $ex) { db()->exec('ROLLBACK'); throw $ex; }
    redirect('/?page=admin&tab=quote_edit&id='.$id);
case 'quote_detail':
    db()->exec('BEGIN IMMEDIATE');
    try {
    $id=integer('quote_id',1); $shipping=integer('shipping'); $deposit=integer('deposit'); $date=trim((string)($_POST['valid_until']??''));
    if($date!=='' && !validDate($date)) throw new DomainException('Ngày không hợp lệ.');
    [,,$subtotal]=quoteTotals($id); if($deposit>$subtotal+$shipping) throw new DomainException('Đặt cọc không được vượt tổng báo giá.');
    query('UPDATE quote_requests SET shipping=?,deposit=?,valid_until=?,internal_notes=? WHERE id=?',[$shipping,$deposit,$date,substr(trim((string)($_POST['internal_notes']??'')),0,10000),$id]); audit('quote.detail.save',(string)$id); db()->exec('COMMIT');
    } catch(Throwable $ex) { db()->exec('ROLLBACK'); throw $ex; }
    redirect('/?page=admin&tab=quote_edit&id='.$id);
case 'staff_save':
    db()->exec('BEGIN IMMEDIATE');
    try {
    capability('users');
    $id=integer('id'); $level=field('access_level',20); $email=strtolower(field('email')); $name=field('name',100); $active=isset($_POST['active'])?1:0;
    if(!in_array($level,['owner','editor','sales'],true) || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new DomainException('Email hoặc quyền không hợp lệ.');
    $me=requireUser(true); if($id===(int)$me['id'] && (!$active || $level!=='owner')) throw new DomainException('Không thể tự khóa hoặc hạ quyền chủ sở hữu đang đăng nhập.');
    $old=$id?query("SELECT * FROM users WHERE id=? AND role='admin'",[$id])->fetch():null;
    if($id && !$old) throw new DomainException('Không tìm thấy nhân viên.');
    if($old && $old['access_level']==='owner' && $old['active'] && (!$active || $level!=='owner') && (int)query("SELECT COUNT(*) FROM users WHERE role='admin' AND access_level='owner' AND active=1")->fetchColumn()<=1) throw new DomainException('Cần giữ ít nhất một chủ sở hữu hoạt động.');
    $password=(string)($_POST['password']??'');
    if((!$id || $password!=='') && (strlen($password)<12 || strlen($password)>72)) throw new DomainException('Mật khẩu cần 12–72 byte.');
    if($id) {
        query('UPDATE users SET name=?,email=?,access_level=?,active=?,auth_version=auth_version+1 WHERE id=?',[$name,$email,$level,$active,$id]);
        if($password!=='') query('UPDATE users SET password=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$id]);
        if($id===(int)$me['id']) $_SESSION['auth_version']=(int)query('SELECT auth_version FROM users WHERE id=?',[$id])->fetchColumn();
    } else query("INSERT INTO users(name,email,password,role,access_level,active) VALUES(?,?,?,'admin',?,?)",[$name,$email,password_hash($password,PASSWORD_DEFAULT),$level,$active]);
    audit('staff.save',(string)$id); db()->exec('COMMIT');
    } catch(Throwable $ex) { db()->exec('ROLLBACK'); throw $ex; }
    redirect('/?page=admin&tab=users');
case 'totp_start':
    $me=requireUser(); $old=query('SELECT password,totp_secret FROM users WHERE id=?',[$me['id']])->fetch();
    if(!password_verify((string)($_POST['current_password']??''),$old['password']) || $old['totp_secret']!=='') throw new DomainException('Xác nhận mật khẩu hoặc trạng thái 2FA không hợp lệ.');
    $_SESSION['enroll_secret']=base32encode(random_bytes(20)); redirect('/?page=security');
case 'totp_enable':
    $me=requireUser(); $secret=$_SESSION['enroll_secret']??''; $counter=$secret?verifyTotp($secret,field('code',6)):0;
    if(!$counter) throw new DomainException('Mã xác thực không đúng.');
    query('UPDATE users SET totp_secret=?,totp_last=?,auth_version=auth_version+1 WHERE id=?',[$secret,$counter,$me['id']]); $_SESSION['auth_version']=(int)query('SELECT auth_version FROM users WHERE id=?',[$me['id']])->fetchColumn(); unset($_SESSION['enroll_secret']); audit('security.2fa.enable'); redirect('/?page=security');
case 'totp_disable':
    $me=requireUser(); $old=query('SELECT * FROM users WHERE id=?',[$me['id']])->fetch();
    if(!password_verify((string)($_POST['current_password']??''),$old['password']) || !verifyTotp($old['totp_secret'],field('code',6),(int)$old['totp_last'])) throw new DomainException('Mật khẩu hoặc mã 2FA không đúng.');
    query("UPDATE users SET totp_secret='',totp_last=0,auth_version=auth_version+1 WHERE id=?",[$me['id']]); $_SESSION['auth_version']=(int)query('SELECT auth_version FROM users WHERE id=?',[$me['id']])->fetchColumn(); audit('security.2fa.disable'); redirect('/?page=security');
case 'totp_login':
    $pending=$_SESSION['pending_login']??[];
    if(!$pending || time()-(int)$pending['time']>300 || (int)$pending['tries']>=5) { unset($_SESSION['pending_login']); throw new DomainException('Phiên xác thực hết hạn. Đăng nhập lại.'); }
    $attemptKey='2fa:'.(int)$pending['id'];
    $attempt=query('SELECT * FROM login_attempts WHERE email=?',[$attemptKey])->fetch();
    if($attempt && (int)$attempt['locked_until']>time()) throw new DomainException('Tài khoản xác thực bị khóa 15 phút.');
    $_SESSION['pending_login']['tries']++;
    $u=query('SELECT * FROM users WHERE id=?',[$pending['id']])->fetch();
    $counter=$u&&$u['active']&&$u['auth_version']==$pending['version']?verifyTotp($u['totp_secret'],field('code',6),(int)$u['totp_last']):0;
    if(!$counter) {
        $n=$attempt && (int)$attempt['locked_until']===0 ? (int)$attempt['attempts']+1:1;
        query('INSERT INTO login_attempts(email,attempts,locked_until) VALUES(?,?,?) ON CONFLICT(email) DO UPDATE SET attempts=excluded.attempts,locked_until=excluded.locked_until',[$attemptKey,$n,$n>=5?time()+900:0]);
        throw new DomainException('Mã xác thực không đúng.');
    }
    query('DELETE FROM login_attempts WHERE email=?',[$attemptKey]);
    if(query('UPDATE users SET totp_last=? WHERE id=? AND totp_last<?',[$counter,$u['id'],$counter])->rowCount()!==1) throw new DomainException('Mã đã được sử dụng.'); signIn($u); audit('login.2fa'); redirect($u['role']==='admin'?'/?page=admin':'/');
case 'forgot':
    $email=strtolower(field('email')); $u=query('SELECT * FROM users WHERE email=? AND active=1',[$email])->fetch();
    if(time()-(int)($_SESSION['last_reset']??0)<60) throw new DomainException('Vui lòng chờ một phút.'); $_SESSION['last_reset']=time();
    $url=getenv('SHOP_URL')?:'';
    $resetKey='reset:'.hash('sha256',$email);
    $rate=query('SELECT locked_until FROM login_attempts WHERE email=?',[$resetKey])->fetchColumn();
    if(!$rate || (int)$rate<time()) {
    if($u && filter_var($url,FILTER_VALIDATE_URL) && parse_url($url,PHP_URL_SCHEME)==='https' && getenv('MAIL_FROM')) {
        $token=bin2hex(random_bytes(32)); query('DELETE FROM password_resets WHERE user_id=?',[$u['id']]); query('INSERT INTO password_resets(token_hash,user_id,expires_at) VALUES(?,?,?)',[hash('sha256',$token),$u['id'],time()+1800]);
        queueMail($email,'Đặt lại mật khẩu',rtrim($url,'/').'/?page=reset&token='.$token);
        query('INSERT INTO login_attempts(email,attempts,locked_until) VALUES(?,0,?) ON CONFLICT(email) DO UPDATE SET locked_until=excluded.locked_until',[$resetKey,time()+300]);
    }
    }
    $_SESSION['reset_notice']=true; redirect('/?page=forgot');
case 'reset':
    $token=field('reset_token',64); $row=query('SELECT * FROM password_resets WHERE token_hash=? AND expires_at>?',[hash('sha256',$token),time()])->fetch(); $password=field('password',72);
    if(!$row || strlen($password)<12) throw new DomainException('Liên kết không hợp lệ, hết hạn hoặc mật khẩu quá ngắn.');
    db()->beginTransaction();
    try {
        $deleted=query('DELETE FROM password_resets WHERE token_hash=? AND expires_at>?',[hash('sha256',$token),time()])->rowCount();
        if(!$deleted) throw new DomainException('Liên kết đã được sử dụng.');
        query('UPDATE users SET password=?,auth_version=auth_version+1 WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),$row['user_id']]); db()->commit();
    } catch(Throwable $e) { db()->rollBack(); throw $e; }
    unset($_SESSION['uid']); redirect('/?page=login');
case 'backup_download': case 'backup_bundle':
    $path=$action==='backup_bundle'?backupBundle():backupDatabase(); audit('backup.download');
    while(ob_get_level()) ob_end_clean(); header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="backup-'.gmdate('Ymd-His').($action==='backup_bundle'?'.zip':'.sqlite').'"'); header('Cache-Control: private, no-store'); readfile($path); exit;
case 'backup_restore':
    $me=requireUser(true); $password=query('SELECT password FROM users WHERE id=?',[$me['id']])->fetchColumn();
    if(!password_verify((string)($_POST['current_password']??''),$password) || ($_POST['confirm']??'')!=='KHOI PHUC') throw new DomainException('Cần mật khẩu hiện tại và nhập KHOI PHUC.');
    $file=$_FILES['backup']??[];
    if(($file['error']??1)!==0 || ($file['size']??0)>50*1024*1024 || !is_uploaded_file($file['tmp_name'])) throw new DomainException('Backup SQLite tối đa 50 MB.');
    $restoredMedia=[]; $tempFiles=[]; $input=$file['tmp_name'];
    register_shutdown_function(function() use (&$tempFiles): void { foreach($tempFiles as $temp) if(is_file($temp)) @unlink($temp); });
    if(strtolower(pathinfo((string)$file['name'],PATHINFO_EXTENSION))==='zip') {
        if(!class_exists('ZipArchive')) throw new DomainException('Hosting cần extension ZIP.');
        $zip=new ZipArchive(); if($zip->open($input)!==true) throw new DomainException('Archive không hợp lệ.');
        $sum=0; $databaseCount=0;
        try {
            for($i=0;$i<$zip->numFiles;$i++) {
                $stat=$zip->statIndex($i); $name=$stat['name']; $sum+=(int)$stat['size'];
                if($sum>200*1024*1024 || $zip->numFiles>10000) throw new DomainException('Archive giải nén tối đa 200 MB, 10000 tệp.');
                if($name==='database.sqlite') { $databaseCount++; if($stat['size']>50*1024*1024 || $databaseCount>1) throw new DomainException('Database quá lớn hoặc bị lặp.'); }
                elseif(!preg_match('~^media/[a-f0-9]{48}\.(jpg|png|webp)$~D',$name) || $stat['size']>5*1024*1024) throw new DomainException('Archive chứa đường dẫn hoặc tệp không được phép.');
                $temp=tempnam(privateDir('backups'),'restore-'); chmod($temp,0600); $tempFiles[]=$temp;
                $stream=$zip->getStream($name); $out=fopen($temp,'wb');
                if(!$stream || !$out) throw new DomainException('Không đọc được archive.');
                $copied=stream_copy_to_stream($stream,$out,((int)$stat['size'])+1); fclose($stream); fclose($out);
                if($copied!==(int)$stat['size']) throw new DomainException('Kích thước archive không hợp lệ.');
                if($name==='database.sqlite') $input=$temp; else $restoredMedia[basename($name)]=$temp;
            }
            if($databaseCount!==1) throw new DomainException('Archive thiếu database.sqlite.');
        } finally { $zip->close(); }
    }
    $candidate=new PDO('sqlite:'.$input); $candidate->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
    if($candidate->query('PRAGMA integrity_check')->fetchColumn()!=='ok' || $candidate->query('PRAGMA foreign_key_check')->fetch()) throw new DomainException('Backup bị lỗi.');
    $schema=fn(PDO $p)=>$p->query("SELECT type,name,sql FROM sqlite_master WHERE name NOT LIKE 'sqlite_%' ORDER BY type,name")->fetchAll(PDO::FETCH_ASSOC);
    if($schema($candidate)!==$schema(db())) throw new DomainException('Backup khác phiên bản cấu trúc. Chỉ phục hồi backup cùng phiên bản.');
    if(!$candidate->query("SELECT id FROM users WHERE role='admin' AND access_level='owner' AND active=1 LIMIT 1")->fetchColumn()) throw new DomainException('Backup phải có chủ sở hữu hoạt động.');
    foreach($candidate->query('SELECT * FROM media')->fetchAll(PDO::FETCH_ASSOC) as $m) {
        if(!preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/D',$m['filename'])) throw new DomainException('Tên ảnh không hợp lệ.');
        $source=$restoredMedia[$m['filename']]??STORAGE.'/media/'.$m['filename'];
        $info=is_file($source)?@getimagesize($source):false;
        if(!$info || $info['mime']!==$m['mime'] || filesize($source)!==(int)$m['size']) throw new DomainException('Ảnh trong backup bị thiếu hoặc không hợp lệ.');
    }
    $candidate->exec('UPDATE users SET auth_version='.random_int(100000000,2000000000));
    $candidate->exec('DELETE FROM password_resets');
    $candidate->exec("UPDATE notifications SET status='failed',body='' WHERE status='pending'");
    $log=$candidate->prepare('INSERT INTO audit_log(event,target) VALUES(?,?)'); $log->execute(['backup.restored',$me['email']]);
    $candidate=null;
    $target=getenv('SHOP_DB')?:STORAGE.'/shop.sqlite';
    // All web requests hold a shared maintenance lock. Refuse restore when another request is active.
    if(!isset($GLOBALS['maintenance_lock']) || !flock($GLOBALS['maintenance_lock'],LOCK_EX|LOCK_NB)) { flock($GLOBALS['maintenance_lock'],LOCK_SH); throw new DomainException('Có yêu cầu khác đang chạy. Thử lại khi website ít truy cập.'); }
    $current=backupDatabase(); audit('backup.before_restore',basename($current));
    $staged=$target.'.restore-'.bin2hex(random_bytes(8));
    foreach($restoredMedia as $filename=>$temp) { $destination=privateDir('media').'/'.$filename; if(is_file($destination)) { if(!hash_equals(hash_file('sha256',$destination),hash_file('sha256',$temp))) throw new DomainException('Ảnh hiện tại khác nội dung backup; cần đối chiếu trước khi phục hồi.'); } elseif(!copy($temp,$destination)) throw new RuntimeException('Cannot restore media'); else chmod($destination,0600); }
    if(!copy($input,$staged)) throw new RuntimeException('Cannot stage restore'); chmod($staged,0600);
    if(!rename($staged,$target)) throw new RuntimeException('Cannot restore');
    $_SESSION=[]; session_regenerate_id(true); redirect('/?page=login');
}
