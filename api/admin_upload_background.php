<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => '未授权']);
    exit;
}

$type = $_POST['type'] ?? ''; // login 或 game
if (!in_array($type, ['login', 'game'])) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$uploadDir = __DIR__ . '/../uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$file = $_FILES['bg'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => '文件上传失败']);
    exit;
}

$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$allowed = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
if (!in_array(strtolower($ext), $allowed)) {
    echo json_encode(['success' => false, 'message' => '只允许图片格式']);
    exit;
}

$filename = $type . '_bg_' . time() . '.' . $ext;
$dest = $uploadDir . $filename;
if (move_uploaded_file($file['tmp_name'], $dest)) {
    $settingsFile = __DIR__ . '/../data/settings.json';
    $settings = [];
    if (file_exists($settingsFile)) {
        $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
    }
    $url = '/uploads/' . $filename;
    $settings[$type . '_bg'] = $url;
    file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true, 'url' => $url]);
} else {
    echo json_encode(['success' => false, 'message' => '保存失败']);
}