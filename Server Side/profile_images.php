<?php
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: application/json");

require_once dirname(dirname(__DIR__)) . '/site_config.php';  // ~/site_config.php

$dir      = __DIR__ . '/profile-images/';
$base_url = site_base_url() . '/profile-images/';
$exts     = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

if (!is_dir($dir)) {
    echo json_encode(["images" => []]);
    exit;
}

$images = [];
foreach (scandir($dir) as $file) {
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (in_array($ext, $exts)) {
        $images[] = $base_url . rawurlencode($file);
    }
}

echo json_encode(["images" => $images]);
