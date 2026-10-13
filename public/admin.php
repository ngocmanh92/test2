<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
session_start(['use_strict_mode'=>1, 'cookie_httponly'=>true, 'cookie_samesite'=>'Strict', 'cookie_secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function input(string $key): string { return isset($_POST[$key]) && is_string($_POST[$key]) ? trim($_POST[$key]) : ''; }
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], input('csrf'))) { http_response_code(403); exit('Yêu cầu không hợp lệ.'); }
    if (input('action') === 'login') {
        $path = __DIR__ . '/../data/admin.json';
        $admin = is_file($path) ? json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR) : null;
        if ($admin && hash_equals($admin['username'], input('username')) && password_verify(input('password'), $admin['password_hash'])) {
            session_regenerate_id(true); $_SESSION['admin'] = true; $_SESSION['csrf'] = bin2hex(random_bytes(32));
            header('Location: admin.php'); exit;
        }
        $error = 'Đăng nhập thất bại hoặc chưa tạo tài khoản.';
    } elseif (!empty($_SESSION['admin'])) {
        if (input('action') === 'logout') { $_SESSION = []; session_destroy(); header('Location: admin.php'); exit; }
        if (input('action') === 'save') {
            try {
                $slug = input('slug'); $date = input('date'); $status = input('status');
                if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) || !isset(CATEGORIES[input('category')]) || input('title') === '' || input('body') === '' || !in_array($status, ['draft','published'], true)) { throw new RuntimeException('Kiểm tra tiêu đề, slug, chuyên mục và nội dung.'); }
                $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
                if (!$parsed || $parsed->format('Y-m-d') !== $date) { throw new RuntimeException('Ngày không hợp lệ.'); }
                save_article(['slug'=>$slug, 'title'=>input('title'), 'category'=>input('category'), 'summary'=>input('summary'), 'author'=>input('author'), 'date'=>$date, 'symbol'=>'NT', 'minutes'=>max(1, min(120, (int)input('minutes'))), 'body'=>preg_split('/\R\s*\R/u', input('body')), 'status'=>$status], input('original'));
                header('Location: admin.php'); exit;
            } catch (RuntimeException $exception) { $error = $exception->getMessage(); }
        }
    } else { http_response_code(403); exit('Cần đăng nhập.'); }
}
$articles = !empty($_SESSION['admin']) ? read_articles() : [];
$edit = ['slug'=>'', 'title'=>'', 'category'=>'cong-nghe', 'summary'=>'', 'author'=>'', 'date'=>date('Y-m-d'), 'minutes'=>3, 'body'=>[], 'status'=>'draft'];
$original = '';
if (isset($_GET['edit']) && is_string($_GET['edit'])) { foreach ($articles as $item) { if ($item['slug'] === $_GET['edit']) { $edit = $item; $original = $item['slug']; } } }
if ($error !== '' && input('action') === 'save') { foreach (['slug','title','category','summary','author','date','minutes','status'] as $key) { $edit[$key] = input($key); } $edit['body'] = [input('body')]; $original = input('original'); }
?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Quản trị Nhịp Tin</title><link rel="stylesheet" href="assets/style.css"></head><body><main><h1>Quản trị Nhịp Tin</h1><p><a href="index.php">Xem website</a></p><?php if ($error): ?><p role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if (empty($_SESSION['admin'])): ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="login"><p><label>Tên đăng nhập <input name="username" required autocomplete="username"></label></p><p><label>Mật khẩu <input type="password" name="password" required autocomplete="current-password"></label></p><button>Đăng nhập</button></form>
<?php else: ?>
<form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><button name="action" value="logout">Đăng xuất</button></form>
<h2>Bài viết</h2><p><a href="admin.php">Tạo bài mới</a></p><ul><?php foreach ($articles as $item): ?><li><a href="admin.php?edit=<?= e(rawurlencode($item['slug'])) ?>"><?= e($item['title']) ?></a> — <?= ($item['status'] ?? 'published') === 'draft' ? 'Nháp' : 'Đã đăng' ?></li><?php endforeach; ?></ul>
<h2><?= $original === '' ? 'Tạo bài mới' : 'Sửa bài' ?></h2><form method="post"><input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="original" value="<?= e($original) ?>">
<?php foreach (['title'=>'Tiêu đề','slug'=>'Slug (không dấu, dùng dấu -)','summary'=>'Tóm tắt','author'=>'Tác giả','date'=>'Ngày YYYY-MM-DD','minutes'=>'Phút đọc'] as $key=>$label): ?><p><label><?= e($label) ?><input name="<?= e($key) ?>" value="<?= e((string)$edit[$key]) ?>" required maxlength="500"></label></p><?php endforeach; ?>
<p><label>Chuyên mục <select name="category"><?php foreach (CATEGORIES as $key=>$label): ?><option value="<?= e($key) ?>" <?= $edit['category'] === $key ? 'selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label></p>
<p><label>Nội dung (dòng trống ngăn đoạn)<textarea name="body" rows="12" required><?= e(implode("\n\n", $edit['body'])) ?></textarea></label></p>
<p><label>Trạng thái <select name="status"><option value="draft" <?= ($edit['status'] ?? 'published') === 'draft' ? 'selected' : '' ?>>Nháp</option><option value="published" <?= ($edit['status'] ?? 'published') === 'published' ? 'selected' : '' ?>>Đăng</option></select></label></p><button>Lưu bài</button></form>
<?php endif; ?></main></body></html>
