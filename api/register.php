<?php
session_start();
header('Content-Type: application/json');

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (strlen($username) < 3 || strlen($username) > 20 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    echo json_encode(['success' => false, 'message' => '用户名必须为3-20位字母数字或下划线']);
    exit;
}
if (strlen($password) < 6) {
    echo json_encode(['success' => false, 'message' => '密码至少6位']);
    exit;
}

$usersFile = __DIR__ . '/../data/users.json';
$users = json_decode(file_get_contents($usersFile), true) ?: [];

if (isset($users[$username])) {
    echo json_encode(['success' => false, 'message' => '用户名已存在']);
    exit;
}

// 创建新用户
$users[$username] = [
    'username' => $username,
    'password' => password_hash($password, PASSWORD_DEFAULT),
    'rank' => '初段',
    'score' => 1000,
    'wins' => 0,
    'losses' => 0,
    'created_at' => time()
];

file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
echo json_encode(['success' => true, 'message' => '注册成功']);