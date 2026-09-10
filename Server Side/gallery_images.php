<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once dirname(dirname(__DIR__)) . '/site_config.php';  // ~/site_config.php

// Both the folder and the URL come from where this file sits and which host was
// requested, so the same file works on any site with no configuration.
$gallery_dir = __DIR__ . '/gallery/';
$base_url    = site_base_url() . '/gallery/';
$extensions  = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

if (!is_dir($gallery_dir)) {
    echo json_encode(["images" => []]);
    exit;
}

$images = [];
foreach (scandir($gallery_dir) as $file) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, $extensions)) continue;

    $size = @getimagesize($gallery_dir . $file);
    if (!$size) continue;  // unreadable or not actually an image

    $images[] = [
        "url"       => $base_url . rawurlencode($file),
        "landscape" => $size[0] >= $size[1],
    ];
}

echo json_encode(["images" => $images]);
