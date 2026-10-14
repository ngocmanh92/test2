# Nhịp Tin — Website tin tức PHP

Mã nguồn khởi đầu bằng PHP 8.2+, không cần Composer. Dữ liệu JSON phù hợp prototype nhỏ.

## Chức năng
- Trang chủ responsive, bài nổi bật, phân trang và tìm kiếm.
- Danh mục Công nghệ, Đời sống, Kinh doanh, Thể thao.
- Chi tiết bài viết, bài liên quan, trang 404.
- `/admin.php`: đăng nhập, đăng xuất, tạo/sửa bài, lưu nháp hoặc đăng.
- Mật khẩu băm, session, CSRF và escape HTML; dữ liệu mẫu ghi rõ trên website.

## Chạy thử
```bash
php bin/create-admin.php
php -S 127.0.0.1:8080 -t public
```
Mở http://127.0.0.1:8080 và http://127.0.0.1:8080/admin.php.
Không có tài khoản mặc định. Tệp `data/admin.json` được tạo riêng và bỏ qua bởi Git.

## Cấu trúc
- `public/`: trang công khai, quản trị và CSS; đây là document root.
- `app/bootstrap.php`: đọc/ghi dữ liệu, khóa file và danh mục.
- `data/articles.json`: bài mẫu; bản ghi cũ mặc định đã đăng.
- `bin/create-admin.php`: tạo tài khoản bằng CLI.
- `tests/`: kiểm tra HTTP công khai và quản trị.
- `.github/workflows/php.yml`: lint PHP và chạy kiểm tra trên GitHub.

## Quyết định repository
Dùng `ngocmanh92/test2`: đã xác nhận repo trống, có quyền ghi và đang public.
Không dùng TestProject/EAGAP vì đã có dữ liệu và chưa xác minh mục đích; không có README.md ở đường dẫn đã kiểm tra.
Tên repo riêng `nhip-tin-php` rõ nghĩa hơn, nhưng công cụ hiện có không cung cấp thao tác tạo/đổi tên repository. Có thể đổi tên test2 trong GitHub Settings sau.
Chỉ mã nguồn và nội dung minh họa được đưa lên; không commit tài khoản thật.

## Hosting PHP / Hostinger
Bật HTTPS và PHP 8.2+. Đặt `public` làm document root. Nếu dùng `public_html`, chép nội dung `public` vào đó và đặt `app`, `data` cùng cấp với `public_html`.
Chỉ cấp quyền ghi cần thiết cho tiến trình PHP trên `data/articles.json`; không dùng 777.
Tạo tài khoản trên máy riêng hoặc SSH; đưa `data/admin.json` ngoài web root, giới hạn quyền đọc.
GitHub Pages không chạy PHP. PHP built-in server chỉ dùng phát triển.

## Kiểm tra và giới hạn
GitHub Actions lint mọi tệp PHP, chạy smoke test và kiểm tra đăng nhập, CSRF, nháp/đăng, XSS, đăng xuất.
Đã xác minh lượt chạy [PHP checks #1](https://github.com/ngocmanh92/test2/actions/runs/38387541021) cho commit `b907455`: lint 4 tệp PHP, smoke test và quản trị đều pass trên PHP 8.2.34. Môi trường chỉnh sửa hiện chưa có PHP; runtime được xác minh bằng GitHub Actions. Luôn kiểm tra lượt chạy của commit mới nhất trước khi triển khai.
Workflow giới hạn 5 phút, chờ server tối đa 20 lần với timeout mỗi request, in log server khi lỗi và dọn tiến trình khi kết thúc. HTTP test có timeout 10 giây; có thể chạy thủ công qua workflow_dispatch.
Chưa có upload ảnh, xóa bài, quản lý danh mục, nhiều tài khoản hay giới hạn đăng nhập. Trước khi mở quản trị ra Internet, thêm rate limit ở ứng dụng hoặc hosting.
JSON có khóa để tránh ghi đồng thời, nhưng không thay thế cơ sở dữ liệu và backup. Sao lưu trước khi dùng thật; chuyển MySQL khi mở rộng.

## Backlog tiếp theo
1. Bảo vệ đăng nhập bằng rate limit, ghi audit log.
2. Chuyển repository dữ liệu sang PDO/MySQL, migration và backup.
3. Thêm quản lý danh mục, ảnh với kiểm tra MIME/kích thước.
4. Thêm phân quyền editor/admin, lịch đăng và xác nhận xóa.
5. Kiểm thử trên hosting với HTTPS và thiết lập document root đúng.
