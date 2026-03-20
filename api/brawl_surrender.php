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

if (!$roomId) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$gamesFile = __DIR__ . '/../data/brawl_games.json';

$fp = fopen($gamesFile, 'r+');
flock($fp, LOCK_EX);

$games = json_decode(file_get_contents($gamesFile), true) ?: [];

if (!isset($games[$roomId])) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '游戏不存在']);
    exit;
}

$game = &$games[$roomId];

if ($game['status'] !== 'playing' || $game['winner'] !== null) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '游戏未进行中或已结束']);
    exit;
}

// 查找该玩家
$playerIndex = -1;
$playerColor = null;
foreach ($game['players'] as $index => $player) {
    if ($player['username'] === $username) {
        $playerIndex = $index;
        $playerColor = $player['color'];
        break;
    }
}

if ($playerIndex === -1) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '你不在这个游戏中']);
    exit;
}

// 标记投降
$game['players'][$playerIndex]['surrendered'] = true;

// 检查该阵营是否所有真人玩家都已投降
$allSurrendered = true;
$hasHuman = false;
foreach ($game['players'] as $player) {
    if ($player['color'] === $playerColor) {
        if (!$player['isAI']) {
            $hasHuman = true;
            if (!isset($player['surrendered']) || !$player['surrendered']) {
                $allSurrendered = false;
                break;
            }
        }
    }
}

if (!$hasHuman || $allSurrendered) {
    $game['winner'] = ($playerColor === 'black') ? 'white' : 'black';
    $game['status'] = 'ended';
}

file_put_contents($gamesFile, json_encode($games, JSON_PRETTY_PRINT));

flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['success' => true]);