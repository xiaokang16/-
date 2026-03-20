<?php
session_start();
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? null;
if (!$username) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$newUsername = $data['new_username'] ?? '';
$newPassword = $data['new_password'] ?? '';

if (!$newUsername && !$newPassword) {
    echo json_encode(['success' => false, 'message' => '没有要修改的内容']);
    exit;
}

$usersFile = __DIR__ . '/../data/users.json';
$playersFile = __DIR__ . '/../data/players.json';

$fp = fopen($usersFile, 'r+');
flock($fp, LOCK_EX);

$users = json_decode(file_get_contents($usersFile), true) ?: [];
$players = json_decode(file_get_contents($playersFile), true) ?: [];

if (!isset($users[$username])) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    exit;
}

$user = &$users[$username];

if ($newUsername && $newUsername !== $username) {
    if (isset($users[$newUsername])) {
        flock($fp, LOCK_UN);
        fclose($fp);
        echo json_encode(['success' => false, 'message' => '用户名已存在']);
        exit;
    }
    
    $users[$newUsername] = $user;
    unset($users[$username]);
    
    if (isset($players[$username])) {
        $players[$newUsername] = $players[$username];
        $players[$newUsername]['username'] = $newUsername;
        unset($players[$username]);
    }
    
    $_SESSION['username'] = $newUsername;
    $username = $newUsername;
}

if ($newPassword) {
    if (strlen($newPassword) < 6) {
        flock($fp, LOCK_UN);
        fclose($fp);
        echo json_encode(['success' => false, 'message' => '密码至少6位']);
        exit;
    }
    $users[$username]['password'] = password_hash($newPassword, PASSWORD_DEFAULT);
}

file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT));

flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['success' => true, 'new_username' => $username]);