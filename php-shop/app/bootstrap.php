<?php
declare(strict_types=1);
const ROOT = __DIR__ . '/..';
define('STORAGE',getenv('SHOP_STORAGE')?:ROOT.'/storage');
if (PHP_SAPI !== 'cli') {
    ini_set('display_errors','0');
    $GLOBALS['maintenance_lock']=fopen(STORAGE.'/maintenance.lock','c');
    if(!$GLOBALS['maintenance_lock'] || !flock($GLOBALS['maintenance_lock'],LOCK_SH)) { http_response_code(503); exit('Website đang bảo trì.'); }
    set_exception_handler(function(Throwable $ex): void {
        error_log((string)$ex);
        while(ob_get_level()>0) ob_end_clean();
        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');
        header('Cache-Control: no-store');
        echo '<!doctype html><html lang="vi"><meta charset="utf-8"><title>Tạm thời chưa khả dụng</title><h1>Chưa thể xử lý yêu cầu</h1><p>Vui lòng thử lại sau hoặc liên hệ cơ sở.</p></html>';
    });
    foreach ([$_GET,$_POST] as $input) foreach ($input as $value) {
        if (!is_string($value) || strlen($value)>12000) {
            http_response_code(400); exit('Dữ liệu gửi lên không hợp lệ.');
        }
    }
    ini_set('session.use_strict_mode','1');
}
if (PHP_SAPI !== 'cli' && !defined('PUBLIC_MEDIA')) {
    session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','samesite'=>'Lax']);
    session_start();
    header('Content-Type: text/html; charset=UTF-8');
    header('Cache-Control: private, no-store');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' https:; style-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'");
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $file=getenv('SHOP_DB') ?: STORAGE.'/shop.sqlite';
        if (!is_file($file)) throw new RuntimeException('Chưa cài đặt. Chạy php bin/install.php trước.');
        $pdo=new PDO('sqlite:'.$file,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC]);
        $pdo->exec('PRAGMA foreign_keys=ON; PRAGMA busy_timeout=5000');
    }
    return $pdo;
}
function query(string $sql,array $args=[]): PDOStatement { $s=db()->prepare($sql); $s->execute($args); return $s; }
function e(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES,'UTF-8'); }
function money(int $v): string { return number_format($v,0,',','.').' ₫'; }
function user(): ?array {
    if(!isset($_SESSION['uid'])) return null;
    $u=query('SELECT id,name,email,role,access_level,active,auth_version,totp_secret FROM users WHERE id=?',[$_SESSION['uid']])->fetch();
    if(!$u || !$u['active'] || (int)($_SESSION['auth_version']??0)!==(int)$u['auth_version']) { unset($_SESSION['uid']); return null; }
    unset($u['totp_secret']); return $u;
}
function redirect(string $url): never { header('Location: '.$url); exit; }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function token(): string { return '<input type="hidden" name="csrf" value="'.e(csrf()).'">'; }
function requireUser(bool $admin=false): array {
    $u=user(); if (!$u) redirect('/?page=login');
    if ($admin && $u['role']!=='admin') { http_response_code(403); exit('Không có quyền truy cập.'); } return $u;
}
function field(string $name,int $max=255): string {
    $v=trim((string)($_POST[$name]??'')); if ($v==='' || strlen($v)>$max) throw new DomainException('Thông tin '.$name.' không hợp lệ.'); return $v;
}
function integer(string $name,int $min=0,int $max=1000000000): int {
    $v=filter_var($_POST[$name]??null,FILTER_VALIDATE_INT); if ($v===false || $v===null || $v<$min || $v>$max) throw new DomainException('Giá trị '.$name.' không hợp lệ.'); return $v;
}
function cart(): array {
    $quantities=$_SESSION['cart']??[];
    if(!$quantities) return [];
    $ids=array_map('intval',array_keys($quantities));
    $rows=query('SELECT * FROM products WHERE active=1 AND id IN ('.implode(',',array_fill(0,count($ids),'?')).')',$ids)->fetchAll();
    foreach($rows as &$row) $row['qty']=(int)$quantities[$row['id']];
    unset($row);
    return $rows;
}
function totals(array $rows,string $code=''): array {
    $subtotal=0; foreach ($rows as $p) $subtotal+=(int)$p['price']*(int)$p['qty'];
    $coupon=null; $discount=0;
    if ($code!=='') {
        $coupon=query('SELECT * FROM coupons WHERE code=? AND active=1 AND (expires_at IS NULL OR expires_at>=date(\'now\'))',[strtoupper($code)])->fetch();
        if (!$coupon || $subtotal<(int)$coupon['min_total']) throw new DomainException('Mã giảm giá không hợp lệ hoặc chưa đủ giá trị đơn.');
        $discount=intdiv($subtotal*(int)$coupon['percent'],100);
    }
    $shipping=$subtotal>=500000 || !$rows ? 0 : 30000;
    return compact('subtotal','discount','shipping')+['total'=>$subtotal-$discount+$shipping,'code'=>$coupon['code']??''];
}
function checkout(int $uid,array $rows,string $name,string $phone,string $address,string $payment,string $code): int {
    if (!$rows) throw new DomainException('Giỏ hàng đang trống.');
    if (!in_array($payment,['cod','bank'],true)) throw new DomainException('Phương thức thanh toán không hợp lệ.');
    $pdo=db(); $pdo->exec('BEGIN IMMEDIATE');
    try {
        $fresh=[];
        foreach ($rows as $r) {
            $p=query('SELECT * FROM products WHERE id=? AND active=1',[$r['id']])->fetch();
            $qty=(int)$r['qty'];
            if (!$p || $qty<1 || $qty>99 || (int)$p['stock']<$qty) throw new DomainException('Sản phẩm hết hàng hoặc số lượng không hợp lệ.');
            $p['qty']=$qty; $fresh[]=$p;
        }
        $t=totals($fresh,$code);
        query('INSERT INTO orders(user_id,name,phone,address,payment,subtotal,discount,shipping,total,coupon) VALUES(?,?,?,?,?,?,?,?,?,?)',[$uid,$name,$phone,$address,$payment,$t['subtotal'],$t['discount'],$t['shipping'],$t['total'],$t['code']]);
        $id=(int)$pdo->lastInsertId();
        foreach ($fresh as $p) {
            query('INSERT INTO order_items(order_id,product_id,name,price,qty) VALUES(?,?,?,?,?)',[$id,$p['id'],$p['name'],$p['price'],$p['qty']]);
            query('UPDATE products SET stock=stock-? WHERE id=?',[$p['qty'],$p['id']]);
        }
        $pdo->exec('COMMIT'); return $id;
    } catch (Throwable $ex) { $pdo->exec('ROLLBACK'); throw $ex; }
}
function orderStatus(int $id,string $next): void {
    $allowed=['pending'=>['confirmed','cancelled'],'confirmed'=>['shipping','cancelled'],'shipping'=>['completed'],'completed'=>[],'cancelled'=>[]];
    db()->exec('BEGIN IMMEDIATE');
    try {
        $o=query('SELECT * FROM orders WHERE id=?',[$id])->fetch();
        if (!$o || !in_array($next,$allowed[$o['status']]??[],true)) throw new DomainException('Không thể chuyển trạng thái đơn hàng.');
        if ($next==='cancelled') foreach (query('SELECT * FROM order_items WHERE order_id=?',[$id])->fetchAll() as $i) query('UPDATE products SET stock=stock+? WHERE id=?',[$i['qty'],$i['product_id']]);
        query('UPDATE orders SET status=? WHERE id=?',[$next,$id]); db()->exec('COMMIT');
    } catch(Throwable $ex) { db()->exec('ROLLBACK'); throw $ex; }
}
const STATUSES=['pending'=>'Chờ xác nhận','confirmed'=>'Đã xác nhận','shipping'=>'Đang giao','completed'=>'Hoàn tất','cancelled'=>'Đã hủy'];

