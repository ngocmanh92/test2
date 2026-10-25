# Nghệ nhân Văn Nho — website đá mỹ nghệ PHP

Website tiếng Việt cho danh mục tác phẩm, nội dung làng nghề, hồ sơ nghệ nhân và tư vấn chế tác. PHP 8.2–8.4 + SQLite, không cần Composer/Node.js.

## Chức năng hiện có

| Nhóm | Chức năng |
| --- | --- |
| Sản phẩm | Thêm/sửa/ẩn, giá/tồn kho cho luồng bán hàng cũ; ảnh bìa và bộ ảnh nhiều ảnh, thứ tự gallery |
| Ảnh | Upload JPEG/PNG/WebP tối đa 5 MB, 24 megapixel; kiểm tra MIME và kích thước; thư viện phân trang; gắn/gỡ ảnh khỏi sản phẩm |
| Danh mục | Thêm/sửa/xóa danh mục trống, danh mục cha/con, thứ tự; chặn chu trình; lọc danh mục cha bao gồm con |
| Nội dung | Bài viết, công trình, thành tích, trang giới thiệu; nháp/đăng/gỡ xuất bản; ảnh và SEO riêng; nội dung văn bản được escape |
| Trang giới thiệu | Nội dung loại `page` dùng slug `about`, `artist`, `services`, `process` thay phần mặc định sau khi xuất bản |
| Trang chủ & cấu hình | Tên thương hiệu, hotline, địa chỉ, ảnh đầu trang/chân dung/tác phẩm, lời giới thiệu đầu trang, mô tả SEO và chính sách |
| Báo giá | Phiếu khách hàng, tìm/lọc/phân trang, trạng thái; nhiều hạng mục, số lượng, đơn giá, vận chuyển, đặt cọc dự kiến, hiệu lực, ghi chú nội bộ |
| In báo giá | Bản in riêng, tiếng Việt, không chứa ghi chú nội bộ; nút in mở hộp thoại trình duyệt để Lưu thành PDF; chưa có bộ dựng PDF phía server |
| Nhân viên | Thêm/sửa, khóa, đổi quyền/mật khẩu, thu hồi phiên; bảo vệ chủ sở hữu cuối cùng và chống tự hạ quyền |
| Bảo mật | Session strict mode, CSRF, PDO prepared statements, escape HTML, giới hạn đăng nhập; 2FA TOTP, chống dùng lại mã, khóa 15 phút sau 5 lần sai |
| Quên mật khẩu | Token băm, một lần dùng, 30 phút, giới hạn yêu cầu, email qua hàng đợi; thay mật khẩu thu hồi phiên, giữ nguyên 2FA |
| Nhật ký | Ghi người thực hiện, sự kiện và đối tượng; phân trang; không ghi mật khẩu, OTP hoặc token reset |
| Sao lưu | SQLite nhất quán qua VACUUM INTO; ZIP gồm database và toàn bộ ảnh; tải xuống qua POST dành cho chủ sở hữu |
| Khôi phục | ZIP/SQLite, xác nhận mật khẩu và nhập KHOI PHUC; kiểm tra integrity, foreign keys, schema, chủ sở hữu và tệp ảnh; chống path traversal/bomb; sao lưu trước khi thay, thu hồi phiên/token cũ |
| Email | Hàng đợi, trạng thái chờ/gửi/lỗi; thông báo có yêu cầu báo giá mới và liên kết quên mật khẩu; worker CLI dùng mail transport của server |
| Luồng cũ | Giỏ hàng, tài khoản, COD/chuyển khoản thủ công, lịch sử đơn, tồn kho, mã giảm giá; không là luồng chính của website tư vấn đá |

## Phân quyền

| Quyền | Truy cập |
| --- | --- |
| Chủ sở hữu (`owner`) | Toàn bộ quản trị, nhân viên, cấu hình, nhật ký, backup, email |
| Biên tập (`editor`) | Nội dung, ảnh, sản phẩm và danh mục; không xem tài chính, báo giá, nhân viên hoặc backup |
| Kinh doanh (`sales`) | Báo giá, đơn hàng, khách hàng, mã giảm giá; không sửa nội dung, quyền nhân viên hoặc cấu hình |

Tài khoản nhân viên vẫn dùng `role=admin` để tương thích schema cũ, kết hợp cột `access_level` để kiểm soát quyền tại mỗi route/action; không chỉ ẩn menu. Khách hàng không truy cập quản trị.

## Cài mới

