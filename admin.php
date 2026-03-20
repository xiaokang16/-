<?php
session_start();

// 如果请求的是登录页面，直接显示登录表单，不重定向
if (isset($_GET['login'])) {
    // 显示登录表单，跳过 session 检查
} else {
    // 检查是否已登录
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header('Location: ?login');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>棋霸后台管理系统</title>
    <link rel="stylesheet" href="style.css">
    <style>
<!-- PATH_FIXED: 已添加 /wzq 前缀 -->
        body {
            background: #f2f2f7;
            padding: 20px;
        }
        .admin-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 32px;
            padding: 24px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.1);
        }
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            padding-bottom: 16px;
            border-bottom: 1px solid #e5e5ea;
        }
        .admin-header h1 {
            font-size: 24px;
            color: #1c1c1e;
        }
        .admin-header button {
            background: #ff3b30;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 30px;
            cursor: pointer;
        }
        .admin-tabs {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
            border-bottom: 1px solid #e5e5ea;
        }
        .tab-btn {
            background: none;
            border: none;
            padding: 10px 20px;
            font-size: 16px;
            cursor: pointer;
            color: #8e8e93;
        }
        .tab-btn.active {
            color: #007aff;
            border-bottom: 2px solid #007aff;
        }
        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
        }
        .player-table {
            width: 100%;
            border-collapse: collapse;
        }
        .player-table th, .player-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e5e5ea;
        }
        .player-table th {
            background: #f2f2f7;
            font-weight: 600;
        }
        .edit-btn, .ban-btn {
            padding: 4px 12px;
            border-radius: 20px;
            border: none;
            cursor: pointer;
            margin-right: 8px;
        }
        .edit-btn {
            background: #007aff;
            color: white;
        }
        .ban-btn {
            background: #ff3b30;
            color: white;
        }
        .modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
        }
        .modal-content {
            background: white;
            border-radius: 24px;
            padding: 24px;
            width: 90%;
            max-width: 400px;
        }
        .modal-content input, .modal-content textarea {
            width: 100%;
            padding: 12px;
            margin: 8px 0;
            border: 1px solid #e5e5ea;
            border-radius: 14px;
        }
        .modal-buttons {
            display: flex;
            gap: 12px;
            margin-top: 16px;
        }
        .bg-preview {
            max-width: 200px;
            max-height: 100px;
            margin: 10px 0;
            border-radius: 12px;
        }
    </style>