function setting(string $key,string $fallback=''): string {
    static $values;
    if($values===null) {
        $values=[];
        if(query("SELECT name FROM sqlite_master WHERE type='table' AND name='site_settings'")->fetch()) {
            foreach(query('SELECT key,value FROM site_settings')->fetchAll() as $row) $values[$row['key']]=$row['value'];
        }
    }
    return $values[$key]??$fallback;
}

function asset(string $path): string {
    $file=ROOT.'/public/'.$path;
    return '/'.$path.'?v='.(is_file($file)?filemtime($file):'1');
}
function pageLinks(int $current,int $pages,array $params=[],string $anchor=''): string {
    if($pages<=1) return '';
    $current=max(1,min($pages,$current));
    $numbers=array_unique(array_merge([1,$pages],range(max(1,$current-2),min($pages,$current+2))));
    sort($numbers); $html='<nav class="pagination" aria-label="Phân trang">'; $last=0;
    foreach($numbers as $i) {
        if($last && $i>$last+1) $html.='<span aria-hidden="true">…</span>';
        $url='/?'.http_build_query(array_merge($params,['n'=>$i])).$anchor;
        $html.='<a href="'.e($url).'"'.($i===$current?' aria-current="page"':'').'>'.$i.'</a>';
        $last=$i;
    }
    return $html.'</nav>';
}

require_once ROOT.'/app/admin-tools.php';