Yêu cầu PHP 8.2+ với PDO SQLite, fileinfo, session và extension ZIP để backup/restore cả ảnh. Thư mục `storage/` phải ghi được bởi tài khoản chạy PHP; không cấp quyền 777.

```bash
cd php-shop
export ADMIN_EMAIL='admin@example.com'
read -rsp 'Admin password: ' ADMIN_PASSWORD
export ADMIN_PASSWORD
php bin/install.php
unset ADMIN_PASSWORD
php -S localhost:8000 -t public
```

Mật khẩu 12–72 byte. Không có tài khoản cố định trong repo. Bộ cài tạo 6 mẫu tham khảo với giá/tồn kho bằng 0; không phải hàng có sẵn. Chưa có ảnh thật, hotline và địa chỉ cơ sở. Không công bố website như cửa hàng đã sẵn sàng khi chưa cập nhật thông tin thật.

## Nâng cấp database đã có

1. Sao lưu database cùng toàn bộ `storage/media/`, tạm ngừng truy cập và worker ghi dữ liệu.
2. Đưa mã mới lên, giữ nguyên database và storage.
3. Chạy `php bin/migrate-stone.php`. Script tạo bảng/cột/index còn thiếu, có thể chạy lại, không đổi giá hoặc xóa dữ liệu cũ. Script từ chối chạy khi có request giữ maintenance lock.
4. Đăng nhập lại. Tài khoản quản trị cũ mặc định là chủ sở hữu; khách hàng cũ vẫn là khách hàng.
5. Nếu vẫn dùng danh mục/sản phẩm từ demo Mộc Shop, sửa dữ liệu đó trước khi công bố; migration không tự thay nội dung kinh doanh.

Schema quản trị nằm ở `app/migrations.php`, được gọi bởi cả bộ cài và công cụ nâng cấp. File `database.sql` chứa phần lõi.

## Cấu hình hosting

| Biến môi trường | Ý nghĩa |
| --- | --- |
| SHOP_STORAGE | Thư mục dữ liệu riêng, mặc định `storage/`; cần đặt giống nhau cho web và CLI |
| SHOP_DB | Đường dẫn tuyệt đối SQLite; mặc định `storage/shop.sqlite` |
| SHOP_NAME / SHOP_PHONE / SHOP_ADDRESS | Giá trị thương hiệu ban đầu; cấu hình trong admin được ưu tiên |
| SHOP_URL | URL HTTPS chính thức, ví dụ `https://demo.thegioifx.com`; cần cho liên kết quên mật khẩu |
| MAIL_FROM | Email gửi hợp lệ thuộc miền bạn quản lý; cần cho worker và reset mật khẩu |
| BANK_INSTRUCTIONS | Thông tin chuyển khoản của luồng đơn hàng cũ |

Không tự đọc `.env`. Cấu hình biến môi trường trong môi trường thực thi của cả web PHP và cron. Bật HTTPS; document root **phải là `php-shop/public/`**. `storage`, `app`, tệp backup, khóa 2FA và database nằm ngoài web root. Không commit hoặc upload toàn bộ storage thật lên GitHub.

Đặt `upload_max_filesize` ít nhất 50M và `post_max_size` lớn hơn 50M để dùng giới hạn backup của ứng dụng; giới hạn riêng cho ảnh vẫn là 5 MB. Gói upload ZIP/SQLite tối đa 50 MB; tổng giải nén ZIP tối đa 200 MB/10.000 tệp, database 50 MB. Với dữ liệu lớn hơn cần quy trình backup/restore trên server riêng.

`public/.htaccess` bật cache/nén khi Apache/LiteSpeed hỗ trợ. Khi dùng Nginx cần cấu hình tương đương. PHP development server không kiểm chứng được `.htaccess`; kiểm tra header thực tế sau triển khai. Truy vấn cá nhân dùng `private, no-store`; ảnh thư viện có URL riêng, cache lâu và không cho ghi đè nội dung cùng URL.

## Hướng dẫn quản trị

