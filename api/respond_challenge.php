<?php
session_start();
header('Content-Type: application/json');

$myUsername = $_SESSION['username'] ?? null;
if (!$myUsername) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$roomId = $data['room_id'] ?? '';
$accept = $data['accept'] ?? false;

$playersFile = __DIR__ . '/../data/players.json';
$gamesFile = __DIR__ . '/../data/games.json';

$fp = fopen($playersFile, 'r+');
flock($fp, LOCK_EX);

$players = json_decode(file_get_contents($playersFile), true) ?: [];
$games = json_decode(file_get_contents($gamesFile), true) ?: [];

$players[$myUsername]['last_heartbeat'] = time();

$challenge = $players[$myUsername]['pending_challenge'] ?? null;
if (!$challenge || $challenge['room_id'] !== $roomId) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '挑战已过期']);
    exit;
}

if (!isset($games[$roomId])) {
    $players[$myUsername]['pending_challenge'] = null;
    file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '房间不存在']);
    exit;
}

$game = &$games[$roomId];

if ($game['status'] !== 'waiting') {
    $players[$myUsername]['pending_challenge'] = null;
    file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '房间已关闭']);
    exit;
}

$from = $game['player1'];
$fromActive = isset($players[$from]) && 
              ($players[$from]['last_heartbeat'] ?? 0) > (time() - 15) &&
              $players[$from]['status'] === 'waiting' &&
              $players[$from]['room_id'] === $roomId;

if (!$fromActive) {
    unset($games[$roomId]);
    $players[$myUsername]['pending_challenge'] = null;
    file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT));
    file_put_contents($gamesFile, json_encode($games, JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '对方已离线']);
    exit;
}

if (!$accept) {
    unset($games[$roomId]);
    $players[$from]['status'] = 'online';
    $players[$from]['room_id'] = null;
    $players[$myUsername]['pending_challenge'] = null;
    file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT));
    file_put_contents($gamesFile, json_encode($games, JSON_PRETTY_PRINT));
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => true]);
    exit;
}

// 接受挑战，创建游戏房间
$game['player2'] = $myUsername;
$game['status'] = 'playing';
$game['turn'] = $from;
$game['hint_used'] = 0; // 新增
$games[$roomId] = $game;

$players[$from]['status'] = 'in_game';
$players[$from]['room_id'] = $roomId;
$players[$from]['last_heartbeat'] = time();
$players[$myUsername]['status'] = 'in_game';
$players[$myUsername]['room_id'] = $roomId;
$players[$myUsername]['pending_challenge'] = null;

file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT));
file_put_contents($gamesFile, json_encode($games, JSON_PRETTY_PRINT));

flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['success' => true, 'room_id' => $roomId]);