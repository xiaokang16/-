<?php
session_start();
header('Content-Type: application/json');

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';

$usersFile = __DIR__ . '/../data/users.json';
$playersFile = __DIR__ . '/../data/players.json';

$users = json_decode(file_get_contents($usersFile), true) ?: [];
$players = json_decode(file_get_contents($playersFile), true) ?: [];

if (!isset($users[$username]) || !password_verify($password, $users[$username]['password'])) {
    echo json_encode(['success' => false, 'message' => '用户名或密码错误']);
    exit;
}

// 新增：检查封禁
if (isset($users[$username]['banned'])) {
    $ban = $users[$username]['banned'];
    $now = time();
    if ($ban['until'] > $now) {
        echo json_encode([
            'success' => false,
            'banned' => true,
            'reason' => $ban['reason'],
            'until' => $ban['until']
        ]);
        exit;
    } else {
        // 封禁过期，清除封禁状态
        unset($users[$username]['banned']);
        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
    }
}

// 原有登录逻辑
$players[$username] = [
    'username' => $username,
    'rank' => $users[$username]['rank'],
    'status' => 'online',
    'room_id' => null,
    'session_id' => session_id(),
    'last_heartbeat' => time(),
    'pending_challenge' => null
];

file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT));

$_SESSION['username'] = $username;
echo json_encode(['success' => true, 'username' => $username]);