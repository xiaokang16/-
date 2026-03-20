// 全局变量
let currentUser = null;
let currentRoomId = null;
let boardData = Array(15).fill().map(() => Array(15).fill(0));
let myColor = null;
let myTurn = false;
let gameActive = false;
let isWaiting = false;
let isAIGame = false;
let hintCards = 0;
let hintUsed = 0;
let gameMode = 'normal'; // normal, ai, brawl-room, brawl-game

// 选中状态
let selectedX = -1, selectedY = -1;
let lastMoveX = -1, lastMoveY = -1;
let gameOver = false;

// 轮询控制
let heartbeatInterval;
let gameStateInterval;
let playersInterval;
let challengesInterval;
let aiCheckInterval;
let chatInterval;

const boardSize = 15;
const cellSize = 480 / boardSize;
const boardCanvas = document.getElementById('board');
const ctx = boardCanvas.getContext('2d');

// 横屏检测
function checkOrientation() {
    const overlay = document.getElementById('orientation-overlay');
    if (window.innerHeight > window.innerWidth) {
        overlay.style.display = 'flex';
    } else {
        overlay.style.display = 'none';
    }
}
window.addEventListener('resize', checkOrientation);
window.addEventListener('orientationchange', checkOrientation);

// 初始化
window.onload = function() {
    checkOrientation();
    fetchUserInfo();
};

// 获取用户信息
function fetchUserInfo() {
    fetch('api/get_user_info.php')
        .then(res => res.json())
        .then(data => {
            if (data.logged_in) {
                currentUser = data.username;
                hintCards = data.hint_cards || 0;
                document.getElementById('current-username').innerText = data.username;
                document.getElementById('current-rank').innerText = data.rank;
                document.getElementById('current-score').innerText = `${data.score}分`;
                document.getElementById('hint-cards').innerHTML = `🎴 ${hintCards}`;
                document.getElementById('auth-modal').style.display = 'none';
                document.getElementById('game-container').style.display = 'flex';
                startHeartbeat();
                startPolling();
            } else {
                document.getElementById('auth-modal').style.display = 'flex';
            }
        });
}

// 登录/注册切换
document.getElementById('tab-login').addEventListener('click', () => {
    document.getElementById('tab-login').classList.add('active');
    document.getElementById('tab-register').classList.remove('active');
    document.getElementById('login-form').style.display = 'block';
    document.getElementById('register-form').style.display = 'none';
});
document.getElementById('tab-register').addEventListener('click', () => {
    document.getElementById('tab-register').classList.add('active');
    document.getElementById('tab-login').classList.remove('active');
    document.getElementById('register-form').style.display = 'block';
    document.getElementById('login-form').style.display = 'none';
});

// 登录
document.getElementById('login-submit').addEventListener('click', () => {
    const username = document.getElementById('login-username').value.trim();
    const password = document.getElementById('login-password').value;
    fetch('api/login.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            fetchUserInfo();
        } else if (data.banned) {
            // 显示封禁模态框
            const modal = document.getElementById('ban-modal');
            const reasonEl = document.getElementById('ban-reason');
            const untilEl = document.getElementById('ban-until');
            reasonEl.innerText = `封禁理由：${data.reason}`;
            const untilDate = new Date(data.until * 1000);
            untilEl.innerText = `解封时间：${untilDate.toLocaleString()}`;
            modal.style.display = 'flex';
            document.getElementById('ban-close').addEventListener('click', () => {
                modal.style.display = 'none';
            });
        } else {
            document.getElementById('auth-error').innerText = data.message;
        }
    });
});

// 注册
document.getElementById('register-submit').addEventListener('click', () => {
    const username = document.getElementById('reg-username').value.trim();
    const password = document.getElementById('reg-password').value;
    fetch('api/register.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('注册成功，请登录');
            document.getElementById('tab-login').click();
            document.getElementById('login-username').value = username;
        } else {
            document.getElementById('auth-error').innerText = data.message;
        }
    });
});

// 退出
document.getElementById('logout-btn').addEventListener('click', () => {
    fetch('api/logout.php', { method: 'POST' })
        .then(() => location.reload());
});

