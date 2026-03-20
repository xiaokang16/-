<?php
session_start();
$settingsFile = __DIR__ . '/data/settings.json';
$settings = [];
if (file_exists($settingsFile)) {
    $settings = json_decode(file_get_contents($settingsFile), true) ?: [];
}
$loginBg = $settings['login_bg'] ?? '';
$gameBg = $settings['game_bg'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>棋霸 · 五子棋</title>
    <link rel="stylesheet" href="style.css">
<style>
    <?php if ($loginBg): ?>
    body {
        background-image: url('<?= htmlspecialchars($loginBg) ?>');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
    }
    <?php endif; ?>

    <?php if ($gameBg): ?>
    #game-container {
        position: relative;
        background: none;
    }
    #game-container::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-image: url('<?= htmlspecialchars($gameBg) ?>');
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
        filter: blur(10px);
        z-index: -1;
    }
    #game-container > * {
        position: relative;
        z-index: 1;
    }
    <?php else: ?>
    #game-container {
        background: #f2f2f7;
    }
    <?php endif; ?>
</style>
</head>
<body>
    <!-- 认证模态框 -->
    <div id="auth-modal" class="modal">
        <div class="modal-content">
            <h1 class="logo">⚫️ 棋霸 ⚪️</h1>
            <div class="tab-bar">
                <button class="tab-btn active" id="tab-login">登录</button>
                <button class="tab-btn" id="tab-register">注册</button>
            </div>
            
            <div id="login-form" class="auth-form">
                <input type="text" id="login-username" placeholder="用户名" autocomplete="off">
                <input type="password" id="login-password" placeholder="密码">
                <button id="login-submit" class="primary-btn">登录</button>
            </div>
            
            <div id="register-form" class="auth-form" style="display:none;">
                <input type="text" id="reg-username" placeholder="用户名 (3-20位字母数字)">
                <input type="password" id="reg-password" placeholder="密码 (至少6位)">
                <button id="register-submit" class="primary-btn">注册</button>
            </div>
            
            <div id="auth-error" class="error-message"></div>
        </div>
    </div>

    <!-- 封禁提示模态框 -->
    <div id="ban-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <h2>⛔ 账号已被封禁</h2>
            <p id="ban-reason"></p>
            <p id="ban-until"></p>
            <button id="ban-close" class="primary-btn">确定</button>
        </div>
    </div>

    <!-- 主游戏容器 -->
    <div id="game-container" style="display:none;">
        <!-- 顶部状态栏 -->
        <header class="status-bar">
            <div class="user-profile">
                <span class="username" id="current-username"></span>
                <span class="rank-badge" id="current-rank"></span>
                <span class="score" id="current-score"></span>
                <span class="hint-card-badge" id="hint-cards">🎴 0</span>
            </div>
            <div class="header-actions">
                <button id="shop-btn" class="icon-btn">🛒 商店</button>
                <button id="history-btn" class="icon-btn">📋 历史</button>
                <button id="settings-btn" class="icon-btn">⚙️ 设置</button>
                <button id="logout-btn" class="icon-btn">退出</button>
            </div>
        </header>

        <!-- 大厅视图 -->
        <div id="hall-view" class="view">
            <div class="hall-header">
                <h2>在线玩家 · <span id="online-count">0</span></h2>
                <div class="hall-actions">
                    <button id="start-ai-game" class="ai-btn">🤖 人机对战</button>
                    <button id="start-brawl-btn" class="brawl-btn">⚔️ 大乱斗</button>
                    <button id="refresh-players" class="icon-btn">刷新</button>
                </div>
            </div>
            <ul id="player-list" class="player-list"></ul>
        </div>

        <!-- 普通对战视图 -->
        <div id="game-view" class="view" style="display:none;">
            <div class="game-layout">
                <div class="board-section">
                    <canvas id="board" width="480" height="480"></canvas>
                </div>
                <div class="info-section">
                    <div class="opponent-card">
                        <div class="card-label">对手</div>
                        <div class="opponent-name" id="opponent-name">--</div>
                        <div class="opponent-rank" id="opponent-rank">--</div>
                    </div>
                    <div class="turn-indicator" id="turn-indicator">等待对方落子</div>
                    <button id="use-hint-btn" class="hint-btn" style="display:none;">🎴 使用指点卡 (<span id="hint-used">0</span>/2)</button>
                    <div class="chat-container">
                        <div class="chat-header">💬 聊天</div>
                        <div class="chat-messages" id="chat-messages"></div>
                        <div class="chat-input-area">
                            <input type="text" id="chat-input" placeholder="输入消息...">
                            <button id="chat-send">发送</button>
                        </div>
                    </div>
                    <button id="surrender-btn" class="secondary-btn danger">🏳️ 认输</button>
                    <div id="result-animation" class="result-animation"></div>
                    <button id="back-to-hall" class="secondary-btn">返回大厅</button>
                </div>
            </div>
        </div>

        <!-- 大乱斗房间创建/等待界面 -->
        <div id="brawl-room-view" class="view" style="display:none;">
            <div class="brawl-room-header">
                <h2>大乱斗房间 · <span id="brawl-room-id"></span></h2>
                <button id="brawl-start-ai-btn" class="ai-btn">🤖 AI补足并开始</button>
            </div>
            <div class="brawl-players-container">
                <div class="team-black">
                    <h3>⚫️ 黑方 <span id="black-count">0</span>/3</h3>
                    <ul id="black-room-players" class="player-list-small"></ul>
                </div>
                <div class="team-white">
                    <h3>⚪️ 白方 <span id="white-count">0</span>/3</h3>
                    <ul id="white-room-players" class="player-list-small"></ul>
                </div>
            </div>
            <div class="brawl-chat-container">
                <div class="chat-header">💬 聊天</div>
                <div class="chat-messages" id="brawl-room-chat"></div>
                <div class="chat-input-area">
                    <input type="text" id="brawl-room-chat-input" placeholder="输入消息...">
                    <button id="brawl-room-chat-send">发送</button>
                </div>
            </div>
            <button id="brawl-room-exit" class="secondary-btn">退出房间</button>
        </div>

        <!-- 大乱斗游戏视图（进行中） -->
        <div id="brawl-game-view" class="view" style="display:none;">
            <div class="game-layout">
                <div class="board-section">
                    <canvas id="brawl-board" width="480" height="480"></canvas>
                </div>
                <div class="info-section">
                    <div class="players-panel">
                        <div class="team-black">
                            <h3>⚫️ 黑方</h3>
                            <ul id="brawl-black-players" class="player-list-small"></ul>
                        </div>
                        <div class="team-white">
                            <h3>⚪️ 白方</h3>
                            <ul id="brawl-white-players" class="player-list-small"></ul>
                        </div>
                    </div>
                    <div class="turn-indicator" id="brawl-turn-indicator"></div>
                    <div class="brawl-chat-container">
                        <div class="chat-header">💬 聊天</div>
                        <div class="chat-messages" id="brawl-game-chat"></div>
                        <div class="chat-input-area">
                            <input type="text" id="brawl-game-chat-input" placeholder="输入消息...">
                            <button id="brawl-game-chat-send">发送</button>
                        </div>
                    </div>
                    <button id="brawl-surrender-btn" class="secondary-btn danger">🏳️ 投降</button>
                    <button id="brawl-game-exit" class="secondary-btn">退出</button>
                </div>
            </div>
        </div>
    </div>

    <!-- 商店模态框 -->
    <div id="shop-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <h2>🛒 指点商店</h2>
            <div class="shop-info">
                <p>当前积分：<span id="shop-score">0</span></p>
                <p>拥有指点卡：<span id="shop-cards">0</span>/10</p>
                <p>价格：150 积分 / 张</p>
            </div>
            <button id="buy-hint-btn" class="primary-btn">购买指点卡</button>
            <div id="shop-error" class="error-message"></div>
            <button id="close-shop" class="secondary-btn">关闭</button>
        </div>
    </div>

    <!-- 历史记录模态框 -->
    <div id="history-modal" class="modal" style="display:none;">
        <div class="modal-content large">
            <h2>📋 对局历史</h2>
            <div id="history-list" class="history-list"></div>
            <button id="close-history" class="primary-btn">关闭</button>
        </div>
    </div>

    <!-- 设置模态框 -->
    <div id="settings-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <h2>⚙️ 账号设置</h2>
            <div class="settings-form">
                <div class="input-group">
                    <label>新用户名</label>
                    <input type="text" id="new-username" placeholder="不修改则留空">
                </div>
                <div class="input-group">
                    <label>新密码</label>
                    <input type="password" id="new-password" placeholder="至少6位">
                </div>
                <button id="update-profile" class="primary-btn">保存修改</button>
            </div>
            <div id="settings-error" class="error-message"></div>
            <button id="close-settings" class="secondary-btn">关闭</button>
        </div>
    </div>

    <!-- 阵营选择模态框（用于创建房间） -->
    <div id="choose-side-modal" class="modal" style="display:none;">
        <div class="modal-content">
            <h2>选择阵营</h2>
            <button id="choose-black-btn" class="primary-btn" style="margin-bottom:10px;">⚫️ 黑方</button>
            <button id="choose-white-btn" class="primary-btn" style="margin-bottom:10px;">⚪️ 白方</button>
            <button id="close-choose-side" class="secondary-btn">取消</button>
        </div>
    </div>

    <!-- 横屏提示 -->
    <div id="orientation-overlay" class="orientation-overlay" style="display:none;">
        <div class="orientation-card">
            <span class="icon">📱 ↻</span>
            <p>请旋转至横屏获得最佳体验</p>
        </div>
    </div>

    <script src="script.js"></script>
    <script src="brawl.js"></script>
</body>
</html>