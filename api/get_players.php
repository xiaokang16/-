<?php
session_start();
header('Content-Type: application/json');

$playersFile = __DIR__ . '/../data/players.json';
$players = json_decode(file_get_contents($playersFile), true) ?: [];

$onlinePlayers = [];
$now = time();
$onlineThreshold = 15;

foreach ($players as $username => $info) {
    $lastHeartbeat = $info['last_heartbeat'] ?? 0;
    if ($lastHeartbeat > $now - $onlineThreshold) {
        $status = $info['status'] ?? 'online';
        $onlinePlayers[] = [
            'username' => $username,
            'rank' => $info['rank'],
            'status' => $status
        ];
    }
}

echo json_encode($onlinePlayers);