// 心跳（每5秒）
function startHeartbeat() {
    heartbeatInterval = setInterval(() => {
        fetch('api/heartbeat.php', { method: 'POST' })
            .catch(err => console.log('心跳失败', err));
    }, 5000);
}

// 轮询
function startPolling() {
    fetchPlayers();
    checkChallenges();
    updateGameState();
    
    playersInterval = setInterval(fetchPlayers, 3000);
    challengesInterval = setInterval(checkChallenges, 3000);
    gameStateInterval = setInterval(updateGameState, 2000);
    aiCheckInterval = setInterval(checkAITurn, 1000);
    chatInterval = setInterval(fetchChat, 2000);
}

// 获取在线玩家列表
function fetchPlayers() {
    fetch('api/get_players.php')
        .then(res => res.json())
        .then(updatePlayerList)
        .catch(err => console.log('获取玩家列表失败', err));
}

// 更新玩家列表
function updatePlayerList(players) {
    const list = document.getElementById('player-list');
    const onlineCount = document.getElementById('online-count');
    list.innerHTML = '';
    
    const others = players.filter(p => p.username !== currentUser);
    onlineCount.innerText = others.length;
    
    others.forEach(p => {
        const li = document.createElement('li');
        li.innerHTML = `
            <div class="player-info">
                <span class="player-nickname">${p.username}</span>
                <span class="player-rank">${p.rank}</span>
                <span class="player-status ${p.status === 'online' ? 'status-online' : (p.status === 'waiting' ? 'status-waiting' : 'status-busy')}">${p.status === 'online' ? '空闲' : (p.status === 'waiting' ? '等待中' : '对战中')}</span>
            </div>
            <button class="challenge-btn" data-target="${p.username}" ${p.status !== 'online' ? 'disabled' : ''}>挑战</button>
        `;
        list.appendChild(li);
    });
    
    document.querySelectorAll('.challenge-btn:not(:disabled)').forEach(btn => {
        btn.addEventListener('click', () => {
            sendChallenge(btn.dataset.target);
        });
    });
}

// 发起挑战
function sendChallenge(target) {
    fetch('api/send_challenge.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ target })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            currentRoomId = data.room_id;
            switchToGameView('normal');
            isWaiting = true;
            document.getElementById('turn-indicator').innerText = '⏳ 等待对方接受挑战...';
            document.getElementById('opponent-name').innerText = target;
        } else {
            alert('❌ 发送失败：' + data.message);
        }
    });
}

// 检查待处理挑战
function checkChallenges() {
    fetch('api/check_challenges.php')
        .then(res => res.json())
        .then(data => {
            if (data.challenge) {
                const c = data.challenge;
                if (confirm(`👋 玩家 ${c.from} 向你发起五子棋挑战，是否接受？`)) {
                    respondChallenge(c.room_id, true);
                } else {
                    respondChallenge(c.room_id, false);
                }
            }
        });
}

function respondChallenge(roomId, accept) {
    fetch('api/respond_challenge.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ room_id: roomId, accept })
    })
    .then(res => res.json())
    .then(data => {
        if (accept && data.success) {
            currentRoomId = data.room_id;
            switchToGameView('normal');
            isWaiting = false;
        } else if (!accept && data.success) {
            // 已拒绝
        } else if (accept && !data.success) {
            alert('❌ 接受失败：' + data.message);
        }
    });
}

// 人机对战
document.getElementById('start-ai-game').addEventListener('click', startAIGame);

function startAIGame() {
    fetch('api/start_ai_game.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            currentRoomId = data.room_id;
            switchToGameView('ai');
            gameActive = true;
            document.getElementById('opponent-name').innerText = 'AI 机器人';
            document.getElementById('opponent-rank').innerText = '智能棋手';
            document.getElementById('turn-indicator').innerText = '⏳ 轮到你了';
        } else {
            alert('❌ 开始失败：' + data.message);
        }
    });
}

// 检查AI是否需要落子（人机对战）
function checkAITurn() {
    if (gameMode !== 'ai' || !gameActive || document.getElementById('game-view').style.display !== 'block') return;
    if (!myTurn && gameActive) {
        setTimeout(() => {
            fetch('api/ai_move.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ room_id: currentRoomId })
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    setTimeout(updateGameState, 300);
                }
            })
            .catch(err => console.log('AI落子失败', err));
        }, 500);
    }
}

