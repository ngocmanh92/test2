<?php
declare(strict_types=1);
// Images already displayed on the user's danonnuoc.net reference, with original credits retained.
function themeReferences(): array {
    $nguyen=['credit'=>'Nguyễn Công','source'=>'https://danguyencong.com/lang-nghe-da-my-nghe-non-nuoc/'];
    $anh=['credit'=>'Anh Nhật','source'=>'https://dieukhacdaanhnhat.com/danh-muc/tuong-cong-giao/'];
    return [
        'village'=>['file'=>'village.jpg','alt'=>'Không gian trưng bày tượng đá ở làng nghề Non Nước']+$nguyen,
        'Tượng Phật'=>['file'=>'buddhist.png','alt'=>'Tượng Phật — ảnh tham khảo']+$anh,
        'Tượng Công giáo'=>['file'=>'catholic.png','alt'=>'Tượng Công giáo — ảnh tham khảo','credit'=>'Anh Nhật','source'=>'https://dieukhacdaanhnhat.com/danh-muc-san-pham/cg16-tuong-cong-giao/'],
        'Linh vật đá'=>['file'=>'lion.png','alt'=>'Linh vật đá — ảnh tham khảo']+$anh,
        'Đồ thờ đá'=>['file'=>'incense.jpg','alt'=>'Đồ thờ đá — ảnh tham khảo','credit'=>'Thành Đô','source'=>'https://damynghenonnuocdn.com/lu-huong-da-cam-thach-tai-may'],
        'Đá sân vườn'=>['file'=>'fountain.jpg','alt'=>'Đá sân vườn — ảnh tham khảo','credit'=>'Thành Đô','source'=>'https://damynghenonnuocdn.com/dai-phun-nuoc-bang-da-non-nuoc'],
        'Tượng nghệ thuật'=>['file'=>'art.jpg','alt'=>'Tượng nghệ thuật — ảnh tham khảo','credit'=>'Thành Đô','source'=>'https://damynghenonnuocdn.com/tuong-nghe-thuat-bang-da-cam-thach-tu-nhien'],
        'workshop'=>['file'=>'workshop.jpg','alt'=>'Thợ đá hoàn thiện chi tiết một tác phẩm tại làng nghề Non Nước']+$nguyen,
    ];
}
function themeReference(string $key): ?array {
    return setting('reference_mode','1')==='0'?null:(themeReferences()[$key]??null);
}
function referenceImage(array $reference,string $class='',bool $priority=false): void {
    $path='assets/references/'.$reference['file']; $size=getimagesize(ROOT.'/public/'.$path);
    echo '<img class="'.e($class).'" src="'.e(asset($path)).'" alt="'.e($reference['alt']).'" width="'.$size[0].'" height="'.$size[1].'" decoding="async" '.($priority?'fetchpriority="high"':'loading="lazy"').'>';
}
function referenceCredit(?array $reference): void {
    if($reference) echo '<span class="reference-credit">Ảnh tham khảo · <a href="'.e($reference['source']).'" target="_blank" rel="noopener noreferrer">'.e($reference['credit']).' ↗</a></span>';
}
function productReference(array $product): ?array {
    if($product['image']!=='') return null;
    $samples=['Tượng Quan Âm — mẫu tham khảo'=>'Tượng Phật','Tượng Đức Mẹ — mẫu tham khảo'=>'Tượng Công giáo','Cặp sư tử đá — mẫu tham khảo'=>'Linh vật đá','Lư hương đá — mẫu tham khảo'=>'Đồ thờ đá','Đài phun nước — mẫu tham khảo'=>'Đá sân vườn','Tượng nghệ thuật — mẫu tham khảo'=>'Tượng nghệ thuật'];
    return themeReference($samples[$product['name']]??'');
}
