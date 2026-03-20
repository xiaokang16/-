<?php
session_start();
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? null;
if ($username) {
    $playersFile = __DIR__ . '/../data/players.json';
    $players = json_decode(file_get_contents($playersFile), true) ?: [];
    if (isset($players[$username])) {
        $players[$username]['status'] = 'offline';
        $players[$username]['session_id'] = null;
        $players[$username]['pending_challenge'] = null;
        $players[$username]['current_game'] = null;
        file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT));
    }
    session_destroy();
}
echo json_encode(['success' => true]);