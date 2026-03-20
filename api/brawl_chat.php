<?php
session_start();
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? null;
if (!$username) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$roomId = $data['room_id'] ?? '';
$message = trim($data['message'] ?? '');

if (!$roomId || !$message) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$chatFile = __DIR__ . '/../data/brawl_chat_' . $roomId . '.json';
$chats = [];
if (file_exists($chatFile)) {
    $chats = json_decode(file_get_contents($chatFile), true) ?: [];
}

$chats[] = [
    'username' => $username,
    'message' => htmlspecialchars($message, ENT_QUOTES, 'UTF-8'),
    'time' => time()
];

if (count($chats) > 50) {
    $chats = array_slice($chats, -50);
}

file_put_contents($chatFile, json_encode($chats, JSON_PRETTY_PRINT));

echo json_encode(['success' => true]);