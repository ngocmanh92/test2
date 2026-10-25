<?php
declare(strict_types=1);
requireUser(true);
$labels=['new'=>'Mới','contacted'=>'Đã liên hệ','quoted'=>'Đã báo giá','closed'=>'Đã đóng'];
$filter=(string)($_GET['status']??'');
$search=trim(substr((string)($_GET['q']??''),0,200));
$where=[]; $args=[];
if(isset($labels[$filter])) { $where[]='q.status=?'; $args[]=$filter; }
if($search!=='') {
    $where[]="(q.name LIKE ? ESCAPE '\' OR q.phone LIKE ? ESCAPE '\')";
    $term='%'.str_replace(['\\','%','_'],['\\\\','\\%','\\_'],$search).'%';
    $args[]=$term; $args[]=$term;
}
$sql=$where?' WHERE '.implode(' AND ',$where):'';
$count=(int)query('SELECT COUNT(*) FROM quote_requests q'.$sql,$args)->fetchColumn();
$pages=max(1,(int)ceil($count/25));
$current=max(1,min($pages,(int)($_GET['n']??1)));
$requests=query('SELECT q.*,p.name product_name FROM quote_requests q LEFT JOIN products p ON p.id=q.product_id'.$sql.' ORDER BY q.id DESC LIMIT 25 OFFSET '.(($current-1)*25),$args)->fetchAll();
?>
<section class="panel"><h2>Yêu cầu tư vấn & báo giá</h2>
<form method="get" class="filters"><input type="hidden" name="page" value="admin"><input type="hidden" name="tab" value="quotes"><input name="q" aria-label="Tên hoặc số điện thoại" placeholder="Tìm theo tên hoặc số điện thoại" value="<?= e($search) ?>"><select name="status" aria-label="Lọc trạng thái"><option value="">Tất cả trạng thái</option><?php foreach($labels as $key=>$label): ?><option value="<?= $key ?>" <?= $filter===$key?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select><button>Lọc</button></form>
<p><?= $count ?> yêu cầu · Trang <?= $current ?>/<?= $pages ?></p>
<?php if(!$requests): ?><p>Không có yêu cầu phù hợp.</p><?php endif; foreach($requests as $q): ?>
<article class="coupon"><h3>#<?= $q['id'] ?> · <?= e($q['name']) ?></h3><small><?= e($q['created_at']) ?> UTC · <?= $labels[$q['status']] ?></small><p><?= e($q['phone']) ?> · <?= e($q['location']) ?><br>Mẫu: <?= e($q['product_name']?:'Thiết kế riêng') ?><br><?= e($q['material']) ?> · <?= e($q['dimensions']) ?></p><p><?= nl2br(e($q['notes'])) ?></p>
<?php startForm('quote_status'); ?><input type="hidden" name="id" value="<?= $q['id'] ?>"><label>Trạng thái yêu cầu #<?= $q['id'] ?><select name="status"><?php foreach($labels as $key=>$label): ?><option value="<?= $key ?>" <?= $key===$q['status']?'selected':'' ?>><?= $label ?></option><?php endforeach ?></select></label><button>Cập nhật</button></form></article>
<?php endforeach ?>
<?= pageLinks($current,$pages,['page'=>'admin','tab'=>'quotes','status'=>$filter,'q'=>$search]) ?>
</section>
