<?php
session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$roomId = $data['room_id'] ?? '';

$gamesFile = __DIR__ . '/../data/games.json';
$games = json_decode(file_get_contents($gamesFile), true) ?: [];

if (!isset($games[$roomId]) || $games[$roomId]['player2'] !== 'AI') {
    echo json_encode(['success' => false, 'message' => '房间不存在']);
    exit;
}

$game = &$games[$roomId];

if ($game['turn'] !== 'AI' || $game['winner'] !== null) {
    echo json_encode(['success' => false, 'message' => '未轮到AI']);
    exit;
}

$board = $game['board'];
$move = getAIMove($board);

if (!$move) {
    echo json_encode(['success' => false, 'message' => '无可用落子位置']);
    exit;
}

$x = $move['x'];
$y = $move['y'];

$board[$x][$y] = 2;

if (checkWin($board, $x, $y, 2)) {
    $game['winner'] = 'AI';
    $game['status'] = 'ended';
} else {
    $game['turn'] = $game['player1'];
}

$game['board'] = $board;
$game['last_move'] = time();

file_put_contents($gamesFile, json_encode($games, JSON_PRETTY_PRINT));

echo json_encode(['success' => true, 'move' => ['x' => $x, 'y' => $y]]);

function getAIMove($board) {
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
        $offensiveScore = evaluatePosition($board, $x, $y, 2);
        $defensiveScore = evaluatePosition($board, $x, $y, 1) * 1.2;
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