- **Ảnh:** upload tại Thư viện ảnh, sao chép `/media.php?id=…` vào trường ảnh bìa, bài viết hoặc cấu hình. Nhập ID ảnh và sản phẩm để tạo gallery. Gỡ liên kết không xóa tệp gốc; hiện không có nút xóa hẳn tệp để tránh làm hỏng các bản backup cũ.
- **Nội dung:** dùng các loại bài viết/công trình/thành tích, hoặc `page` để thay trang giới thiệu. Định dạng hiện là văn bản thuần, xuống dòng; HTML/script không chạy. Chưa có trình soạn thảo rich text hoặc quản lý tệp bản vẽ.
- **Báo giá:** mở “Lập báo giá / ghi chú” từ danh sách. Thêm hạng mục, lưu phí giao, cọc dự kiến và hiệu lực. Đây chưa phải thu tiền/đối soát cọc, hóa đơn hoặc hợp đồng. Bản in không bao gồm ghi chú nội bộ.
- **2FA:** vào Bảo mật, xác nhận mật khẩu, nhập khóa thủ công vào ứng dụng authenticator TOTP (SHA-1, 6 số, chu kỳ 30 giây), nhập mã để bật. Cửa sổ chấp nhận ±1 bước, không dùng lại bước đã xác thực. Không có QR hay recovery codes tự động; giữ khóa thiết lập ở nơi an toàn. Khi mất thiết bị, chủ hosting xác minh danh tính rồi dùng `php bin/recover-2fa.php email --confirm`; công cụ thu hồi phiên và ghi nhật ký. Không dùng script này như chức năng công khai.
- **Khôi phục:** chỉ nhận backup cùng phiên bản schema; dữ liệu hiện tại được sao lưu trước, tệp ảnh có tên bất biến. Sau khôi phục mọi phiên và token reset cũ vô hiệu, thư đang chờ bị hủy để không gửi lại yêu cầu từ quá khứ. Với backup SQLite riêng, ảnh vẫn cần còn trên đĩa. Tệp backup không mã hóa, chứa dữ liệu khách hàng và bí mật xác thực: bảo vệ như database gốc. Không chia sẻ liên kết công khai; chủ hosting quản lý dung lượng và vòng đời backup ngoài ứng dụng.

## Gửi email / quên mật khẩu

1. Cấu hình `MAIL_FROM`, `SHOP_URL`, mail transport cho PHP và DNS gửi thư của miền (SPF/DKIM theo nhà cung cấp).
2. Nhập email nhận báo giá trong Cấu hình website.
3. Cấu hình cron chạy `php /duong-dan/php-shop/bin/send-mail.php`, ví dụ mỗi phút. Đây là hướng dẫn; chưa có cron hoặc email thật nào được kích hoạt bởi việc cập nhật mã.
4. Xem trạng thái trong mục Email. `sent` nghĩa mail transport chấp nhận, không đảm bảo thư vào inbox. Worker xóa nội dung hàng đợi sau khi xử lý để giảm lưu giữ token. Khi gửi lỗi, người dùng yêu cầu link mới sau thời gian giới hạn; chưa có nút retry email cũ.

Chưa cấu hình mail thì chức năng quên mật khẩu không thể gửi liên kết. Không trả token reset trên giao diện hoặc nhật ký. Chưa có tích hợp SMTP/API bên thứ ba hoặc SMS.

## Kiểm thử

```bash
find app public bin tests -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/run.php
python3 tests/smoke.py
python3 tests/admin_features.py
```

CI kiểm tra PHP 8.2/8.3/8.4 với `pdo_sqlite`, `fileinfo`, `zip`. Test quản trị dùng database tạm, không gửi email thực tế; kiểm tra migration lặp lại, ảnh/MIME/gallery, danh mục chống chu trình, CMS nháp/đăng/XSS, phân quyền, tổng báo giá/bản in, token reset một lần, TOTP/replay/khóa tài khoản, ZIP backup và khôi phục/thu hồi phiên. Chưa có kiểm thử trực quan, Lighthouse, kiểm thử trên Hostinger hoặc đo tải production.

## Thiết kế và dữ liệu tham khảo

Website lấy cảm hứng tổ chức danh mục từ:
- https://danguyencong.com/lang-nghe-da-my-nghe-non-nuoc/
- https://www.phanthien.com/tin-tuc/thuyet-minh-ve-lang-da-non-nuoc-ngu-hanh-son-da-nang-n38.html
- https://dieukhacdaanhnhat.com/danh-muc/tuong-cong-giao/
- https://damynghenonnuocdn.com/

Hồ sơ nghệ nhân tham khảo ba bài báo người dùng cung cấp:
- https://vnexpress.net/nguoi-nan-phoi-cho-hang-tram-tuong-da-non-nuoc-5130767.html
- https://nhandan.vn/o-lai-de-tra-on-lang-da-post881505.html
- https://thanhnien.vn/huyen-thoai-ngu-hanh-son-duoc-khac-vao-da-doat-giai-nhat-cuoc-thi-dieu-khac-185260827114406794.htm

