<?php
declare(strict_types=1);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if (!hash_equals(csrf(),(string)($_POST['csrf']??''))) { http_response_code(419); exit('Phiên hết hạn. Hãy tải lại trang.'); }
        $action=(string)($_POST['action']??'');
        $actionPermissions=['site_settings'=>'settings','quote_status'=>'quotes','category'=>'categories','product'=>'products','coupon'=>'coupons','coupon_toggle'=>'coupons','status'=>'orders','paid'=>'orders','media_upload'=>'media','gallery_add'=>'media','gallery_remove'=>'media','category_save'=>'categories','category_delete'=>'categories','content_save'=>'content','content_delete'=>'content','quote_line_add'=>'quotes','quote_line_remove'=>'quotes','quote_detail'=>'quotes','staff_save'=>'users','backup_download'=>'backup','backup_bundle'=>'backup','backup_restore'=>'backup'];
        if(isset($actionPermissions[$action])) { capability($actionPermissions[$action]); audit('attempt.'.$action); }
        require ROOT.'/app/admin-actions.php';
        switch ($action) {
            case 'site_settings':
                requireUser(true);
                $keys=['announcement','brand_subtitle','hero_title','hero_tagline','reference_mode','craft_image','brand','phone','address','hero_image','portrait_image','work_image_1','work_image_2','work_image_3','hero_intro','seo_description','policy','notification_email'];
                $values=[];
                foreach($keys as $key) {
                    $value=trim((string)($_POST[$key]??setting($key)));
                    if(strlen($value)>(in_array($key,['hero_intro','policy'],true)?10000:1000)) throw new DomainException('Thông tin cấu hình quá dài.');
                    if(str_contains($key,'image') && $value!=='' && (!imageUrl($value))) throw new DomainException('Ảnh phải là URL HTTPS hợp lệ.');
                    $values[$key]=$value;
                }
                if(!in_array($values['reference_mode'],['','0','1'],true)) throw new DomainException('Ảnh tham khảo: nhập 1 để bật hoặc 0 để tắt.');
                if($values['brand']==='') throw new DomainException('Cần nhập tên thương hiệu.');
                db()->beginTransaction();
                try {
                    foreach($values as $key=>$value) query('INSERT INTO site_settings(key,value) VALUES(?,?) ON CONFLICT(key) DO UPDATE SET value=excluded.value',[$key,$value]);
                    db()->commit();
                } catch(Throwable $ex) { db()->rollBack(); throw $ex; }
                redirect('/?page=admin&tab=settings');
            case 'quote':
                if (!empty($_POST['website'])) throw new DomainException('Không thể gửi yêu cầu.');
                if (time()-(int)($_SESSION['last_quote']??0)<60) throw new DomainException('Vui lòng chờ một phút trước khi gửi lại.');
                $phone=field('phone',20);
                if (!validPhone($phone)) throw new DomainException('Số điện thoại không hợp lệ.');
                if (!isset($_POST['consent'])) throw new DomainException('Cần đồng ý sử dụng thông tin để tư vấn.');
                $pid=integer('product_id');
                if ($pid && !query('SELECT id FROM products WHERE id=? AND active=1',[$pid])->fetch()) throw new DomainException('Mẫu sản phẩm không tồn tại.');
                db()->beginTransaction();
                try {
                    query('INSERT INTO quote_requests(product_id,name,phone,material,dimensions,location,notes) VALUES(?,?,?,?,?,?,?)',[$pid?:null,field('name',100),$phone,field('material',100),field('dimensions',200),field('location',300),field('notes',3000)]);
                    if(filter_var(setting('notification_email'),FILTER_VALIDATE_EMAIL)) queueMail(setting('notification_email'),'Có yêu cầu báo giá mới','Đăng nhập khu quản trị để xem yêu cầu mới.');
                    db()->commit();
                } catch(Throwable $ex) { db()->rollBack(); throw $ex; }
                $_SESSION['last_quote']=time(); $_SESSION['quote_sent']=true; redirect('/?page=quote&sent=1');
            case 'quote_status':
                requireUser(true); $status=field('status',20);
                if (!in_array($status,['new','contacted','quoted','closed'],true)) throw new DomainException('Trạng thái không hợp lệ.');
                query('UPDATE quote_requests SET status=? WHERE id=?',[$status,integer('id',1)]); redirect('/?page=admin&tab=quotes');
            case 'register':
                $name=field('name',100); $email=strtolower(field('email')); $password=field('password',72);
                if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12) throw new DomainException('Email không hợp lệ hoặc mật khẩu dưới 12 ký tự.');
                if (query('SELECT id FROM users WHERE email=?',[$email])->fetch()) throw new DomainException('Email đã được đăng ký.');
                query('INSERT INTO users(name,email,password) VALUES(?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
                signIn(query('SELECT * FROM users WHERE id=?',[(int)db()->lastInsertId()])->fetch()); redirect('/');
            case 'login':
                $email=strtolower(field('email')); $a=query('SELECT * FROM login_attempts WHERE email=?',[$email])->fetch();
                if ($a && (int)$a['locked_until']>time()) throw new DomainException('Thử lại sau 15 phút.');
                $u=query('SELECT * FROM users WHERE email=?',[$email])->fetch();
                $valid=password_verify((string)($_POST['password']??''),$u['password']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
                if (!$u || !$valid || !$u['active']) {
                    $n=$a && (int)$a['locked_until']===0 ? (int)$a['attempts']+1 : 1;
                    query('INSERT INTO login_attempts(email,attempts,locked_until) VALUES(?,?,?) ON CONFLICT(email) DO UPDATE SET attempts=excluded.attempts,locked_until=excluded.locked_until',[$email,$n,$n>=5?time()+900:0]);
                    throw new DomainException('Email hoặc mật khẩu không đúng.');
                }
                query('DELETE FROM login_attempts WHERE email=?',[$email]);
                if($u['totp_secret']!=='') { unset($_SESSION['uid']); session_regenerate_id(true); $_SESSION['pending_login']=['id'=>$u['id'],'version'=>$u['auth_version'],'time'=>time(),'tries'=>0]; redirect('/?page=twofactor'); }
                signIn($u); audit('login.success'); redirect('/');
            case 'logout': $_SESSION=[]; session_regenerate_id(true); redirect('/');
            case 'cart':
                $id=integer('id',1); $qty=integer('qty',0,99);
                $p=query('SELECT * FROM products WHERE id=? AND active=1',[$id])->fetch();
                if (!$p || $qty>(int)$p['stock']) throw new DomainException('Số lượng vượt tồn kho.');
                if ($qty===0) unset($_SESSION['cart'][$id]); else $_SESSION['cart'][$id]=$qty;
                redirect('/?page=cart');
            case 'checkout':
                $u=requireUser(); $phone=field('phone',20);
                if (!validPhone($phone)) throw new DomainException('Số điện thoại không hợp lệ.');
                $id=checkout((int)$u['id'],cart(),field('name',100),$phone,field('address',1000),field('payment',10),strtoupper(trim((string)($_POST['coupon']??''))));
                $_SESSION['cart']=[]; redirect('/?page=order&id='.$id);
            case 'profile':
                $u=requireUser(); $name=field('name',100); $newHash=null;
                if (!empty($_POST['password'])) {
                    $old=query('SELECT password FROM users WHERE id=?',[$u['id']])->fetchColumn();
                    if (!password_verify((string)($_POST['current_password']??''),$old) || (strlen((string)$_POST['password'])<12 || strlen((string)$_POST['password'])>72)) throw new DomainException('Mật khẩu hiện tại không đúng hoặc mật khẩu mới dưới 12 ký tự.');
                    $newHash=password_hash((string)$_POST['password'],PASSWORD_DEFAULT);
                }
                if($newHash!==null) { query('UPDATE users SET name=?,password=?,auth_version=auth_version+1 WHERE id=?',[$name,$newHash,$u['id']]); $_SESSION['auth_version']=(int)query('SELECT auth_version FROM users WHERE id=?',[$u['id']])->fetchColumn(); session_regenerate_id(true); }
                else query('UPDATE users SET name=? WHERE id=?',[$name,$u['id']]);
                redirect('/?page=account');
            case 'category': requireUser(true); query('INSERT INTO categories(name) VALUES(?)',[field('name',100)]); redirect('/?page=admin&tab=categories');
            case 'product':
                requireUser(true); $id=integer('id'); $image=trim((string)($_POST['image']??''));
                if ($image!=='' && (!imageUrl($image))) throw new DomainException('Ảnh phải là URL HTTPS.');
                $args=[integer('category_id',1),field('name',200),field('description',10000),integer('price'),integer('stock',0,1000000),$image,isset($_POST['active'])?1:0];
                if ($id) { $args[]=$id; query('UPDATE products SET category_id=?,name=?,description=?,price=?,stock=?,image=?,active=? WHERE id=?',$args); }
                else query('INSERT INTO products(category_id,name,description,price,stock,image,active) VALUES(?,?,?,?,?,?,?)',$args);
                redirect('/?page=admin&tab=products');
            case 'coupon':
                requireUser(true); $code=strtoupper(field('code',30)); $date=trim((string)($_POST['expires_at']??''));
                if (!preg_match('/^[A-Z0-9_-]+$/',$code) || ($date!=='' && !validDate($date))) throw new DomainException('Mã hoặc ngày hết hạn không hợp lệ.');
                query('INSERT INTO coupons(code,percent,min_total,expires_at) VALUES(?,?,?,?)',[$code,integer('percent',1,100),integer('min_total'),$date?:null]); redirect('/?page=admin&tab=coupons');
            case 'coupon_toggle': requireUser(true); query('UPDATE coupons SET active=1-active WHERE id=?',[integer('id',1)]); redirect('/?page=admin&tab=coupons');
            case 'status': requireUser(true); orderStatus(integer('id',1),field('status',20)); redirect('/?page=order&id='.(int)$_POST['id']);
            case 'paid': requireUser(true); query('UPDATE orders SET paid=1-paid WHERE id=?',[integer('id',1)]); redirect('/?page=order&id='.(int)$_POST['id']);
            default: throw new DomainException('Thao tác không hợp lệ.');
        }
    } catch (DomainException $ex) { $error=$ex->getMessage(); }
    catch (PDOException $ex) { error_log((string)$ex); $error='Không thể lưu dữ liệu. Kiểm tra dữ liệu trùng hoặc danh mục không tồn tại.'; }
}
