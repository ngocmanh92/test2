<?php
declare(strict_types=1);
const DATA_FILE = __DIR__ . '/../data/articles.json';
const CATEGORIES = ['cong-nghe'=>'Công nghệ', 'doi-song'=>'Đời sống', 'kinh-doanh'=>'Kinh doanh', 'the-thao'=>'Thể thao'];
function read_articles(): array {
    $file = fopen(DATA_FILE, 'r');
    if (!$file || !flock($file, LOCK_SH)) { throw new RuntimeException('Không đọc được dữ liệu.'); }
    try { return json_decode(stream_get_contents($file), true, 512, JSON_THROW_ON_ERROR); }
    finally { flock($file, LOCK_UN); fclose($file); }
}
function save_article(array $article, string $original): void {
    $file = fopen(DATA_FILE, 'r+');
    if (!$file || !flock($file, LOCK_EX)) { throw new RuntimeException('Không ghi được dữ liệu.'); }
    try {
        $articles = json_decode(stream_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $found = false;
        foreach ($articles as &$item) {
            if ($item['slug'] === $article['slug'] && $item['slug'] !== $original) { throw new RuntimeException('Slug đã tồn tại.'); }
            if ($original !== '' && $item['slug'] === $original) { $item = $article; $found = true; }
        }
        unset($item);
        if ($original !== '' && !$found) { throw new RuntimeException('Bài viết không còn tồn tại.'); }
        if (!$found) { $articles[] = $article; }
        $json = json_encode($articles, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        rewind($file);
        if (fwrite($file, $json) !== strlen($json)) { throw new RuntimeException('Ghi dữ liệu thất bại.'); }
        ftruncate($file, strlen($json)); fflush($file);
    } finally { flock($file, LOCK_UN); fclose($file); }
}
