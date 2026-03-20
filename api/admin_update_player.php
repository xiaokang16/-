<?php
session_start();
header('Content-Type: application/json');

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['error' => '未授权']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$username = $data['username'] ?? '';
$action = $data['action'] ?? '';

if (!$username) {
    echo json_encode(['success' => false, 'message' => '参数错误']);
    exit;
}

$usersFile = __DIR__ . '/../data/users.json';
$users = json_decode(file_get_contents($usersFile), true) ?: [];

if (!isset($users[$username])) {
    echo json_encode(['success' => false, 'message' => '用户不存在']);
    exit;
}

switch ($action) {
    case 'score':
        $newScore = (int)$data['value'];
        $users[$username]['score'] = $newScore;
        // 更新段位
        $score = $newScore;
        if ($score >= 2600) $rank = '九段';
        elseif ($score >= 2400) $rank = '八段';
        elseif ($score >= 2200) $rank = '七段';
        elseif ($score >= 2000) $rank = '六段';
        elseif ($score >= 1800) $rank = '五段';
        elseif ($score >= 1600) $rank = '四段';
        elseif ($score >= 1400) $rank = '三段';
        elseif ($score >= 1200) $rank = '二段';
        else $rank = '初段';
        $users[$username]['rank'] = $rank;
        break;
    case 'username':
        $newName = $data['value'];
        if (isset($users[$newName])) {
            echo json_encode(['success' => false, 'message' => '用户名已存在']);
            exit;
        }
        $users[$newName] = $users[$username];
        unset($users[$username]);
        // 更新玩家在线记录
        $playersFile = __DIR__ . '/../data/players.json';
        if (file_exists($playersFile)) {
            $players = json_decode(file_get_contents($playersFile), true) ?: [];
            if (isset($players[$username])) {
                $players[$newName] = $players[$username];
                unset($players[$username]);
                file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT));
            }
        }
        echo json_encode(['success' => true]);
        file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
        exit;
    case 'ban':
        $reason = $data['reason'] ?? '';
        $until = (int)$data['until'];
        $users[$username]['banned'] = [
            'reason' => $reason,
            'until' => $until
        ];
        // 踢出在线会话（可选，通过 players.json 状态）
        $playersFile = __DIR__ . '/../data/players.json';
        if (file_exists($playersFile)) {
            $players = json_decode(file_get_contents($playersFile), true) ?: [];
            if (isset($players[$username])) {
                $players[$username]['status'] = 'offline';
                unset($players[$username]['room_id']);
                file_put_contents($playersFile, json_encode($players, JSON_PRETTY_PRINT));
            }
        }
        break;
    case 'unban':
        unset($users[$username]['banned']);
        break;
    default:
        echo json_encode(['success' => false, 'message' => '未知操作']);
        exit;
}

file_put_contents($usersFile, json_encode($users, JSON_PRETTY_PRINT));
echo json_encode(['success' => true]);