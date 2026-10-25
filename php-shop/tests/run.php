<?php
declare(strict_types=1);
$file=sys_get_temp_dir().'/shop-test-'.bin2hex(random_bytes(8)).'.sqlite';
putenv('SHOP_DB='.$file); putenv('ADMIN_EMAIL=admin@example.test'); putenv('ADMIN_PASSWORD=test-password-123456');
require __DIR__.'/../bin/install.php';
query('UPDATE products SET price=590000,stock=30 WHERE id=1');
function check(bool $v,string $label): void { if(!$v) throw new RuntimeException('FAIL '.$label); echo 'PASS '.$label."\n"; }
try {
    query('INSERT INTO users(name,email,password) VALUES(?,?,?)',['Customer','customer@example.test',password_hash('long-password-123',PASSWORD_DEFAULT)]);
    $uid=(int)db()->lastInsertId(); $p=query('SELECT * FROM products WHERE id=1')->fetch(); $p['qty']=2;
    $id=checkout($uid,[$p],'Người mua','0901234567','Hà Nội','cod','WELCOME10');
    $o=query('SELECT * FROM orders WHERE id=?',[$id])->fetch();
    check((int)$o['total']===1062000,'server price, coupon, free shipping');
    check((int)query('SELECT stock FROM products WHERE id=1')->fetchColumn()===28,'stock deduction');
    $p['qty']=99;
    try { checkout($uid,[$p],'Buyer','0901234567','Address','cod',''); throw new RuntimeException('should reject'); } catch(DomainException $e) {}
    check((int)query('SELECT COUNT(*) FROM orders')->fetchColumn()===1,'out of stock rollback');
    orderStatus($id,'cancelled');
    check((int)query('SELECT stock FROM products WHERE id=1')->fetchColumn()===30,'cancellation restores stock');
    try { orderStatus($id,'cancelled'); throw new RuntimeException('should reject'); } catch(DomainException $e) {}
    check((int)query('SELECT stock FROM products WHERE id=1')->fetchColumn()===30,'no double restock');
    $p['qty']=1; $p['price']=1;
    $id=checkout($uid,[$p],'Buyer','0901234567','Address','bank','');
    check((int)query('SELECT total FROM orders WHERE id=?',[$id])->fetchColumn()===590000,'ignore forged price');
    orderStatus($id,'confirmed'); orderStatus($id,'shipping'); orderStatus($id,'completed');
    check(query('SELECT status FROM orders WHERE id=?',[$id])->fetchColumn()==='completed','order lifecycle');
    try { totals([$p],'INVALID'); throw new RuntimeException('should reject'); } catch(DomainException $e) {}
    check(e('<script>')==='&lt;script&gt;','HTML escaping');
    check(substr_count(pageLinks(500,10000),'<a ')<=7,'bounded pagination for large catalogs');
    echo "All integration checks passed.\n";
} finally { @unlink($file); }
