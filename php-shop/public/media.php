<?php
declare(strict_types=1);
define('PUBLIC_MEDIA',true);
require __DIR__.'/../app/bootstrap.php';
$m=query('SELECT * FROM media WHERE id=?',[(int)($_GET['id']??0)])->fetch();
if(!$m || !preg_match('/^[a-f0-9]{48}\.(jpg|png|webp)$/D',$m['filename'])) { http_response_code(404); exit; }
$path=STORAGE.'/media/'.$m['filename'];
if(!is_file($path)) { http_response_code(404); exit; }
header('Content-Type: '.$m['mime']); header('X-Content-Type-Options: nosniff'); header('Cache-Control: public, max-age=2592000, immutable');
header('Content-Length: '.filesize($path)); readfile($path);
