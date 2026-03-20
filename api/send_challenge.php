<?php
session_start();
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? null;
if (!$username) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$target = $data['target'] ?? '';

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
    echo json_encode(['success' => false, 'message' => '你当前无法发起挑战']);
    exit;
}

$targetActive = isset($players[$target]) && 
                ($players[$target]['last_heartbeat'] ?? 0) > (time() - 15) &&
                $players[$target]['status'] === 'online';
if (!$targetActive) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '对方不在线']);
    exit;
}

$roomId = uniqid('room_');
$game = [
    'id' => $roomId,
    'player1' => $username,
    'player2' => null,
    'board' => array_fill(0, 15, array_fill(0, 15, 0)),
    'turn' => $username,
    'winner' => null,
    'status' => 'waiting',
    'created_at' => time(),
    'last_move' => time()
];
$games[$roomId] = $game;

$players[$username]['status'] = 'waiting';
$players[$username]['room_id'] = $roomId;

$players[$target]['pending_challenge'] = [
    'from' => $username,
    'room_id' => $roomId,
    'time' => time()
];

file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT));
file_put_contents($gamesFile, json_encode($games, JSON_PRETTY_PRINT));

flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['success' => true, 'room_id' => $roomId]);