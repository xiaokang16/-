<?php
session_start();
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? null;
if (!$username) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$usersFile = __DIR__ . '/../data/users.json';

$fp = fopen($usersFile, 'r+');
flock($fp, LOCK_EX);

$users = json_decode(file_get_contents($usersFile), true) ?: [];

if (!isset($users[$username])) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    exit;
}

$user = &$users[$username];

$price = 150;
$maxCards = 10;

$currentCards = $user['hint_cards'] ?? 0;
$currentScore = $user['score'] ?? 1000;

if ($currentCards >= $maxCards) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '已达持有上限（10张）']);
    exit;
}

if ($currentScore < $price) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '积分不足']);
    exit;
}

$user['score'] = $currentScore - $price;
$user['hint_cards'] = $currentCards + 1;

file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));

flock($fp, LOCK_UN);
fclose($fp);

echo json_encode([
    'success' => true,
    'score' => $user['score'],
    'hint_cards' => $user['hint_cards']
]);