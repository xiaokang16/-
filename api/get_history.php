<?php
session_start();
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? null;
if (!$username) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$usersFile = __DIR__ . '/../data/users.json';
$users = json_decode(file_get_contents($usersFile), true) ?: [];

if (!isset($users[$username])) {
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    exit;
}

$history = $users[$username]['history'] ?? [];
echo json_encode(['success' => true, 'history' => $history]);