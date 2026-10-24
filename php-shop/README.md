# Mộc Shop — website bán hàng PHP

Website tiếng Việt dùng PHP 8.2+ và SQLite (PDO), không cần Composer hoặc Node.js. Giao diện responsive cho máy tính và điện thoại.

## Chức năng đã triển khai

- Trang chủ, danh mục, tìm kiếm, sắp xếp giá, phân trang, chi tiết sản phẩm và ảnh qua URL HTTPS.
- Đăng ký, đăng nhập, đăng xuất, sửa tên, đổi mật khẩu; phân quyền khách hàng/quản trị.
- Giỏ hàng trong session, cập nhật số lượng và xóa sản phẩm.
- Checkout yêu cầu đăng nhập, địa chỉ nhận hàng, COD hoặc chuyển khoản thủ công.
- Mã giảm theo phần trăm, giá trị đơn tối thiểu, ngày hết hạn, bật/tắt mã.
- Phí vận chuyển cố định 30.000đ, miễn phí từ 500.000đ; sửa quy tắc tại `app/bootstrap.php::totals`.
- Lịch sử và chi tiết đơn chỉ dành cho chủ đơn hoặc quản trị viên; giá và tên được lưu tại thời điểm đặt.
- Quản trị: thống kê, thêm/sửa/ẩn sản phẩm, tồn kho, thêm danh mục, xem khách hàng, lọc đơn hàng, trạng thái giao hàng, xác nhận thanh toán.
- Trạng thái: chờ xác nhận → đã xác nhận → đang giao → hoàn tất. Có thể hủy trước khi giao; hoàn kho một lần.
- Kiểm tra giá và tồn kho phía server; giao dịch SQLite `BEGIN IMMEDIATE` tránh bán vượt tồn kho.
- PDO prepared statements, HTML escaping, CSRF cho mọi POST, HttpOnly/SameSite cookie, session regeneration, khóa đăng nhập theo email sau 5 lần sai trong 15 phút.
- GitHub Actions: kiểm tra cú pháp, integration và HTTP trên PHP 8.2/8.3/8.4.

## Cài đặt

Yêu cầu `php`, `pdo_sqlite`, thư mục `storage` cho phép PHP ghi. Chạy từ thư mục gốc dự án:

```bash
export ADMIN_EMAIL='admin@example.com'
# Nhập mật khẩu riêng; không commit lên GitHub.
read -rsp 'Admin password: ' ADMIN_PASSWORD
export ADMIN_PASSWORD
php bin/install.php
unset ADMIN_PASSWORD
php -S localhost:8000 -t public
```

Mật khẩu quản trị tối thiểu 12 ký tự. Truy cập http://localhost:8000 và đăng nhập bằng tài khoản vừa tạo. Bộ cài không ghi đè database đã tồn tại. Có 6 sản phẩm mẫu và mã `WELCOME10`, giảm 10% cho đơn từ 200.000đ, hết hạn sau một năm tính từ ngày cài.

Biến môi trường tùy chọn:

| Biến | Ý nghĩa |
| --- | --- |
| `SHOP_DB` | Đường dẫn tuyệt đối tới database; mặc định `storage/shop.sqlite` |
| `BANK_INSTRUCTIONS` | Ngân hàng, số tài khoản, chủ tài khoản hiển thị trên đơn chuyển khoản |

Không tự đọc `.env`: cấu hình biến môi trường trên web server hoặc hosting. Không nhập thông tin ngân hàng giả. Chuyển khoản chưa được tự động đối soát, quản trị xác nhận sau khi kiểm tra tiền nhận thực tế.

## Kiểm thử

```bash
find app public bin tests -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/run.php
python3 tests/smoke.py
```

Tests dùng database tạm riêng và không đụng dữ liệu cửa hàng. HTTP tests kiểm tra CSRF, đăng ký, phân quyền, giỏ hàng, checkout, quyền sở hữu đơn, các trang admin, hủy đơn và escape XSS. Integration tests kiểm tra tính tiền, rollback thiếu hàng, chống giả giá, hoàn kho và luồng trạng thái.

## Đưa lên hosting / Hostinger

1. Bật PHP 8.2+ và extension PDO SQLite. Kiểm tra hosting có hỗ trợ SQLite; dự án chưa có driver MySQL.
2. Document root phải là thư mục `public/`; không để `storage`, `app`, database hay toàn bộ repo trong thư mục công khai.
3. Tạo database bằng CLI, cấu hình biến môi trường, quyền ghi storage và bật HTTPS. Không dùng PHP development server trên production.
4. Cấu hình `BANK_INSTRUCTIONS`, sửa thương hiệu, chính sách vận chuyển/đổi trả và thông tin liên hệ trong giao diện trước khi mở bán.
5. Sao lưu SQLite bằng SQLite backup API hoặc dừng ghi trong khi sao chép. Lưu bản sao ngoài web root, kiểm thử khôi phục. Tắt display_errors; giữ log nội bộ.

## Cấu trúc

```text
app/bootstrap.php    Database, session, CSRF, giá, checkout, trạng thái
app/actions.php      Xử lý POST và phân quyền
app/admin.php        Giao diện quản trị
app/order-table.php  Bảng đơn hàng dùng chung
public/index.php     Router và giao diện khách hàng
public/assets/       CSS responsive
bin/install.php      Cài schema, admin, dữ liệu mẫu qua CLI
storage/             SQLite (không commit)
database.sql         Schema và indexes
tests/              Integration và HTTP tests
.github/workflows/   CI
```

## Giới hạn và phần cần tích hợp tiếp

Đây là phiên bản nền tảng hoạt động cho cửa hàng nhỏ, chưa phải toàn bộ hệ thống thương mại điện tử production. Chưa có VNPay/MoMo/Stripe, webhook thanh toán, API giao vận, email/SMS, quên mật khẩu qua email, đánh giá, yêu thích, biến thể SKU, nhiều địa chỉ, xuất hóa đơn, hoàn tiền, thuế, nhiều kho, audit log, chống bot hoặc cấu hình cửa hàng qua admin. Danh mục hiện hỗ trợ thêm; đơn hàng/khách hàng trong admin hiển thị tối đa 200 dòng. Không lưu dữ liệu thẻ. Các tích hợp cần tài khoản nhà cung cấp và kiểm thử riêng.

Giỏ hàng session được xóa sau khi đặt thành công. API chưa có idempotency key riêng, cần bổ sung trước khi dùng với frontend tự retry hoặc tải lớn. Khi số đơn tăng, chuyển database sang MySQL/PostgreSQL và bổ sung phân trang quản trị.

## Repository

Dự án nằm tại `php-shop/` trong `ngocmanh92/test2`. Chạy `cd php-shop` trước khi cài đặt; document root của cửa hàng là `php-shop/public/`. Workflow ở `.github/workflows/php-shop.yml` tại gốc repository.
