<?php
session_start();
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? null;
if (!$username) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$playersFile = __DIR__ . '/../data/players.json';
$gamesFile = __DIR__ . '/../data/games.json';

$fp = fopen($playersFile, 'r+');
flock($fp, LOCK_EX);

$players = json_decode(file_get_contents($playersFile), true) ?: [];
$games = json_decode(file_get_contents($gamesFile), true) ?: [];

$players[$username]['last_heartbeat'] = time();

if ($players[$username]['status'] !== 'online') {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '你当前无法开始游戏']);
    exit;
}

$roomId = uniqid('ai_room_');
$game = [
    'id' => $roomId,
    'player1' => $username,
    'player2' => 'AI',
    'board' => array_fill(0, 15, array_fill(0, 15, 0)),
    'turn' => $username,
    'winner' => null,
    'status' => 'playing',
    'is_ai_game' => true,
    'created_at' => time(),
    'last_move' => time(),
    'hint_used' => 0
];
$games[$roomId] = $game;

$players[$username]['status'] = 'in_game';
$players[$username]['room_id'] = $roomId;

file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT));
file_put_contents($gamesFile, json_encode($games, JSON_PRETTY_PRINT));

flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['success' => true, 'room_id' => $roomId]);