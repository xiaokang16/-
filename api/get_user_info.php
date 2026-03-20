<?php
session_start();
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? null;
if (!$username) {
    echo json_encode(['logged_in' => false]);
    exit;
}

$usersFile = __DIR__ . '/../data/users.json';
$users = json_decode(file_get_contents($usersFile), true) ?: [];

$user = $users[$username] ?? null;
if (!$user) {
    echo json_encode(['logged_in' => false]);
    exit;
}

if (!isset($user['hint_cards'])) {
    $user['hint_cards'] = 0;
}

echo json_encode([
    'logged_in' => true,
    'username' => $username,
    'rank' => $user['rank'],
    'score' => $user['score'],
    'wins' => $user['wins'],
    'losses' => $user['losses'],
    'hint_cards' => $user['hint_cards']
]);