// 普通对战聊天
function fetchChat() {
    if (!currentRoomId || document.getElementById('game-view').style.display !== 'block') return;
    fetch(`api/get_chat.php?room_id=${currentRoomId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success && data.chats) {
                displayChatMessages(data.chats);
            }
        });
}

function displayChatMessages(chats) {
    const container = document.getElementById('chat-messages');
    container.innerHTML = '';
    chats.forEach(chat => {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'chat-message' + (chat.username === currentUser ? ' self' : '');
        const time = new Date(chat.time * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        msgDiv.innerHTML = `
            <span class="username">${chat.username}</span>
            <span class="time">${time}</span>
            <span class="text">${chat.message}</span>
        `;
        container.appendChild(msgDiv);
    });
    container.scrollTop = container.scrollHeight;
}

document.getElementById('chat-send').addEventListener('click', sendChatMessage);
document.getElementById('chat-input').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') sendChatMessage();
});

function sendChatMessage() {
    const input = document.getElementById('chat-input');
    const msg = input.value.trim();
    if (!msg || !currentRoomId) return;
    fetch('api/send_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ room_id: currentRoomId, message: msg })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            fetchChat();
        } else {
            alert('发送失败：' + data.message);
        }
    });
}

// 普通对战状态更新
function updateGameState() {
    if (gameMode !== 'normal' && gameMode !== 'ai') return;
    if (document.getElementById('game-view').style.display !== 'block') return;
    
    fetch('api/get_game_state.php')
        .then(res => res.json())
        .then(data => {
            if (!data.in_game) {
                if (data.waiting) {
                    isWaiting = true;
                    document.getElementById('turn-indicator').innerText = '⏳ 等待对方接受挑战...';
                    document.getElementById('opponent-name').innerText = data.opponent;
                    return;
                } else {
                    document.getElementById('game-view').style.display = 'none';
                    document.getElementById('hall-view').style.display = 'block';
                    gameActive = false;
                    gameOver = false;
                    isWaiting = false;
                    isAIGame = false;
                    document.getElementById('chat-messages').innerHTML = '';
                    lastMoveX = -1;
                    lastMoveY = -1;
                    return;
                }
            }

            if (data.opponent === 'AI') {
                isAIGame = true;
            }

            isWaiting = false;
            if (!gameActive) {
                document.getElementById('opponent-name').innerText = data.opponent;
                document.getElementById('opponent-rank').innerText = isAIGame ? '智能棋手' : data.opponent_rank;
                gameActive = true;
                gameOver = false;
            }

            hintUsed = data.hint_used || 0;

            // 检测最后一子
            const newBoard = data.board;
            let moveFound = false;
            for (let i = 0; i < boardSize; i++) {
                for (let j = 0; j < boardSize; j++) {
                    if (boardData[i][j] !== newBoard[i][j]) {
                        if (newBoard[i][j] !== 0) {
                            lastMoveX = i;
                            lastMoveY = j;
                            moveFound = true;
                            break;
                        }
                    }
                }
                if (moveFound) break;
            }

            boardData = newBoard;
            myColor = data.my_color;
            myTurn = data.my_turn;
            drawBoard();
            updateTurnDisplay();

            // 控制指点卡按钮
            const hintBtn = document.getElementById('use-hint-btn');
            if (myTurn && gameActive && !isWaiting && !isAIGame) {
                hintBtn.style.display = 'block';
                document.getElementById('hint-used').innerText = hintUsed;
                if (hintCards === 0 || hintUsed >= 2) {
                    hintBtn.disabled = true;
                } else {
                    hintBtn.disabled = false;
                }
            } else {
                hintBtn.style.display = 'none';
            }

            // 胜负判定及动画
            if (data.winner && !gameOver) {
                gameActive = false;
                gameOver = true;
                document.getElementById('board').classList.add('winner-flash');
                
                const animDiv = document.getElementById('result-animation');
                if (data.winner === currentUser) {
                    animDiv.innerText = '🏆 胜利！';
                    animDiv.className = 'result-animation show victory';
                } else {
                    animDiv.innerText = '😢 失败';
                    animDiv.className = 'result-animation show defeat';
                }
                
                drawBoard();
                
                setTimeout(() => {
                    animDiv.className = 'result-animation';
                    document.getElementById('board').classList.remove('winner-flash');
                    document.getElementById('game-view').style.display = 'none';
                    document.getElementById('hall-view').style.display = 'block';
                    gameActive = false;
                    gameOver = false;
                    isAIGame = false;
                    document.getElementById('chat-messages').innerHTML = '';
                    lastMoveX = -1;
                    lastMoveY = -1;
                }, 1500);
            }
        })
        .catch(err => console.log('获取游戏状态失败', err));
}

// 普通对战落子
function makeMove(x, y) {
    if (!gameActive || !myTurn) return;
    fetch('api/make_move.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ x, y })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            myTurn = false;
            selectedX = -1;
            selectedY = -1;
            updateTurnDisplay();
            setTimeout(updateGameState, 300);
        } else {
            alert('落子失败：' + data.message);
        }
    });
}

// 绘制普通棋盘
function drawBoard() {
    ctx.clearRect(0, 0, 480, 480);
    
    ctx.lineWidth = 1.5;
    ctx.strokeStyle = '#8b5a2b';
    for (let i = 0; i < boardSize; i++) {
        ctx.beginPath();
        ctx.moveTo(i * cellSize + cellSize/2, cellSize/2);
        ctx.lineTo(i * cellSize + cellSize/2, 480 - cellSize/2);
        ctx.stroke();
        ctx.beginPath();
        ctx.moveTo(cellSize/2, i * cellSize + cellSize/2);
        ctx.lineTo(480 - cellSize/2, i * cellSize + cellSize/2);
        ctx.stroke();
    }
    
    const stars = [[7,7], [3,3], [11,3], [3,11], [11,11]];
    ctx.fillStyle = '#8b5a2b';
    stars.forEach(([x, y]) => {
        ctx.beginPath();
        ctx.arc(x * cellSize + cellSize/2, y * cellSize + cellSize/2, 4, 0, 2*Math.PI);
        ctx.fill();
    });
    
    for (let i = 0; i < boardSize; i++) {
        for (let j = 0; j < boardSize; j++) {
            if (boardData[i][j] !== 0) {
                const x = i * cellSize + cellSize/2;
                const y = j * cellSize + cellSize/2;
                const gradient = ctx.createRadialGradient(x-4, y-4, 5, x, y, cellSize/2);
                
                if (boardData[i][j] === 1) {
                    gradient.addColorStop(0, '#2b2b2b');
                    gradient.addColorStop(1, '#1a1a1a');
                } else {
                    gradient.addColorStop(0, '#f5f5f5');
                    gradient.addColorStop(1, '#cccccc');
                }
                
                ctx.beginPath();
                ctx.arc(x, y, cellSize/2 * 0.8, 0, 2*Math.PI);
                ctx.fillStyle = gradient;
                ctx.fill();
                ctx.strokeStyle = '#444';
                ctx.lineWidth = 1;
                ctx.stroke();
            }
        }
    }

    if (selectedX >= 0 && selectedX < boardSize && selectedY >= 0 && selectedY < boardSize && myTurn && gameActive) {
        if (boardData[selectedX][selectedY] === 0) {
            const x = selectedX * cellSize + cellSize/2;
            const y = selectedY * cellSize + cellSize/2;
            ctx.save();
            ctx.strokeStyle = '#00ff00';
            ctx.lineWidth = 4;
            ctx.beginPath();
            ctx.arc(x, y, cellSize/2 * 0.9, 0, 2*Math.PI);
            ctx.stroke();
            ctx.restore();
        } else {
            selectedX = -1;
            selectedY = -1;
        }
    }

    if (gameOver && lastMoveX >= 0 && lastMoveY >= 0) {
        const x = lastMoveX * cellSize + cellSize/2;
        const y = lastMoveY * cellSize + cellSize/2;
        ctx.save();
        ctx.strokeStyle = '#ff0000';
        ctx.lineWidth = 6;
        ctx.shadowColor = '#ff0000';
        ctx.shadowBlur = 10;
        ctx.beginPath();
        ctx.arc(x, y, cellSize/2 * 0.9, 0, 2*Math.PI);
        ctx.stroke();
        ctx.restore();
    }
}

function updateTurnDisplay() {
    const turnEl = document.getElementById('turn-indicator');
    if (isWaiting) {
        turnEl.innerText = '⏳ 等待对方接受挑战...';
    } else if (!gameActive) {
        turnEl.innerText = '对局已结束';
    } else if (myTurn) {
        turnEl.innerText = '⏳ 轮到你了（点击棋子选中，再次点击确认）';
    } else {
        turnEl.innerText = '⏳ 等待对方落子';
    }
}

boardCanvas.addEventListener('click', (e) => {
    if (gameMode !== 'normal' && gameMode !== 'ai') return;
    if (!gameActive || !myTurn || isWaiting) return;
    
    const rect = boardCanvas.getBoundingClientRect();
    const scaleX = boardCanvas.width / rect.width;
    const scaleY = boardCanvas.height / rect.height;
    const mouseX = (e.clientX - rect.left) * scaleX;
    const mouseY = (e.clientY - rect.top) * scaleY;
    const col = Math.floor(mouseX / cellSize);
    const row = Math.floor(mouseY / cellSize);
    
    if (col < 0 || col >= boardSize || row < 0 || row >= boardSize) return;
    if (boardData[col][row] !== 0) return;
    
    if (selectedX === col && selectedY === row) {
        makeMove(col, row);
    } else {
        selectedX = col;
        selectedY = row;
        drawBoard();
    }
});

// 认输
document.getElementById('surrender-btn').addEventListener('click', () => {
    if (!gameActive || !currentRoomId) return;
    if (confirm('确定要认输吗？')) {
        fetch('api/surrender.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ room_id: currentRoomId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('你已认输');
            } else {
                alert('认输失败：' + data.message);
            }
        });
    }
});

// 返回大厅（普通对战）
document.getElementById('back-to-hall').addEventListener('click', () => {
    fetch('api/leave_game.php', { method: 'POST' })
        .then(() => {
            switchToHall();
        });
});

// 商店功能
document.getElementById('shop-btn').addEventListener('click', showShop);
document.getElementById('close-shop').addEventListener('click', () => {
    document.getElementById('shop-modal').style.display = 'none';
});

function showShop() {
    document.getElementById('shop-score').innerText = document.getElementById('current-score').innerText.replace('分', '');
    document.getElementById('shop-cards').innerText = hintCards;
    document.getElementById('shop-modal').style.display = 'flex';
}

document.getElementById('buy-hint-btn').addEventListener('click', () => {
    if (hintCards >= 10) {
        document.getElementById('shop-error').innerText = '最多只能拥有10张指点卡';
        return;
    }
    fetch('api/buy_hint_card.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' }
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            hintCards = data.hint_cards;
            document.getElementById('hint-cards').innerHTML = `🎴 ${hintCards}`;
            document.getElementById('shop-cards').innerText = hintCards;
            document.getElementById('current-score').innerText = `${data.score}分`;
            document.getElementById('shop-score').innerText = data.score;
            document.getElementById('shop-error').innerText = '';
            alert('购买成功！');
        } else {
            document.getElementById('shop-error').innerText = data.message;
        }
    });
});

// 指点卡使用（普通对战）
document.getElementById('use-hint-btn').addEventListener('click', () => {
    if (!gameActive || !myTurn || isWaiting) return;
    if (hintCards <= 0) {
        alert('你没有指点卡了');
        return;
    }
    if (hintUsed >= 2) {
        alert('本局最多使用2张指点卡');
        return;
    }
    fetch('api/use_hint_card.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ room_id: currentRoomId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            hintCards = data.hint_cards;
            document.getElementById('hint-cards').innerHTML = `🎴 ${hintCards}`;
            setTimeout(updateGameState, 300);
        } else {
            alert('使用失败：' + data.message);
        }
    });
});

// 历史记录
document.getElementById('history-btn').addEventListener('click', showHistory);
document.getElementById('close-history').addEventListener('click', () => {
    document.getElementById('history-modal').style.display = 'none';
});

function showHistory() {
    fetch('api/get_history.php')
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                const container = document.getElementById('history-list');
                container.innerHTML = '';
                if (data.history.length === 0) {
                    container.innerHTML = '<p style="text-align:center;color:#8e8e93;">暂无对局记录</p>';
                } else {
                    data.history.forEach(record => {
                        const date = new Date(record.date * 1000).toLocaleString();
                        const resultClass = record.result === 'win' ? 'win' : 'loss';
                        const resultText = record.result === 'win' ? '胜利' : '失败';
                        const div = document.createElement('div');
                        div.className = `history-item ${resultClass}`;
                        div.innerHTML = `
                            <div class="history-info">
                                <div class="history-opponent">对手：${record.opponent}</div>
                                <div class="history-result ${resultClass}">${resultText}</div>
                                <div class="history-moves">步数：${record.moves}</div>
                            </div>
                            <div class="history-date">${date}</div>
                        `;
                        container.appendChild(div);
                    });
                }
                document.getElementById('history-modal').style.display = 'flex';
            }
        });
}

// 账号设置
document.getElementById('settings-btn').addEventListener('click', () => {
    document.getElementById('settings-modal').style.display = 'flex';
});
document.getElementById('close-settings').addEventListener('click', () => {
    document.getElementById('settings-modal').style.display = 'none';
    document.getElementById('settings-error').innerText = '';
});
document.getElementById('update-profile').addEventListener('click', updateProfile);

function updateProfile() {
    const newUsername = document.getElementById('new-username').value.trim();
    const newPassword = document.getElementById('new-password').value;
    fetch('api/update_profile.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            new_username: newUsername,
            new_password: newPassword
        })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            alert('修改成功！' + (data.new_username ? '新用户名：' + data.new_username : ''));
            if (data.new_username) {
                document.getElementById('current-username').innerText = data.new_username;
                currentUser = data.new_username;
            }
            document.getElementById('settings-modal').style.display = 'none';
            document.getElementById('new-username').value = '';
            document.getElementById('new-password').value = '';
        } else {
            document.getElementById('settings-error').innerText = data.message;
        }
    });
}

// 刷新玩家列表
document.getElementById('refresh-players').addEventListener('click', fetchPlayers);

// ========== 视图切换函数（供brawl.js调用） ==========
window.switchToHall = function() {
    gameMode = 'normal';
    document.getElementById('hall-view').style.display = 'block';
    document.getElementById('game-view').style.display = 'none';
    document.getElementById('brawl-room-view').style.display = 'none';
    document.getElementById('brawl-game-view').style.display = 'none';
    if (gameStateInterval) clearInterval(gameStateInterval);
    if (aiCheckInterval) clearInterval(aiCheckInterval);
    gameStateInterval = setInterval(updateGameState, 2000);
    aiCheckInterval = setInterval(checkAITurn, 1000);
    // 重新启动普通轮询
    if (!playersInterval) startPolling();
};

function switchToGameView(mode) {
    gameMode = mode;
    document.getElementById('hall-view').style.display = 'none';
    document.getElementById('game-view').style.display = 'block';
    document.getElementById('brawl-room-view').style.display = 'none';
    document.getElementById('brawl-game-view').style.display = 'none';
}

// 导出当前用户供brawl.js使用
window.currentUser = currentUser;

// ========== 大乱斗入口 ==========
document.getElementById('start-brawl-btn').addEventListener('click', () => {
    document.getElementById('choose-side-modal').style.display = 'flex';
});

document.getElementById('close-choose-side').addEventListener('click', () => {
    document.getElementById('choose-side-modal').style.display = 'none';
});

document.getElementById('choose-black-btn').addEventListener('click', () => {
    if (typeof window.createBrawlRoom === 'function') {
        window.createBrawlRoom('black');
    }
    document.getElementById('choose-side-modal').style.display = 'none';
});

document.getElementById('choose-white-btn').addEventListener('click', () => {
    if (typeof window.createBrawlRoom === 'function') {
        window.createBrawlRoom('white');
    }
    document.getElementById('choose-side-modal').style.display = 'none';
});