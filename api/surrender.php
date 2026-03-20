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

$playersFile = __DIR__ . '/../data/players.json';
$gamesFile = __DIR__ . '/../data/games.json';
$usersFile = __DIR__ . '/../data/users.json';

$fp = fopen($playersFile, 'r+');
flock($fp, LOCK_EX);

$players = json_decode(file_get_contents($playersFile), true) ?: [];
$games = json_decode(file_get_contents($gamesFile), true) ?: [];
$users = json_decode(file_get_contents($usersFile), true) ?: [];

if (!isset($players[$username])) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    exit;
}

$roomId = $players[$username]['room_id'] ?? '';
if (!$roomId || !isset($games[$roomId])) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '没有进行中的对局']);
    exit;
}

$game = &$games[$roomId];
if ($game['winner'] !== null) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '对局已结束']);
    exit;
}

$winner = ($game['player1'] === $username) ? $game['player2'] : $game['player1'];
$game['winner'] = $winner;
$game['status'] = 'ended';

$loser = $username;

if ($winner !== 'AI') {
    $users[$winner]['wins'] = ($users[$winner]['wins'] ?? 0) + 1;
    $users[$loser]['losses'] = ($users[$loser]['losses'] ?? 0) + 1;

    $historyEntry = [
        'id' => $roomId,
        'opponent' => $winner,
        'result' => 'loss',
        'moves' => '认输',
        'date' => time()
    ];
    if (!isset($users[$loser]['history'])) $users[$loser]['history'] = [];
    array_unshift($users[$loser]['history'], $historyEntry);

    $historyEntry = [
        'id' => $roomId,
        'opponent' => $loser,
        'result' => 'win',
        'moves' => '对手认输',
        'date' => time()
    ];
    if (!isset($users[$winner]['history'])) $users[$winner]['history'] = [];
    array_unshift($users[$winner]['history'], $historyEntry);

    if (count($users[$winner]['history']) > 50)
        $users[$winner]['history'] = array_slice($users[$winner]['history'], 0, 50);
    if (count($users[$loser]['history']) > 50)
        $users[$loser]['history'] = array_slice($users[$loser]['history'], 0, 50);
}

$players[$winner]['status'] = 'online';
$players[$winner]['room_id'] = null;
$players[$loser]['status'] = 'online';
$players[$loser]['room_id'] = null;

file_put_contents($gamesFile, json_encode($games, JSON_PRETTY_PRINT));
file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT));

$chatFile = __DIR__ . '/../data/chat_' . $roomId . '.json';
if (file_exists($chatFile)) unlink($chatFile);

flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['success' => true, 'winner' => $winner]);