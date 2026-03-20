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

$playersFile = __DIR__ . '/../data/players.json';
$gamesFile = __DIR__ . '/../data/games.json';
$usersFile = __DIR__ . '/../data/users.json';

$fp = fopen($playersFile, 'r+');
flock($fp, LOCK_EX);

$players = json_decode(file_get_contents($playersFile), true) ?: [];
$games = json_decode(file_get_contents($gamesFile), true) ?: [];
$users = json_decode(file_get_contents($usersFile), true) ?: [];

// 验证用户
if (!isset($players[$username])) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    exit;
}

// 检查房间
if (!isset($games[$roomId])) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '房间不存在']);
    exit;
}

$game = &$games[$roomId];

// 检查是否轮到该玩家
if ($game['turn'] !== $username) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '现在不是你的回合']);
    exit;
}

// 检查游戏是否已结束
if ($game['winner'] !== null) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '游戏已结束']);
    exit;
}

// 检查指点卡使用次数
if (!isset($game['hint_used'])) {
    $game['hint_used'] = 0;
}
if ($game['hint_used'] >= 2) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '本局最多使用2张指点卡']);
    exit;
}

// 检查用户指点卡数量
if (!isset($users[$username]['hint_cards'])) {
    $users[$username]['hint_cards'] = 0;
}
if ($users[$username]['hint_cards'] <= 0) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '没有指点卡']);
    exit;
}

// 扣除用户指点卡
$users[$username]['hint_cards'] -= 1;

// 计算AI落子
$board = $game['board'];
$player = ($game['player1'] === $username) ? 1 : 2; // 玩家棋子颜色
$aiMove = getAIMove($board, $player);

if (!$aiMove) {
    // 无空位，不应该发生
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => 'AI计算失败']);
    exit;
}

$x = $aiMove['x'];
$y = $aiMove['y'];

// 落子
$game['board'][$x][$y] = $player;

// 检查胜利
if (checkWin($game['board'], $x, $y, $player)) {
    $game['winner'] = $username;
}

// 增加指点卡使用次数
$game['hint_used'] += 1;

// 切换回合（如果游戏未结束）
if (!$game['winner']) {
    $game['turn'] = ($game['turn'] === $game['player1']) ? $game['player2'] : $game['player1'];
}

// 保存数据
file_put_contents($gamesFile, json_encode($games, JSON_PRETTY_PRINT));
file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT));

// 发送聊天消息
$chatFile = __DIR__ . '/../data/chat_' . $roomId . '.json';
$chats = [];
if (file_exists($chatFile)) {
    $chats = json_decode(file_get_contents($chatFile), true) ?: [];
}
$chats[] = [
    'username' => '系统',
    'message' => $username . ' 使用了一张指点卡',
    'time' => time()
];
if (count($chats) > 50) {
    $chats = array_slice($chats, -50);
}
file_put_contents($chatFile, json_encode($chats, JSON_PRETTY_PRINT));

flock($fp, LOCK_UN);
fclose($fp);

echo json_encode([
    'success' => true,
    'move' => ['x' => $x, 'y' => $y],
    'hint_cards' => $users[$username]['hint_cards']
]);

// ========== AI算法辅助函数 ==========
function getAIMove($board, $player) {
    $emptyPositions = [];
    for ($i = 0; $i < 15; $i++) {
        for ($j = 0; $j < 15; $j++) {
            if ($board[$i][$j] === 0) {
                $emptyPositions[] = ['x' => $i, 'y' => $j];
            }
        }
    }
    if (empty($emptyPositions)) return null;
    
    $centerScore = [];
    foreach ($emptyPositions as $pos) {
        $x = $pos['x']; $y = $pos['y'];
        $distance = abs($x - 7) + abs($y - 7);
        $centerScore[$x.','.$y] = (14 - $distance) * 2;
    }
    
    $scores = [];
    foreach ($emptyPositions as $pos) {
        $x = $pos['x']; $y = $pos['y'];
        $key = $x.','.$y;
        $offensiveScore = evaluatePosition($board, $x, $y, $player);
        $opponent = ($player == 1) ? 2 : 1;
        $defensiveScore = evaluatePosition($board, $x, $y, $opponent) * 1.2;
        $scores[$key] = $offensiveScore + $defensiveScore + ($centerScore[$key] ?? 0);
    }
    
    arsort($scores);
    $bestKey = array_key_first($scores);
    list($bestX, $bestY) = explode(',', $bestKey);
    return ['x' => (int)$bestX, 'y' => (int)$bestY];
}

function evaluatePosition($board, $x, $y, $player) {
    $totalScore = 0;
    $directions = [[1,0], [0,1], [1,1], [1,-1]];
    foreach ($directions as $dir) {
        $count = 1;
        $leftBlocked = false;
        $rightBlocked = false;
        
        for ($i = 1; $i < 5; $i++) {
            $nx = $x + $dir[0]*$i;
            $ny = $y + $dir[1]*$i;
            if ($nx<0||$nx>=15||$ny<0||$ny>=15) { $rightBlocked = true; break; }
            if ($board[$nx][$ny] === $player) $count++;
            elseif ($board[$nx][$ny] === 0) break;
            else { $rightBlocked = true; break; }
        }
        for ($i = 1; $i < 5; $i++) {
            $nx = $x - $dir[0]*$i;
            $ny = $y - $dir[1]*$i;
            if ($nx<0||$nx>=15||$ny<0||$ny>=15) { $leftBlocked = true; break; }
            if ($board[$nx][$ny] === $player) $count++;
            elseif ($board[$nx][$ny] === 0) break;
            else { $leftBlocked = true; break; }
        }
        
        if ($count >= 5) $totalScore += 100000;
        elseif ($count == 4) {
            if (!$leftBlocked && !$rightBlocked) $totalScore += 10000;
            elseif (!$leftBlocked || !$rightBlocked) $totalScore += 1000;
        } elseif ($count == 3) {
            if (!$leftBlocked && !$rightBlocked) $totalScore += 2000;
            elseif (!$leftBlocked || !$rightBlocked) $totalScore += 200;
        } elseif ($count == 2) {
            if (!$leftBlocked && !$rightBlocked) $totalScore += 300;
            elseif (!$leftBlocked || !$rightBlocked) $totalScore += 30;
        } elseif ($count == 1) {
            $totalScore += 5;
        }
    }
    return $totalScore;
}

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