# Website đá mỹ nghệ — danh mục mẫu & yêu cầu báo giá

Giao diện tiếng Việt tham khảo cách tổ chức nội dung của các website đá mỹ nghệ Non Nước. Tên mặc định “ĐÁ MỸ NGHỆ” là tên tạm, không đại diện cho cơ sở trong nguồn tham khảo.

## Phần mới

- Trang chủ: giới thiệu, nhóm mẫu, tìm kiếm, lọc danh mục, phân trang, câu chuyện làng nghề và lời mời tư vấn.
- Trang chi tiết mẫu, trang giới thiệu làng nghề, quy trình đề xuất và liên hệ.
- Sáu nhóm mẫu: Tượng Phật, Tượng Công giáo, Linh vật đá, Đồ thờ đá, Đá sân vườn, Tượng nghệ thuật.
- Giá công khai chuyển thành “Liên hệ báo giá”; không công bố giá hoặc phí vận chuyển mẫu như giá thương mại thực tế.
- Phiếu tư vấn không yêu cầu đăng nhập: mẫu, họ tên, điện thoại, chất liệu, kích thước, địa điểm và nội dung. Có CSRF, đồng ý liên hệ, honeypot và khoảng cách gửi tối thiểu 60 giây mỗi session.
- Admin: mục “Yêu cầu báo giá”, trạng thái Mới / Đã liên hệ / Đã báo giá / Đã đóng.
- Chưa gửi email/SMS tự động. Yêu cầu chỉ được lưu trong database và hiển thị trong admin. Chưa có upload ảnh tham khảo hoặc gửi tệp đính kèm.

## Cài mới

Yêu cầu PHP 8.2+, PDO SQLite. Chạy trong `php-shop/`:

```bash
export ADMIN_EMAIL='admin@example.com'
read -rsp 'Admin password: ' ADMIN_PASSWORD
export ADMIN_PASSWORD
php bin/install.php
unset ADMIN_PASSWORD
php -S localhost:8000 -t public
```

Mật khẩu tối thiểu 12 ký tự. Bộ cài không ghi đè database đã tồn tại. Sản phẩm mẫu có giá và tồn kho bằng 0, dùng để bắt đầu trao đổi, không phải hàng có sẵn.

## Nâng cấp database đã cài

Sao lưu database trước, rồi chạy `php bin/migrate-stone.php`. Script chỉ tạo bảng yêu cầu báo giá nếu chưa có; không xóa, đổi tên hoặc sửa giá sản phẩm/đơn hàng cũ. Nếu đang dùng dữ liệu Mộc Shop cũ, cần sửa danh mục và sản phẩm trong database trước khi công bố; migration không tự thay dữ liệu kinh doanh.

## Thông tin cần cấu hình

| Biến môi trường | Nội dung |
| --- | --- |
| SHOP_NAME | Tên cơ sở thật |
| SHOP_PHONE | Hotline thật |
| SHOP_ADDRESS | Địa chỉ thật |
| SHOP_DB | Đường dẫn SQLite; mặc định storage/shop.sqlite |
| BANK_INSTRUCTIONS | Thông tin chuyển khoản cho luồng đơn hàng cũ |

Ứng dụng không tự đọc `.env`. Đặt biến trong môi trường server/hosting. Document root phải là `php-shop/public/`, bật HTTPS và không công khai database/storage/app. PHP development server chỉ dùng phát triển.

## Hình ảnh và nội dung

Hiện dùng placeholder ảnh có nhãn rõ ràng; khối đá trang đầu là hình trừu tượng CSS. Thêm ảnh được phép sử dụng bằng trường URL HTTPS trong admin. Chưa có gallery nhiều ảnh hoặc trường thông số có cấu trúc; có thể ghi thông số đã xác nhận trong mô tả sản phẩm. Không sao chép ảnh, nội dung nguyên văn, hotline, địa chỉ, tuổi nghề, số công trình hoặc bảo hành của nguồn tham khảo sang thương hiệu người dùng.

Quy trình trên website là đề xuất tư vấn, chưa phải cam kết dịch vụ. Chủ cơ sở cần xác nhận thông tin vật liệu, quy trình, chính sách, ảnh thật, giá và quyền sử dụng nội dung trước khi mở bán. Chưa tạo hồ sơ công trình giả; trang dự án sẽ cần ảnh và mô tả được cung cấp.

## Kiểm thử

```bash
find app public bin tests -name '*.php' -print0 | xargs -0 -n1 php -l
php tests/run.php
python3 tests/smoke.py
```

HTTP test bao gồm gửi phiếu tư vấn và kiểm tra admin nhận được yêu cầu. Các bài kiểm tra checkout cũ dùng fixture riêng để tiếp tục kiểm tra hồi quy.

## Luồng cửa hàng cũ

Các route giỏ hàng, tài khoản, checkout COD/chuyển khoản và đơn hàng cũ vẫn được giữ cho dữ liệu cũ; không đặt làm lời kêu gọi chính trên giao diện mới. Quy tắc giao hàng cố định trong luồng này chỉ là cấu hình demo, không áp dụng cho yêu cầu báo giá. Chưa tích hợp cổng thanh toán, giao vận, email/SMS, bản vẽ, hợp đồng hoặc thanh toán đặt cọc.

## Nguồn tham khảo

- https://danguyencong.com/lang-nghe-da-my-nghe-non-nuoc/
- https://www.phanthien.com/tin-tuc/thuyet-minh-ve-lang-da-non-nuoc-ngu-hanh-son-da-nang-n38.html
- https://dieukhacdaanhnhat.com/danh-muc/tuong-cong-giao/
- https://damynghenonnuocdn.com/
- https://danonnuoc.vn/
- https://tuongphatdamynghe.com/

