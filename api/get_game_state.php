<?php
session_start();
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? null;
if (!$username) {
    echo json_encode(['in_game' => false]);
    exit;
}

$playersFile = __DIR__ . '/../data/players.json';
$gamesFile = __DIR__ . '/../data/games.json';

$players = json_decode(file_get_contents($playersFile), true) ?: [];
$games = json_decode(file_get_contents($gamesFile), true) ?: [];

if (isset($players[$username])) {
    $players[$username]['last_heartbeat'] = time();
    file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT));
}

$roomId = $players[$username]['room_id'] ?? null;
if (!$roomId || !isset($games[$roomId])) {
    echo json_encode(['in_game' => false]);
    exit;
}

$game = $games[$roomId];

if ($game['status'] === 'waiting') {
    echo json_encode([
        'in_game' => false,
        'waiting' => true,
        'room_id' => $roomId,
        'opponent' => $game['player1'] === $username ? '等待对方接受' : $game['player1']
    ]);
    exit;
}

$myColor = ($game['player1'] === $username) ? 'black' : 'white';
$myTurn = ($game['turn'] === $username) && !$game['winner'];

$opponent = ($game['player1'] === $username) ? $game['player2'] : $game['player1'];
$opponentRank = '未知';
if ($opponent === 'AI') {
    $opponentRank = '智能棋手';
} elseif (isset($players[$opponent])) {
    $opponentRank = $players[$opponent]['rank'] ?? '未知';
}

$hintUsed = $game['hint_used'] ?? 0;

echo json_encode([
    'in_game' => true,
    'game_id' => $roomId,
    'board' => $game['board'],
    'my_color' => $myColor,
    'my_turn' => $myTurn,
    'opponent' => $opponent,
    'opponent_rank' => $opponentRank,
    'winner' => $game['winner'],
    'hint_used' => $hintUsed
]);