<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { exit(1); }
$user = trim(readline('Tên đăng nhập: '));
fwrite(STDOUT, "Mật khẩu (ít nhất 12 ký tự; nhập trên máy riêng): ");
$password = trim(fgets(STDIN));
if ($user === '' || strlen($password) < 12) { fwrite(STDERR, "Thông tin không hợp lệ.\n"); exit(1); }
$path = __DIR__ . '/../data/admin.json';
if (file_exists($path)) { fwrite(STDERR, "Tài khoản đã tồn tại; sao lưu và xóa file trước khi đặt lại.\n"); exit(1); }
umask(0077);
file_put_contents($path, json_encode(['username'=>$user, 'password_hash'=>password_hash($password, PASSWORD_DEFAULT)], JSON_THROW_ON_ERROR));
echo "Đã tạo tài khoản.\n";
