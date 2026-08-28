<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

$gallery_dir = __DIR__ . '/gallery/';
$base_url    = 'https://alexhixson.zerofour.tech/gallery/';
$extensions  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

if (!is_dir($gallery_dir)) {
    echo json_encode(["images" => []]);
    exit;
}

$images = [];

foreach (scandir($gallery_dir) as $file) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, $extensions)) continue;

    $path = $gallery_dir . $file;
    $size = getimagesize($path);

    if (!$size) continue;

    $images[] = [
        "url"       => $base_url . rawurlencode($file),
        "landscape" => $size[0] >= $size[1],
    ];
}

echo json_encode(["images" => $images]);
