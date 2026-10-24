# Mộc Shop — Website bán hàng PHP

Website bán hàng nằm trong [`php-shop/`](php-shop/README.md).

## Chạy thử

```bash
cd php-shop
export ADMIN_EMAIL='admin@example.com'
read -rsp 'Admin password: ' ADMIN_PASSWORD
export ADMIN_PASSWORD
php bin/install.php
unset ADMIN_PASSWORD
php -S localhost:8000 -t public
```

Yêu cầu PHP 8.2+ và PDO SQLite. Mật khẩu quản trị tối thiểu 12 ký tự. Document root trên hosting là `php-shop/public/`.

Xem [hướng dẫn đầy đủ](php-shop/README.md) về chức năng, cấu hình, kiểm thử và giới hạn. Workflow `.github/workflows/php-shop.yml` kiểm tra PHP 8.2/8.3/8.4.
