<?php
session_start();
header('Content-Type: application/json');

$username = $_SESSION['username'] ?? null;
if (!$username) {
    echo json_encode(['success' => false, 'message' => '未登录']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$roomId = $data['room_id'] ?? '';

$roomsFile = __DIR__ . '/../data/brawl_rooms.json';
$gamesFile = __DIR__ . '/../data/brawl_games.json';

$fp = fopen($roomsFile, 'r+');
flock($fp, LOCK_EX);

$rooms = json_decode(file_get_contents($roomsFile), true) ?: [];
$games = json_decode(file_get_contents($gamesFile), true) ?: [];

if (!isset($rooms[$roomId])) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '房间不存在']);
    exit;
}

$room = $rooms[$roomId];

// 检查是否创建者
if ($room['creator'] !== $username) {
    flock($fp, LOCK_UN);
    fclose($fp);
    echo json_encode(['success' => false, 'message' => '只有创建者可以开始游戏']);
    exit;
}

// 补全AI
$players = $room['players'];
$blackCount = 0;
$whiteCount = 0;
foreach ($players as $p) {
    if ($p['color'] === 'black') $blackCount++;
    else $whiteCount++;
}

// 添加AI直到每方3人
for ($i = $blackCount; $i < 3; $i++) {
    $players[] = [
        'username' => 'AI_黑' . ($i+1),
        'color' => 'black',
        'isAI' => true,
        'ready' => true,
        'joined_at' => time()
    ];
}
for ($i = $whiteCount; $i < 3; $i++) {
    $players[] = [
        'username' => 'AI_白' . ($i+1),
        'color' => 'white',
        'isAI' => true,
        'ready' => true,
        'joined_at' => time()
    ];
}

// 按颜色排序：黑方在前，白方在后，同颜色按加入顺序
usort($players, function($a, $b) {
    if ($a['color'] === $b['color']) return 0;
    return ($a['color'] === 'black') ? -1 : 1;
});

// 创建游戏
$gameId = uniqid('brawl_game_');
$game = [
    'id' => $gameId,
    'players' => $players,
    'board' => array_fill(0, 15, array_fill(0, 15, 0)),
    'turn' => 0, // 指向第一个玩家（黑方）
    'winner' => null,
    'status' => 'playing',
    'last_move_x' => -1,
    'last_move_y' => -1,
    'created_at' => time()
];
$games[$gameId] = $game;

// 删除房间
unset($rooms[$roomId]);

file_put_contents($roomsFile, json_encode($rooms, JSON_PRETTY_PRINT));
file_put_contents($gamesFile, json_encode($games, JSON_PRETTY_PRINT));

flock($fp, LOCK_UN);
fclose($fp);

echo json_encode(['success' => true, 'game_id' => $gameId]);