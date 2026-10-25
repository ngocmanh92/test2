<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli') exit;
$lock=fopen(STORAGE.'/maintenance.lock','c'); if(!flock($lock,LOCK_EX|LOCK_NB)) { fwrite(STDERR,"Có yêu cầu đang chạy; hãy bảo trì trước khi migrate.\n"); exit(1); }
$sql=file_get_contents(ROOT.'/database.sql');
$start=strpos($sql,'CREATE TABLE IF NOT EXISTS quote_requests');
db()->exec(substr($sql,$start));
require ROOT.'/app/migrations.php'; migrateAdmin(db());
echo "Đã thêm bảng báo giá và cấu hình website. Không sửa sản phẩm hoặc đơn hàng hiện có.\n";
