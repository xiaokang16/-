<?php
session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$password = $data['password'] ?? '';

// 管理员固定用户名 xiaokang
if ($username !== 'xiaokang') {
    echo json_encode(['success' => false, 'message' => '用户名或密码错误']);
    exit;
}

$usersFile = __DIR__ . '/../data/users.json';
$users = json_decode(file_get_contents($usersFile), true) ?: [];

if (!isset($users['xiaokang']) || !password_verify($password, $users['xiaokang']['password'])) {
    echo json_encode(['success' => false, 'message' => '用户名或密码错误']);
    exit;
}

$_SESSION['admin_logged_in'] = true;
echo json_encode(['success' => true]);