</head>
<body>
    <?php if (isset($_GET['login'])): ?>
    <!-- 登录表单 -->
    <div class="modal" id="admin-login-modal" style="display:flex;">
        <div class="modal-content">
            <h2>管理员登录</h2>
            <input type="text" id="admin-username" placeholder="用户名" autocomplete="off">
            <input type="password" id="admin-password" placeholder="密码">
            <div id="admin-login-error" class="error-message"></div>
            <div class="modal-buttons">
                <button id="admin-login-btn" class="primary-btn">登录</button>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="admin-container">
        <div class="admin-header">
            <h1>棋霸后台管理系统</h1>
            <button id="admin-logout">退出登录</button>
        </div>
        <div class="admin-tabs">
            <button class="tab-btn active" data-tab="players">玩家管理</button>
            <button class="tab-btn" data-tab="background">背景设置</button>
        </div>
        <div id="players-tab" class="tab-pane active">
            <div style="margin-bottom: 16px;">
                <input type="text" id="search-player" placeholder="搜索玩家..." style="padding: 8px; border-radius: 30px; border: 1px solid #e5e5ea; width: 300px;">
            </div>
            <table class="player-table" id="player-table">
                <thead>
                    <tr><th>用户名</th><th>积分</th><th>段位</th><th>胜/负</th><th>封禁状态</th><th>操作</th></tr>
                </thead>
                <tbody id="player-list-body"></tbody>
            </table>
        </div>
        <div id="background-tab" class="tab-pane">
            <h3>登录界面背景</h3>
            <div id="login-bg-preview"></div>
            <input type="file" id="login-bg-upload" accept="image/*">
            <button id="upload-login-bg" class="primary-btn" style="margin-top: 8px;">上传并应用</button>
            <h3 style="margin-top: 24px;">游戏界面背景</h3>
            <div id="game-bg-preview"></div>
            <input type="file" id="game-bg-upload" accept="image/*">
            <button id="upload-game-bg" class="primary-btn" style="margin-top: 8px;">上传并应用</button>
        </div>
    </div>
    <?php endif; ?>

    <script>
        <?php if (isset($_GET['login'])): ?>
        // 管理员登录
        document.getElementById('admin-login-btn')?.addEventListener('click', () => {
            const username = document.getElementById('admin-username').value;
            const password = document.getElementById('admin-password').value;
            fetch('api/admin_login.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    // 登录成功，跳转到管理主页（去掉 ?login 参数）
                    window.location.href = 'admin.php';
                } else {
                    document.getElementById('admin-login-error').innerText = data.message;
                }
            });
        });
        <?php else: ?>
        // 管理界面逻辑
        let playersData = [];
        function loadPlayers() {
            fetch('api/admin_get_players.php')
                .then(res => res.json())
                .then(data => {
                    playersData = data;
                    renderPlayers(data);
                });
        }
        function renderPlayers(players) {
            const tbody = document.getElementById('player-list-body');
            tbody.innerHTML = '';
            players.forEach(p => {
                const tr = document.createElement('tr');
                const bannedText = p.banned ? `封禁至 ${new Date(p.banned.until * 1000).toLocaleString()}<br>理由: ${p.banned.reason}` : '正常';
                tr.innerHTML = `
                    <td>${p.username}</td>
                    <td><span class="score-value">${p.score}</span> <button class="edit-score" data-user="${p.username}">编辑</button></td>
                    <td>${p.rank}</td>
                    <td>${p.wins}/${p.losses}</td>
                    <td>${bannedText}</td>
                    <td>
                        <button class="edit-username" data-user="${p.username}">改名</button>
                        <button class="ban-player" data-user="${p.username}">${p.banned ? '解封' : '封禁'}</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
            // 绑定编辑积分事件
            document.querySelectorAll('.edit-score').forEach(btn => {
                btn.addEventListener('click', () => editScore(btn.dataset.user));
            });
            document.querySelectorAll('.edit-username').forEach(btn => {
                btn.addEventListener('click', () => editUsername(btn.dataset.user));
            });
            document.querySelectorAll('.ban-player').forEach(btn => {
                btn.addEventListener('click', () => banPlayer(btn.dataset.user));
            });
        }
        function editScore(username) {
            const newScore = prompt('请输入新积分', playersData.find(p => p.username === username).score);
            if (newScore !== null && !isNaN(newScore)) {
                fetch('api/admin_update_player.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, action: 'score', value: parseInt(newScore) })
                }).then(() => loadPlayers());
            }
        }
        function editUsername(username) {
            const newName = prompt('请输入新用户名', username);
            if (newName && newName !== username) {
                fetch('api/admin_update_player.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, action: 'username', value: newName })
                }).then(() => loadPlayers());
            }
        }
        function banPlayer(username) {
            const player = playersData.find(p => p.username === username);
            if (player.banned) {
                // 解封
                if (confirm('确定解封该玩家吗？')) {
                    fetch('api/admin_update_player.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ username, action: 'unban' })
                    }).then(() => loadPlayers());
                }
            } else {
                // 封禁
                const reason = prompt('请输入封禁理由');
                if (!reason) return;
                let until = prompt('请输入解封时间（格式：YYYY-MM-DD HH:MM:SS）');
                let untilTimestamp = new Date(until).getTime() / 1000;
                if (isNaN(untilTimestamp)) {
                    alert('时间格式错误');
                    return;
                }
                fetch('api/admin_update_player.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, action: 'ban', reason, until: untilTimestamp })
                }).then(() => loadPlayers());
            }
        }
        // 搜索
        document.getElementById('search-player')?.addEventListener('input', (e) => {
            const keyword = e.target.value.toLowerCase();
            const filtered = playersData.filter(p => p.username.toLowerCase().includes(keyword));
            renderPlayers(filtered);
        });
        // 标签切换
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');
                document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
                document.getElementById(btn.dataset.tab + '-tab').classList.add('active');
                if (btn.dataset.tab === 'players') loadPlayers();
                else loadBackgroundSettings();
            });
        });
        function loadBackgroundSettings() {
            fetch('api/get_settings.php')
                .then(res => res.json())
                .then(data => {
                    if (data.login_bg) {
                        document.getElementById('login-bg-preview').innerHTML = `<img src="/wzq${data.login_bg}" class="bg-preview">`;
                    }
                    if (data.game_bg) {
                        document.getElementById('game-bg-preview').innerHTML = `<img src="/wzq${data.game_bg}" class="bg-preview">`;
                    }
                });
        }
        document.getElementById('upload-login-bg')?.addEventListener('click', () => {
            const fileInput = document.getElementById('login-bg-upload');
            const file = fileInput.files[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('bg', file);
            formData.append('type', 'login');
            fetch('api/admin_upload_background.php', {
                method: 'POST',
                body: formData
            }).then(res => res.json()).then(data => {
                if (data.success) alert('上传成功');
                else alert('上传失败');
                loadBackgroundSettings();
            });
        });
        document.getElementById('upload-game-bg')?.addEventListener('click', () => {
            const fileInput = document.getElementById('game-bg-upload');
            const file = fileInput.files[0];
            if (!file) return;
            const formData = new FormData();
            formData.append('bg', file);
            formData.append('type', 'game');
            fetch('api/admin_upload_background.php', {
                method: 'POST',
                body: formData
            }).then(res => res.json()).then(data => {
                if (data.success) alert('上传成功');
                else alert('上传失败');
                loadBackgroundSettings();
            });
        });
        document.getElementById('admin-logout').addEventListener('click', () => {
            fetch('api/logout.php').then(() => location.href = 'admin.php?login');
        });
        loadPlayers();
        loadBackgroundSettings();
        <?php endif; ?>
    </script>
</body>
</html>