Đọc ngày 24/10/2026. Tham khảo phân nhóm sản phẩm, nội dung làng nghề và luồng tư vấn; nội dung website được viết mới.

## Yêu cầu thương hiệu Văn Nho (ảnh tài liệu người dùng)

Đã bổ sung: đầu trang “Nghệ nhân Văn Nho – Người gửi hồn vào Đá”; hồ sơ nghệ nhân; tác phẩm đạt giải và liên kết ba bài báo; các dòng Công giáo, Phật giáo, nội thất/cảnh quan; nội dung giá, chất lượng, tư vấn đặt riêng, bản vẽ và nhu cầu kết cấu.

Cấu hình admin → Cấu hình website cho tên, hotline, địa chỉ, ảnh đầu trang, chân dung và ba ảnh tác phẩm/quá trình chế tác. Giá trị trong admin ưu tiên hơn biến môi trường. Khi nâng cấp database đã cài, chạy lại `php bin/migrate-stone.php` để thêm `site_settings`.

Ảnh chụp tài liệu IMG_0118.png có hình thu nhỏ, không được dùng như ảnh sản phẩm gốc. Các vị trí ảnh có nhãn chờ cập nhật cho đến khi cung cấp URL ảnh thật. Địa chỉ và hotline chưa được cung cấp. Mục 7 chỉ đọc được phần thiết kế bản vẽ, tính toán kết cấu; phần bên dưới bị cắt nên chưa suy diễn yêu cầu khác.

Thông tin “3 lần đoạt giải nhất” trong tài liệu chưa được xác minh: nguồn xác nhận giải Nhì “Đá nở hoa” năm 2023 và giải Nhất “Huyền thoại Ngũ Hành Sơn” năm 2026. Website ghi từng thành tích đã đối chiếu, không dùng “đứng đầu làng nghề”. Cam kết độ bền/màu sắc được diễn đạt theo loại đá, môi trường và bảo dưỡng, không bảo đảm tuyệt đối “không xuống màu”. Nhu cầu đội ngũ thiết kế/kết cấu được thể hiện qua luồng tư vấn, chưa công bố tên chuyên viên hay chứng chỉ khi chưa có hồ sơ.

Nguồn hồ sơ nghệ nhân:
- https://vnexpress.net/nguoi-nan-phoi-cho-hang-tram-tuong-da-non-nuoc-5130767.html
- https://nhandan.vn/o-lai-de-tra-on-lang-da-post881505.html
- https://thanhnien.vn/huyen-thoai-ngu-hanh-son-duoc-khac-vao-da-doat-giai-nhat-cuoc-thi-dieu-khac-185260827114406794.htm

## Nâng cấp tối ưu ngày 25/10/2026

- Báo giá: tìm theo tên/điện thoại, lọc trạng thái, 25 mục mỗi trang, xem được toàn bộ dữ liệu thay vì giới hạn 200 bản ghi. Các ký tự `%`/`_` được tìm theo nghĩa đen trong bộ lọc admin.
- Phân trang danh mục giới hạn số liên kết (tối đa 7) và giữ nguyên bộ lọc; số trang vượt phạm vi được đưa về trang cuối.
- Trang sản phẩm, nghệ nhân và dịch vụ có tiêu đề SEO riêng, mô tả và Open Graph; trang tìm kiếm/phiếu báo giá không lập chỉ mục. Khu quản trị/tài khoản dùng noindex. Chưa có canonical/sitemap vì tên miền triển khai chưa được cấu hình.
- Menu điện thoại dùng HTML details, không phụ thuộc JavaScript; thêm liên kết bỏ qua điều hướng, focus bàn phím, hỗ trợ giảm chuyển động và nút tư vấn cố định.
- Ảnh danh mục khai báo kích thước, lazy loading và giải mã bất đồng bộ; ảnh đầu trang được ưu tiên tải. Kích thước hiển thị thực tế còn phụ thuộc ảnh gốc được cung cấp.
- CSS có phiên bản theo thời gian sửa file. `public/.htaccess` bật nén/caching file tĩnh khi Apache/LiteSpeed hỗ trợ; tắt liệt kê thư mục. PHP luôn trả `Cache-Control: private, no-store` để tránh cache phiên, phiếu và dữ liệu cá nhân. Cấu hình này không được kiểm tra bằng PHP development server; cần xác minh header trên hosting sau triển khai.
- Giỏ hàng đọc sản phẩm bằng một truy vấn thay vì mỗi sản phẩm một truy vấn. Thêm index cho danh mục đang hiển thị và trạng thái báo giá.
- Từ chối GET/POST dạng mảng và giá trị quá dài với HTTP 400, session strict mode; lỗi ngoài dự kiến trả thông báo chung, chi tiết ghi log phía server.
- Đổi hồ sơ chỉ ghi tên/mật khẩu sau khi kiểm tra đầy đủ, tránh lưu một phần khi mật khẩu hiện tại sai. Đăng ký/đổi mật khẩu giới hạn 12–72 byte theo cơ chế băm hiện tại.

**Nâng cấp database cũ:** sao lưu và chạy lại `php bin/migrate-stone.php` để tạo index còn thiếu; không xóa dữ liệu. Không có thay đổi nào được tự động triển khai lên Hostinger.

Kiểm thử bổ sung: đầu vào sai định dạng, private cache header, metadata, đổi hồ sơ bị từ chối không sửa tên, quyền cấu hình, phân trang/tìm kiếm/lọc báo giá và số liên kết danh mục lớn. Chưa có điểm Lighthouse hoặc đo tải production, nên không đưa ra phần trăm tăng tốc.
