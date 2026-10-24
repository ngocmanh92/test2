<?php
declare(strict_types=1);
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if (!hash_equals(csrf(),(string)($_POST['csrf']??''))) { http_response_code(419); exit('Phiên hết hạn. Hãy tải lại trang.'); }
        $action=(string)($_POST['action']??'');
        switch ($action) {
            case 'register':
                $name=field('name',100); $email=strtolower(field('email')); $password=field('password',200);
                if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12) throw new DomainException('Email không hợp lệ hoặc mật khẩu dưới 12 ký tự.');
                if (query('SELECT id FROM users WHERE email=?',[$email])->fetch()) throw new DomainException('Email đã được đăng ký.');
                query('INSERT INTO users(name,email,password) VALUES(?,?,?)',[$name,$email,password_hash($password,PASSWORD_DEFAULT)]);
                session_regenerate_id(true); $_SESSION['uid']=(int)db()->lastInsertId(); redirect('/');
            case 'login':
                $email=strtolower(field('email')); $a=query('SELECT * FROM login_attempts WHERE email=?',[$email])->fetch();
                if ($a && (int)$a['locked_until']>time()) throw new DomainException('Thử lại sau 15 phút.');
                $u=query('SELECT * FROM users WHERE email=?',[$email])->fetch();
                $valid=password_verify((string)($_POST['password']??''),$u['password']??'$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.');
                if (!$u || !$valid) {
                    $n=$a && (int)$a['locked_until']===0 ? (int)$a['attempts']+1 : 1;
                    query('INSERT INTO login_attempts(email,attempts,locked_until) VALUES(?,?,?) ON CONFLICT(email) DO UPDATE SET attempts=excluded.attempts,locked_until=excluded.locked_until',[$email,$n,$n>=5?time()+900:0]);
                    throw new DomainException('Email hoặc mật khẩu không đúng.');
                }
                query('DELETE FROM login_attempts WHERE email=?',[$email]); session_regenerate_id(true); $_SESSION['uid']=$u['id']; redirect('/');
            case 'logout': $_SESSION=[]; session_regenerate_id(true); redirect('/');
            case 'cart':
                $id=integer('id',1); $qty=integer('qty',0,99);
                $p=query('SELECT * FROM products WHERE id=? AND active=1',[$id])->fetch();
                if (!$p || $qty>(int)$p['stock']) throw new DomainException('Số lượng vượt tồn kho.');
                if ($qty===0) unset($_SESSION['cart'][$id]); else $_SESSION['cart'][$id]=$qty;
                redirect('/?page=cart');
            case 'checkout':
                $u=requireUser(); $phone=field('phone',20);
                if (!preg_match('/^[+0-9 ()-]{8,20}$/',$phone)) throw new DomainException('Số điện thoại không hợp lệ.');
                $id=checkout((int)$u['id'],cart(),field('name',100),$phone,field('address',1000),field('payment',10),strtoupper(trim((string)($_POST['coupon']??''))));
                $_SESSION['cart']=[]; redirect('/?page=order&id='.$id);
            case 'profile':
                $u=requireUser(); query('UPDATE users SET name=? WHERE id=?',[field('name',100),$u['id']]);
                if (!empty($_POST['password'])) {
                    $old=query('SELECT password FROM users WHERE id=?',[$u['id']])->fetchColumn();
                    if (!password_verify((string)($_POST['current_password']??''),$old) || strlen((string)$_POST['password'])<12) throw new DomainException('Mật khẩu hiện tại không đúng hoặc mật khẩu mới dưới 12 ký tự.');
                    query('UPDATE users SET password=? WHERE id=?',[password_hash((string)$_POST['password'],PASSWORD_DEFAULT),$u['id']]); session_regenerate_id(true);
                } redirect('/?page=account');
            case 'category': requireUser(true); query('INSERT INTO categories(name) VALUES(?)',[field('name',100)]); redirect('/?page=admin&tab=categories');
            case 'product':
                requireUser(true); $id=integer('id'); $image=trim((string)($_POST['image']??''));
                if ($image!=='' && (!filter_var($image,FILTER_VALIDATE_URL) || parse_url($image,PHP_URL_SCHEME)!=='https')) throw new DomainException('Ảnh phải là URL HTTPS.');
                $args=[integer('category_id',1),field('name',200),field('description',10000),integer('price'),integer('stock',0,1000000),$image,isset($_POST['active'])?1:0];
                if ($id) { $args[]=$id; query('UPDATE products SET category_id=?,name=?,description=?,price=?,stock=?,image=?,active=? WHERE id=?',$args); }
                else query('INSERT INTO products(category_id,name,description,price,stock,image,active) VALUES(?,?,?,?,?,?,?)',$args);
                redirect('/?page=admin&tab=products');
            case 'coupon':
                requireUser(true); $code=strtoupper(field('code',30)); $date=trim((string)($_POST['expires_at']??''));
                if (!preg_match('/^[A-Z0-9_-]+$/',$code) || ($date!=='' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date) || date('Y-m-d',strtotime($date))!==$date))) throw new DomainException('Mã hoặc ngày hết hạn không hợp lệ.');
                query('INSERT INTO coupons(code,percent,min_total,expires_at) VALUES(?,?,?,?)',[$code,integer('percent',1,100),integer('min_total'),$date?:null]); redirect('/?page=admin&tab=coupons');
            case 'coupon_toggle': requireUser(true); query('UPDATE coupons SET active=1-active WHERE id=?',[integer('id',1)]); redirect('/?page=admin&tab=coupons');
            case 'status': requireUser(true); orderStatus(integer('id',1),field('status',20)); redirect('/?page=order&id='.(int)$_POST['id']);
            case 'paid': requireUser(true); query('UPDATE orders SET paid=1-paid WHERE id=?',[integer('id',1)]); redirect('/?page=order&id='.(int)$_POST['id']);
            default: throw new DomainException('Thao tác không hợp lệ.');
        }
    } catch (DomainException $ex) { $error=$ex->getMessage(); }
    catch (PDOException $ex) { error_log((string)$ex); $error='Không thể lưu dữ liệu. Kiểm tra dữ liệu trùng hoặc danh mục không tồn tại.'; }
}
