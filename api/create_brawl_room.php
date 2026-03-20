<?php
session_start();
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? null;
if (!$username) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$color = $data['color'] ?? '';

if (!in_array($color, ['black', 'white'])) {
    echo json_encode(['success' => false, 'message' => '阵营无效']);
    exit;
}

$roomsFile = __DIR__ . '/../data/brawl_rooms.json';
$rooms = [];
if (file_exists($roomsFile)) {
    $rooms = json_decode(file_get_contents($roomsFile), true) ?: [];
}

$roomId = uniqid('brawl_room_');
$room = [
    'id' => $roomId,
    'creator' => $username,
    'players' => [
        [
            'username' => $username,
            'color' => $color,
            'isAI' => false,
            'ready' => true,
            'joined_at' => time()
        ]
    ],
    'status' => 'waiting',
    'created_at' => time()
];
$rooms[$roomId] = $room;

file_put_contents($roomsFile, json_encode($rooms, JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'room_id' => $roomId]);