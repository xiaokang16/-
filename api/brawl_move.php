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
$x = (int)($data['x'] ?? -1);
$y = (int)($data['y'] ?? -1);

if (!$roomId || $x < 0 || $x >= 15 || $y < 0 || $y >= 15) {
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

// 检查游戏状态
if ($game['status'] !== 'playing') {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '游戏未进行中']);
    exit;
}

if ($game['winner'] !== null) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '游戏已结束']);
    exit;
}

// 检查当前轮到谁
$currentPlayerIndex = $game['turn'];
$currentPlayer = $game['players'][$currentPlayerIndex] ?? null;

if (!$currentPlayer) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '游戏状态错误']);
    exit;
}

// 检查是否是该玩家的回合
if ($currentPlayer['username'] !== $username && !$currentPlayer['isAI']) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '未轮到你']);
    exit;
}

// 如果是AI，不应该通过此接口落子
if ($currentPlayer['isAI']) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => 'AI回合不能手动落子']);
    exit;
}

// 检查落子位置是否为空
if ($game['board'][$x][$y] !== 0) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '该位置已有棋子']);
    exit;
}

// 确定棋子颜色
$piece = ($currentPlayer['color'] === 'black') ? 1 : 2;

// 落子
$game['board'][$x][$y] = $piece;
$game['last_move_x'] = $x;
$game['last_move_y'] = $y;

// 检查胜利
if (checkWin($game['board'], $x, $y, $piece)) {
    $game['winner'] = $currentPlayer['color'];
    $game['status'] = 'ended';
} else {
    // 切换到下一个玩家（循环）
    $nextTurn = ($game['turn'] + 1) % count($game['players']);
    $game['turn'] = $nextTurn;
}

$game['last_move'] = time();

file_put_contents($gamesFile, json_encode($games, JSON_PRETTY_PRINT));

flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['success' => true]);

function checkWin($board, $x, $y, $piece) {
    $directions = [[1,0],[0,1],[1,1],[1,-1]];
    foreach ($directions as $dir) {
        $count = 1;
        for ($i = 1; $i < 5; $i++) {
            $nx = $x + $dir[0]*$i;
            $ny = $y + $dir[1]*$i;
            if ($nx<0||$nx>=15||$ny<0||$ny>=15||$board[$nx][$ny]!=$piece) break;
            $count++;
        }
        for ($i = 1; $i < 5; $i++) {
            $nx = $x - $dir[0]*$i;
            $ny = $y - $dir[1]*$i;
            if ($nx<0||$nx>=15||$ny<0||$ny>=15||$board[$nx][$ny]!=$piece) break;
            $count++;
        }
        if ($count >= 5) return true;
    }
    return false;
}