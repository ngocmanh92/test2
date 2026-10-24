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
$pdo->exec("INSERT INTO categories(name) VALUES('Tượng Phật'),('Tượng Công giáo'),('Linh vật đá'),('Đồ thờ đá'),('Đá sân vườn'),('Tượng nghệ thuật')");
$s=$pdo->prepare('INSERT INTO products(category_id,name,description,price,stock,image) VALUES(?,?,?,?,?,?)');
foreach ([[1,'Tượng Quan Âm — mẫu tham khảo','Thiết kế tượng Quan Âm. Chất liệu, kích thước, bệ tượng và chi tiết hoàn thiện được thống nhất khi tư vấn.',0,0,''],[2,'Tượng Đức Mẹ — mẫu tham khảo','Thiết kế tượng Đức Mẹ bằng đá. Tư vấn tỷ lệ, chất liệu và vị trí lắp đặt trước khi báo giá.',0,0,''],[3,'Cặp sư tử đá — mẫu tham khảo','Linh vật trang trí công trình. Cần xác nhận kích thước, loại đá và phương án vận chuyển.',0,0,''],[4,'Lư hương đá — mẫu tham khảo','Mẫu đồ thờ đá. Kích thước và hoa văn được trao đổi theo không gian sử dụng.',0,0,''],[5,'Đài phun nước — mẫu tham khảo','Thiết kế cảnh quan bằng đá. Báo giá riêng phần đá, thiết bị nước và thi công.',0,0,''],[6,'Tượng nghệ thuật — mẫu tham khảo','Tư vấn chế tác theo ý tưởng, bản vẽ hoặc hình ảnh mẫu của khách hàng.',0,0,'']] as $p) $s->execute($p);
$pdo->exec("INSERT INTO coupons(code,percent,min_total,expires_at) VALUES('WELCOME10',10,200000,date('now','+1 year'))");
chmod($file,0600); echo "Cài đặt xong. Chạy php -S localhost:8000 -t public\n";
