<?php
declare(strict_types=1);
require __DIR__.'/../app/bootstrap.php';
if(PHP_SAPI!=='cli') exit;
$sql=file_get_contents(ROOT.'/database.sql');
$start=strpos($sql,'CREATE TABLE IF NOT EXISTS quote_requests');
db()->exec(substr($sql,$start));
echo "Đã thêm bảng báo giá và cấu hình website. Không sửa sản phẩm hoặc đơn hàng hiện có.\n";
