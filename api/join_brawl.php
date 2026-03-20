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
$color = $data['color'] ?? '';

if (!$roomId || !in_array($color, ['black', 'white'])) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$roomsFile = __DIR__ . '/../data/brawl_rooms.json';
$rooms = json_decode(file_get_contents($roomsFile), true) ?: [];

if (!isset($rooms[$roomId])) {
    echo json_encode(['success' => false, 'message' => '房间不存在']);
    exit;
}

$room = &$rooms[$roomId];

// 检查房间状态
if ($room['status'] !== 'waiting') {
    echo json_encode(['success' => false, 'message' => '游戏已开始']);
    exit;
}

// 检查是否已满
$count = 0;
foreach ($room['players'] as $p) {
    if ($p['color'] === $color) $count++;
}
if ($count >= 3) {
    echo json_encode(['success' => false, 'message' => '该阵营已满']);
    exit;
}

// 添加玩家
$room['players'][] = [
    'username' => $username,
    'color' => $color,
    'isAI' => false,
    'ready' => true,
    'joined_at' => time()
];

file_put_contents($roomsFile, json_encode($rooms, JSON_PRETTY_PRINT));

echo json_encode(['success' => true]);