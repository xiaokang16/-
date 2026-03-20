<?php
session_start();
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? null;
if (!$username) {
    echo json_encode(['success' => false]);
    exit;
}

$playersFile = __DIR__ . '/../data/players.json';
$gamesFile = __DIR__ . '/../data/games.json';

$players = json_decode(file_get_contents($playersFile), true) ?: [];
$games = json_decode(file_get_contents($gamesFile), true) ?: [];

$roomId = $players[$username]['room_id'] ?? null;
if ($roomId && isset($games[$roomId])) {
    $game = $games[$roomId];
    
    if ($game['status'] === 'waiting') {
        unset($games[$roomId]);
        $other = $game['player1'] === $username ? null : $game['player1'];
        if ($other && isset($players[$other])) {
            $players[$other]['pending_challenge'] = null;
            $players[$other]['status'] = 'online';
            $players[$other]['room_id'] = null;
        }
    } elseif ($game['status'] === 'playing') {
        unset($games[$roomId]);
        $chatFile = __DIR__ . '/../data/chat_' . $roomId . '.json';
        if (file_exists($chatFile)) unlink($chatFile);
    }
}

$players[$username]['status'] = 'online';
$players[$username]['room_id'] = null;
$players[$username]['pending_challenge'] = null;
$players[$username]['last_heartbeat'] = time();

file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT));
file_put_contents($gamesFile, json_encode($games, JSON_PRETTY_PRINT));

echo json_encode(['success' => true]);