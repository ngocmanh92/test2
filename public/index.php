<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self'; img-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
function e(string $value): string { return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function param(string $key): string { return isset($_GET[$key]) && is_string($_GET[$key]) ? trim($_GET[$key]) : ''; }
function url(array $params): string { return 'index.php?' . http_build_query($params); }
require __DIR__ . '/../app/bootstrap.php';
$articles = array_values(array_filter(read_articles(), fn(array $a): bool => ($a['status'] ?? 'published') === 'published')); 
usort($articles, fn(array $a, array $b): int => strcmp($b['date'], $a['date']));
$categories = ['cong-nghe'=>'Công nghệ', 'doi-song'=>'Đời sống', 'kinh-doanh'=>'Kinh doanh', 'the-thao'=>'Thể thao'];
$query = substr(param('q'), 0, 200);
$category = param('category');
$slug = param('article');
$selected = null;
foreach ($articles as $article) { if ($article['slug'] === $slug) { $selected = $article; break; } }
$notFound = ($slug !== '' && $selected === null) || ($category !== '' && !isset($categories[$category]));
if ($notFound) { http_response_code(404); }
$filtered = array_values(array_filter($articles, function(array $a) use ($category, $query): bool {
    $text = $a['title'] . ' ' . $a['summary'] . ' ' . implode(' ', $a['body']);
    $match = $query === '' || preg_match('/' . preg_quote($query, '/') . '/iu', $text) === 1;
    return ($category === '' || $a['category'] === $category) && $match;
}));
$page = max(1, (int)param('page'));
$pageCount = max(1, (int)ceil(count($filtered) / 6));
$page = min($page, $pageCount);
$visible = array_slice($filtered, ($page - 1) * 6, 6);
$title = $selected ? $selected['title'] : 'Nhịp Tin — Góc nhìn mới mỗi ngày';
function card(array $article, array $categories): void { ?>
<article class="card"><a class="art <?= e($article['category']) ?>" href="<?= e(url(['article'=>$article['slug']])) ?>" tabindex="-1" aria-hidden="true"><span><?= e($article['symbol']) ?></span></a><div class="card-body"><div class="meta"><?= e($categories[$article['category']]) ?> <span>· <?= e(date('d/m/Y', strtotime($article['date']))) ?></span></div><h3><a href="<?= e(url(['article'=>$article['slug']])) ?>"><?= e($article['title']) ?></a></h3><p><?= e($article['summary']) ?></p><div class="byline"><?= e($article['author']) ?> <span><?= e((string)$article['minutes']) ?> phút đọc</span></div></div></article>
<?php } ?>
<!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title) ?></title><meta name="description" content="<?= e($selected ? $selected['summary'] : 'Nhịp Tin: trang tin tức tiếng Việt về công nghệ, đời sống, kinh doanh và thể thao.') ?>"><link rel="stylesheet" href="assets/style.css"></head><body>
<a class="skip" href="#main">Đến nội dung</a><div class="topbar"><span>Góc nhìn mới, nhịp sống mới</span><span>Phiên bản mẫu · Nội dung minh họa</span></div>
<header><a class="brand" href="index.php">nhịp<span>tin.</span></a><form class="search" action="index.php" method="get" role="search"><label class="sr-only" for="q">Tìm kiếm tin tức</label><input id="q" name="q" type="search" maxlength="200" value="<?= e($query) ?>" placeholder="Bạn muốn đọc gì hôm nay?"><button type="submit">Tìm kiếm</button></form></header>
<nav aria-label="Chuyên mục"><a class="<?= $category === '' ? 'active' : '' ?>" href="index.php">Trang chủ</a><?php foreach ($categories as $key=>$label): ?><a class="<?= $category === $key ? 'active' : '' ?>" href="<?= e(url(['category'=>$key])) ?>"><?= e($label) ?></a><?php endforeach; ?></nav>
<main id="main">
<?php if ($notFound): ?><section class="empty"><p class="eyebrow">404</p><h1>Không tìm thấy nội dung</h1><p>Bài viết hoặc chuyên mục này không tồn tại.</p><a class="button" href="index.php">Về trang chủ</a></section>
<?php elseif ($selected): ?><div class="breadcrumb"><a href="index.php">Trang chủ</a> / <a href="<?= e(url(['category'=>$selected['category']])) ?>"><?= e($categories[$selected['category']]) ?></a></div><article class="article"><p class="eyebrow"><?= e($categories[$selected['category']]) ?></p><h1><?= e($selected['title']) ?></h1><p class="lead"><?= e($selected['summary']) ?></p><div class="article-meta"><?= e($selected['author']) ?> · <?= e(date('d/m/Y', strtotime($selected['date']))) ?> · <?= e((string)$selected['minutes']) ?> phút đọc</div><div class="article-art art <?= e($selected['category']) ?>" aria-hidden="true"><span><?= e($selected['symbol']) ?></span></div><p class="sample">Bài viết mẫu để minh họa giao diện, không phải tin tức đã xác minh.</p><div class="prose"><?php foreach ($selected['body'] as $paragraph): ?><p><?= e($paragraph) ?></p><?php endforeach; ?></div></article><section><div class="section-head"><h2>Cùng chuyên mục</h2></div><div class="grid"><?php $related = array_slice(array_values(array_filter($articles, fn(array $a): bool => $a['category'] === $selected['category'] && $a['slug'] !== $selected['slug'])), 0, 3); foreach ($related as $a) { card($a, $categories); } ?></div></section>
<?php else: ?>
<?php if ($category === '' && $query === '' && $page === 1 && $articles): $hero = $articles[0]; ?><section class="hero"><div class="hero-copy"><p class="eyebrow">ĐIỂM NHẤN HÔM NAY</p><h1><a href="<?= e(url(['article'=>$hero['slug']])) ?>"><?= e($hero['title']) ?></a></h1><p><?= e($hero['summary']) ?></p><a class="button" href="<?= e(url(['article'=>$hero['slug']])) ?>">Khám phá câu chuyện <span>↗</span></a><div class="hero-note">CÔNG NGHỆ & CUỘC SỐNG · <?= e((string)$hero['minutes']) ?> PHÚT ĐỌC</div></div><div class="hero-art" aria-hidden="true"><div class="orbit"></div><div class="orb">AI</div><span class="floating">Ý tưởng của ngày mai</span><span class="art-caption">THE NEXT CHAPTER / 01</span></div></section><?php endif; ?>
<section><div class="section-head"><div><p class="eyebrow">DÒNG CHẢY THÔNG TIN</p><h2><?= $query !== '' ? 'Kết quả tìm kiếm' : ($category !== '' ? e($categories[$category]) : 'Tin mới nhất') ?></h2></div><span><?= count($filtered) ?> bài viết</span></div><?php if ($query !== ''): ?><p class="result">Tìm kiếm: “<?= e($query) ?>” · <a href="index.php">Xóa bộ lọc</a></p><?php endif; ?><?php if (!$visible): ?><div class="empty"><h3>Chưa có bài viết phù hợp</h3><p>Thử một từ khóa khác hoặc chọn chuyên mục bên trên.</p></div><?php else: ?><div class="grid"><?php foreach ($visible as $a) { card($a, $categories); } ?></div><?php endif; ?>
<?php if ($pageCount > 1): ?><nav class="pagination" aria-label="Phân trang"><?php for ($i=1; $i<=$pageCount; $i++): ?><a <?= $page === $i ? 'aria-current="page"' : '' ?> href="<?= e(url(['category'=>$category,'q'=>$query,'page'=>$i])) ?>"><?= $i ?></a><?php endfor; ?></nav><?php endif; ?></section>
<aside class="editor-note"><div><p class="eyebrow">ĐỌC CHẬM, HIỂU SÂU</p><h2>Mỗi câu chuyện, một góc nhìn.</h2><p>Khám phá những ý tưởng gần gũi với cuộc sống của bạn.</p></div><a href="<?= e(url(['category'=>'doi-song'])) ?>">Khám phá đời sống ↗</a></aside>
<?php endif; ?></main><footer><a class="brand" href="index.php">nhịp<span>tin.</span></a><p>Trang tin tức PHP · Nội dung mẫu phục vụ phát triển website.</p><span>© <?= date('Y') ?> Nhịp Tin</span></footer></body></html>
