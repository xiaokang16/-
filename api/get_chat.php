<?php
session_start();
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? null;
if (!$username) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$roomId = $_GET['room_id'] ?? '';
if (!$roomId) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$playersFile = __DIR__ . '/../data/players.json';
$players = json_decode(file_get_contents($playersFile), true) ?: [];

if (!isset($players[$username]) || $players[$username]['room_id'] !== $roomId) {
    echo json_encode(['success' => false, 'message' => '不在房间内']);
    exit;
}

$chatFile = __DIR__ . '/../data/chat_' . $roomId . '.json';
$chats = [];
if (file_exists($chatFile)) {
    $chats = json_decode(file_get_contents($chatFile), true) ?: [];
}

echo json_encode(['success' => true, 'chats' => $chats]);