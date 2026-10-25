<?php
declare(strict_types=1);
if($page==='policy'): ?>
<section class="page-intro"><span class="eyebrow">THÔNG TIN DỊCH VỤ</span><h1>Chính sách</h1></section><article class="article"><p><?= nl2br(e(setting('policy','Chính sách đang được cơ sở cập nhật. Vui lòng liên hệ để thống nhất điều kiện chế tác, vận chuyển, thanh toán và bảo hành trước khi đặt hàng.'))) ?></p></article>
<?php elseif(isset($entry) && $entry): ?>
<article class="article"><span class="eyebrow"><?= e(['article'=>'BÀI VIẾT','project'=>'CÔNG TRÌNH','award'=>'THÀNH TÍCH','page'=>'GIỚI THIỆU'][$entry['type']]) ?></span><h1><?= e($entry['title']) ?></h1><?php if($entry['image']): ?><img class="content-image" src="<?= e($entry['image']) ?>" alt="<?= e($entry['title']) ?>" decoding="async" width="1000" height="750"><?php endif ?><div class="content-body"><?= nl2br(e($entry['body'])) ?></div></article>
<?php elseif($page==='content'): ?><h1>Không tìm thấy nội dung</h1>
<?php else:
$type=['journal'=>'article','projects'=>'project','awards'=>'award'][$page];
$title=['journal'=>'Kiến thức & câu chuyện','projects'=>'Công trình thực hiện','awards'=>'Thành tích & dấu ấn'][$page];
$count=(int)query("SELECT COUNT(*) FROM content WHERE type=? AND status='published'",[$type])->fetchColumn(); $pages=max(1,(int)ceil($count/12));$n=max(1,min($pages,(int)($_GET['n']??1)));
$rows=query("SELECT * FROM content WHERE type=? AND status='published' ORDER BY id DESC LIMIT 12 OFFSET ".(($n-1)*12),[$type])->fetchAll(); ?>
<section class="page-intro"><span class="eyebrow">NGHỆ THUẬT ĐÁ</span><h1><?= $title ?></h1></section><div class="products"><?php foreach($rows as $row): ?><article class="product"><a class="product-image" href="/?page=content&slug=<?= e($row['slug']) ?>"><?php if($row['image']): ?><img src="<?= e($row['image']) ?>" alt="<?= e($row['title']) ?>" loading="lazy" decoding="async" width="640" height="640"><?php else: ?><div class="stone-placeholder"><span>◇</span></div><?php endif ?></a><h3><a href="/?page=content&slug=<?= e($row['slug']) ?>"><?= e($row['title']) ?></a></h3></article><?php endforeach ?></div><?php if(!$rows): ?><p>Nội dung đang được cập nhật.</p><?php endif; echo pageLinks($n,$pages,['page'=>$page]); endif;
