<?php
session_start();
header('Content-Type: application/json');

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

$currentPlayerIndex = $game['turn'];
$currentPlayer = $game['players'][$currentPlayerIndex] ?? null;

if (!$currentPlayer || !$currentPlayer['isAI']) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '不是AI回合']);
    exit;
}

$board = $game['board'];
$player = ($currentPlayer['color'] === 'black') ? 1 : 2;
$aiMove = getAIMove($board, $player);

if (!$aiMove) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => 'AI无法落子']);
    exit;
}

$x = $aiMove['x'];
$y = $aiMove['y'];

$game['board'][$x][$y] = $player;
$game['last_move_x'] = $x;
$game['last_move_y'] = $y;

if (checkWin($game['board'], $x, $y, $player)) {
    $game['winner'] = $currentPlayer['color'];
    $game['status'] = 'ended';
} else {
    $nextTurn = ($game['turn'] + 1) % count($game['players']);
    $game['turn'] = $nextTurn;
}

$game['last_move'] = time();

file_put_contents($gamesFile, json_encode($games, JSON_PRETTY_PRINT));

flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['success' => true]);

// AI算法函数（复用自 ai_move.php）
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
    $directions = [[1,0],[0,1],[1,1],[1,-1]];
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