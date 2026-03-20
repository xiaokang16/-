<?php
session_start();
header('Content-Type: application/json');

$roomId = $_GET['room_id'] ?? '';
if (!$roomId) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$chatFile = __DIR__ . '/../data/brawl_chat_' . $roomId . '.json';
$chats = [];
if (file_exists($chatFile)) {
    $chats = json_decode(file_get_contents($chatFile), true) ?: [];
}

echo json_encode(['success' => true, 'chats' => $chats]);