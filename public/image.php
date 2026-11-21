<?php
declare(strict_types=1);
$name = $_GET['name'] ?? '';
if (!is_string($name) || !preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/D', $name, $match)) {
    http_response_code(404);
    exit;
}
$file = __DIR__ . '/../backend/uploads/' . $name;
if (!is_file($file)) {
    http_response_code(404);
    exit;
}
header('Content-Type: ' . ['jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$match[1]]);
header('X-Content-Type-Options: nosniff');
header('Cache-Control: public, max-age=86400');
readfile($file);