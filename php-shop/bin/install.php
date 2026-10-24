<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
if (PHP_SAPI!=='cli') exit;
$file=getenv('SHOP_DB') ?: ROOT.'/storage/shop.sqlite';
if (is_file($file)) { fwrite(STDERR,"Database đã tồn tại, không ghi đè.\n"); exit(1); }
$email=getenv('ADMIN_EMAIL') ?: '';
$pass=getenv('ADMIN_PASSWORD') ?: '';
if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($pass)<12) { fwrite(STDERR,"Đặt ADMIN_EMAIL và ADMIN_PASSWORD (ít nhất 12 ký tự).\n"); exit(1); }
$pdo=new PDO('sqlite:'.$file); $pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
$pdo->exec(file_get_contents(ROOT.'/database.sql'));
$s=$pdo->prepare('INSERT INTO users(name,email,password,role) VALUES(?,?,?,?)'); $s->execute(['Quản trị',$email,password_hash($pass,PASSWORD_DEFAULT),'admin']);
$pdo->exec("INSERT INTO categories(name) VALUES('Phụ kiện'),('Gia dụng'),('Thời trang')");
$s=$pdo->prepare('INSERT INTO products(category_id,name,description,price,stock,image) VALUES(?,?,?,?,?,?)');
foreach ([[1,'Tai nghe không dây','Âm thanh rõ nét, hộp sạc nhỏ gọn.',590000,30,''],[1,'Bàn phím cơ','Thiết kế tối giản, kết nối USB.',890000,20,''],[2,'Bình giữ nhiệt','Dung tích 500ml, phù hợp đi làm và du lịch.',190000,50,''],[3,'Balo hàng ngày','Nhiều ngăn, gọn nhẹ, màu trung tính.',350000,25,''],[2,'Đèn bàn LED','Điều chỉnh độ sáng, tiết kiệm điện.',290000,40,''],[3,'Áo thun basic','Chất liệu cotton mềm mại.',150000,80,'']] as $p) $s->execute($p);
$pdo->exec("INSERT INTO coupons(code,percent,min_total,expires_at) VALUES('WELCOME10',10,200000,date('now','+1 year'))");
chmod($file,0600); echo "Cài đặt xong. Chạy php -S localhost:8000 -t public\n";
