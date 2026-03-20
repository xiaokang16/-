<?php
session_start();
header('Content-Type: application/json');

$roomId = $_GET['room_id'] ?? '';
if (!$roomId) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$gamesFile = __DIR__ . '/../data/brawl_games.json';
$games = json_decode(file_get_contents($gamesFile), true) ?: [];

if (!isset($games[$roomId])) {
    echo json_encode(['success' => false, 'message' => '游戏不存在']);
    exit;
}

$game = $games[$roomId];

echo json_encode([
    'success' => true,
    'players' => $game['players'],
    'board' => $game['board'],
    'turn' => $game['turn'],
    'winner' => $game['winner'],
    'status' => $game['status'],
    'last_move_x' => $game['last_move_x'],
    'last_move_y' => $game['last_move_y']
]);