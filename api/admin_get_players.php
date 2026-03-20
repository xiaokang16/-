<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['error' => '未授权']);
    exit;
}

$usersFile = __DIR__ . '/../data/users.json';
$users = json_decode(file_get_contents($usersFile), true) ?: [];

$players = [];
foreach ($users as $username => $info) {
    $players[] = [
        'username' => $username,
        'score' => $info['score'],
        'rank' => $info['rank'],
        'wins' => $info['wins'],
        'losses' => $info['losses'],
        'banned' => $info['banned'] ?? null
    ];
}

echo json_encode($players);