<?php
session_start();
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? null;
if (!$username) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$x = (int)($data['x'] ?? -1);
$y = (int)($data['y'] ?? -1);

$playersFile = __DIR__ . '/../data/players.json';
$gamesFile = __DIR__ . '/../data/games.json';
$usersFile = __DIR__ . '/../data/users.json';

$fp = fopen($playersFile, 'r+');
flock($fp, LOCK_EX);

$players = json_decode(file_get_contents($playersFile), true) ?: [];
$games = json_decode(file_get_contents($gamesFile), true) ?: [];
$users = json_decode(file_get_contents($usersFile), true) ?: [];

if (isset($players[$username])) {
    $players[$username]['last_heartbeat'] = time();
}

$roomId = $players[$username]['room_id'] ?? null;
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
if ($game['turn'] !== $username) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '未轮到你']);
    exit;
}
if ($x < 0 || $x >= 15 || $y < 0 || $y >= 15 || $game['board'][$x][$y] !== 0) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '无效落子']);
    exit;
}

$piece = ($game['player1'] === $username) ? 1 : 2;
$game['board'][$x][$y] = $piece;
$game['last_move'] = time();

if (checkWin($game['board'], $x, $y, $piece)) {
    $game['winner'] = $username;
    $loser = ($game['player1'] === $username) ? $game['player2'] : $game['player1'];

    if ($loser !== 'AI') {
        $users[$username]['wins'] = ($users[$username]['wins'] ?? 0) + 1;
        $users[$username]['score'] = ($users[$username]['score'] ?? 1000) + 10;
        $users[$loser]['losses'] = ($users[$loser]['losses'] ?? 0) + 1;
        $users[$loser]['score'] = max(0, ($users[$loser]['score'] ?? 1000) - 5);

        foreach ([$username, $loser] as $u) {
            $score = $users[$u]['score'];
            if ($score >= 2600) $users[$u]['rank'] = '九段';
            elseif ($score >= 2400) $users[$u]['rank'] = '八段';
            elseif ($score >= 2200) $users[$u]['rank'] = '七段';
            elseif ($score >= 2000) $users[$u]['rank'] = '六段';
            elseif ($score >= 1800) $users[$u]['rank'] = '五段';
            elseif ($score >= 1600) $users[$u]['rank'] = '四段';
            elseif ($score >= 1400) $users[$u]['rank'] = '三段';
            elseif ($score >= 1200) $users[$u]['rank'] = '二段';
            else $users[$u]['rank'] = '初段';
        }

        $players[$username]['rank'] = $users[$username]['rank'];
        $players[$loser]['rank'] = $users[$loser]['rank'];
    }

    $players[$username]['status'] = 'online';
    $players[$username]['room_id'] = null;
    if ($loser !== 'AI') {
        $players[$loser]['status'] = 'online';
        $players[$loser]['room_id'] = null;
    }
} else {
    $game['turn'] = ($game['turn'] === $game['player1']) ? $game['player2'] : $game['player1'];
}

file_put_contents($gamesFile, json_encode($games, JSON_PRETTY_PRINT));
file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT));

flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['success' => true, 'game_over' => isset($game['winner'])]);

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