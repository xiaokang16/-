<?php
session_start();
header('Content-Type: application/json');

$roomId = $_GET['room_id'] ?? '';
if (!$roomId) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$roomsFile = __DIR__ . '/../data/brawl_rooms.json';
$rooms = json_decode(file_get_contents($roomsFile), true) ?: [];

if (!isset($rooms[$roomId])) {
    echo json_encode(['success' => false, 'message' => '房间不存在']);
    exit;
}

$room = $rooms[$roomId];
// 返回玩家列表（不包含敏感信息）
echo json_encode([
    'success' => true,
    'players' => $room['players']
]);