Đã đọc ngày 24/10/2026. Chỉ ghi thành tích đã đối chiếu: Đá nở hoa — giải Nhì 2023, Huyền thoại Ngũ Hành Sơn — giải Nhất 2026; không dùng tuyên bố chưa xác minh “3 lần giải nhất”. Không sao chép ảnh/địa chỉ/hotline của nguồn khác. Ảnh tài liệu người dùng chưa thay ảnh gốc. Phần cuối mục 7 bị cắt trong tài liệu chưa được suy diễn thêm.

Còn cần dữ liệu thật, điều kiện dịch vụ và kiểm thử triển khai. Chưa có thanh toán online, giao vận, hợp đồng điện tử, đối soát đặt cọc, nhiều kho hoặc ứng dụng kế toán. Các phần này không được mô tả là đã hoàn thành.

## Đồng bộ giao diện danonnuoc.net (25/10/2026)

Đồng bộ CSS và bố cục theo trang công khai của người dùng: thương hiệu mặc định Đá Non Nước, tông kem/xanh trầm, hero ảnh dạng vòm, 6 dòng sản phẩm có ảnh, tác phẩm mẫu, phần chế tác và FAQ. Các route và phân quyền quản trị mở rộng vẫn giữ nguyên. Không sửa database, thông tin liên hệ hoặc nội dung đã cấu hình khi cập nhật mã.

Các mục cấu hình mới: dòng đầu trang, khẩu hiệu, tiêu đề/thông điệp hero, ảnh chế tác. `reference_mode=1` (mặc định) hiển thị ảnh tham khảo; nhập `0` để tắt. Tiêu đề trống dùng nội dung mặc định. Ảnh sản phẩm, ảnh đầu trang và ảnh chế tác do chủ cơ sở cấu hình luôn được ưu tiên hơn ảnh mẫu.

Tám ảnh trong `public/assets/references/` được lấy từ các tài nguyên đang hiển thị trên danonnuoc.net do người dùng cung cấp để đồng bộ giao diện. Chú thích/nguồn Nguyễn Công, Anh Nhật, Thành Đô được giữ cạnh ảnh. Chúng là ảnh tham khảo, không được mô tả thành sản phẩm hoặc công trình riêng của cơ sở. Danh sách nguồn nằm trong `app/theme.php`. Khi dùng thương mại, chủ cơ sở cần bảo đảm quyền sử dụng hoặc thay ảnh riêng qua quản trị; ghi nguồn không tự thay thế quyền sử dụng ảnh.

Ảnh mẫu sản phẩm chỉ áp dụng cho sáu tên mẫu mặc định và chỉ khi trường ảnh sản phẩm trống; không gán nhầm cho sản phẩm mới. Danh mục hiển thị theo dữ liệu hiện tại, ưu tiên ảnh riêng có sẵn trong sản phẩm thuộc danh mục. CSS tham chiếu được giữ kèm lớp truy cập bàn phím/menu mobile/gallery của bản nâng cấp.

Cập nhật GitHub không tự triển khai hoặc thay database trên website danonnuoc.net. Kiểm tra giao diện trên hosting sau khi đưa mã lên; môi trường kiểm thử hiện kiểm tra HTML, tài nguyên ảnh và chức năng, chưa có kiểm thử so sánh ảnh chụp tự động.

## Rà soát giao diện và chức năng (25/10/2026)

- Danh mục tìm theo tên hỗ trợ tiếng Việt có dấu/không dấu, chữ hoa/thường và Unicode tổ hợp; `%`, `_` được tìm như ký tự thông thường. Không cần extension intl/mbstring.
- Hiển thị số mẫu phù hợp và liên kết xóa bộ lọc; tăng vùng chạm danh mục, chừa vùng an toàn cho nút báo giá trên điện thoại.
- Điện thoại phải chứa 8–15 chữ số; ngày hết hạn mã giảm giá/báo giá phải là ngày lịch hợp lệ.
- Phiếu yêu cầu và thông báo email được lưu trong cùng giao dịch: lỗi ghi hàng đợi sẽ hoàn tác phiếu, cho phép thử lại mà không để lại phiếu dở dang.
- Kiểm thử tự động gồm hồi quy tìm kiếm, đầu vào sai, lỗi hàng đợi email, phân quyền, báo giá/in, CMS, ảnh, 2FA và phục hồi bản sao lưu.

Đây là bản mã nguồn đã kiểm thử. Việc kiểm thử quản trị thực hiện trên database tạm; chưa xác nhận gửi email thật, cấu hình hosting, hoặc hiển thị trên thiết bị di động thật. Ảnh mẫu và nội dung nghệ nhân mặc định cần được chủ cơ sở xác nhận/thay bằng dữ liệu kinh doanh phù hợp trước khi công bố.
