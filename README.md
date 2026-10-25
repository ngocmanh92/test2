# Đá Non Nước — website PHP và quản trị

Giao diện được đồng bộ theo website của người dùng **danonnuoc.net**: tông kem/xanh trầm, ảnh hero dạng vòm, nhóm sản phẩm có ảnh, phần chế tác và FAQ. Hồ sơ nghệ nhân Văn Nho và bộ quản trị mở rộng được giữ lại.

Mã website: [`php-shop/`](php-shop/README.md). Quản trị hỗ trợ ảnh/gallery, CMS, danh mục con, báo giá, nhân viên/phân quyền, 2FA, nhật ký, email queue và backup/restore.

Cài đặt/nâng cấp: xem [README](php-shop/README.md). Document root `php-shop/public/`; database cũ cần migration nếu chưa cập nhật bộ quản trị. Cập nhật giao diện không ghi đè dữ liệu hoặc tự triển khai lên hosting.

Ảnh tham khảo được ghi nguồn, có thể tắt hoặc thay ảnh riêng trong admin. Email cần cấu hình mail/cron thật. Bản in hỗ trợ Lưu PDF qua trình duyệt; ZIP backup cần extension zip.
