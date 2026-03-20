<?php
session_start();
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? null;
if (!$username) {
    echo json_encode(['success' => false]);
    exit;
}

$playersFile = __DIR__ . '/../data/players.json';
$players = json_decode(file_get_contents($playersFile), true) ?: [];

if (isset($players[$username])) {
    $players[$username]['last_heartbeat'] = time();
    if ($players[$username]['status'] === 'offline') {
        $players[$username]['status'] = 'online';
    }
    file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT));
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false]);
}