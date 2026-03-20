// brawl.js - 大乱斗模式专用脚本（最终修复版）
// 注意：boardSize, cellSize 已在 script.js 中定义，这里直接使用

// 全局大乱斗变量
let brawlRoomId = null;
let brawlPlayers = [];
let brawlTurn = 0;
let brawlBoardData = Array(15).fill().map(() => Array(15).fill(0));
let brawlMyPlayerIndex = -1;
let brawlGameActive = false;
let brawlGameOver = false;
let brawlLastMoveX = -1, brawlLastMoveY = -1;
let brawlSelectedX = -1, brawlSelectedY = -1;
let isAIMoving = false; // 防止AI重复落子

// 轮询控制
let brawlRoomInterval;
let brawlGameInterval;
let brawlChatInterval;

const brawlCanvas = document.getElementById('brawl-board');
const brawlCtx = brawlCanvas ? brawlCanvas.getContext('2d') : null;

// ========== 房间创建/加入 ==========
window.createBrawlRoom = function(color) {
    fetch('api/create_brawl_room.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ color: color })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            brawlRoomId = data.room_id;
            switchToBrawlRoomView();
            startBrawlRoomPolling();
        } else {
            alert('❌ 创建失败：' + data.message);
        }
    })
    .catch(err => {
        console.error('创建房间请求失败', err);
        alert('网络错误，请稍后重试');
    });
};

function switchToBrawlRoomView() {
    window.gameMode = 'brawl-room';
    document.getElementById('hall-view').style.display = 'none';
    document.getElementById('game-view').style.display = 'none';
    document.getElementById('brawl-room-view').style.display = 'block';
    document.getElementById('brawl-game-view').style.display = 'none';
    document.getElementById('brawl-room-id').innerText = brawlRoomId;
    // 停止普通轮询
    if (window.gameStateInterval) clearInterval(window.gameStateInterval);
    if (window.aiCheckInterval) clearInterval(window.aiCheckInterval);
}

function startBrawlRoomPolling() {
    brawlRoomInterval = setInterval(updateBrawlRoom, 2000);
    brawlChatInterval = setInterval(fetchBrawlRoomChat, 2000);
    updateBrawlRoom(); // 立即获取
    fetchBrawlRoomChat();
}

function updateBrawlRoom() {
    fetch(`api/get_brawl_room_state.php?room_id=${brawlRoomId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                brawlPlayers = data.players;
                updateBrawlRoomPlayers();
            } else {
                alert('房间已失效');
                exitBrawlRoom();
            }
        });
}

function updateBrawlRoomPlayers() {
    const blackList = document.getElementById('black-room-players');
    const whiteList = document.getElementById('white-room-players');
    const blackCount = document.getElementById('black-count');
    const whiteCount = document.getElementById('white-count');
    blackList.innerHTML = '';
    whiteList.innerHTML = '';
    let black = 0, white = 0;
    brawlPlayers.forEach(p => {
        const li = document.createElement('li');
        let status = p.isAI ? 'AI' : (p.ready ? '已准备' : '等待中');
        let statusClass = p.isAI ? 'status-ai' : (p.ready ? 'status-online' : 'status-waiting');
        li.innerHTML = `
            <span class="player-name">${p.username}</span>
            <span class="player-status ${statusClass}">${status}</span>
        `;
        if (p.color === 'black') {
            blackList.appendChild(li);
            black++;
        } else {
            whiteList.appendChild(li);
            white++;
        }
    });
    blackCount.innerText = black;
    whiteCount.innerText = white;
}

function fetchBrawlRoomChat() {
    fetch(`api/get_brawl_chat.php?room_id=${brawlRoomId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displayBrawlChat('brawl-room-chat', data.chats);
            }
        });
}

document.getElementById('brawl-room-chat-send').addEventListener('click', () => {
    sendBrawlChat('brawl-room-chat-input', brawlRoomId, 'brawl-room-chat');
});

// AI补足并开始
document.getElementById('brawl-start-ai-btn').addEventListener('click', () => {
    fetch('api/start_brawl_with_ai.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ room_id: brawlRoomId })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            brawlRoomId = data.game_id;
            switchToBrawlGameView();
            startBrawlGamePolling();
        } else {
            alert('❌ 开始失败：' + data.message);
        }
    });
});

document.getElementById('brawl-room-exit').addEventListener('click', () => {
    if (confirm('确定退出房间吗？')) {
        fetch('api/leave_game.php', { method: 'POST' })
            .then(() => exitBrawlRoom());
    }
});

function exitBrawlRoom() {
    if (brawlRoomInterval) clearInterval(brawlRoomInterval);
    if (brawlChatInterval) clearInterval(brawlChatInterval);
    brawlRoomId = null;
    window.switchToHall();
}

// ========== 游戏进行中 ==========
function switchToBrawlGameView() {
    window.gameMode = 'brawl-game';
    document.getElementById('hall-view').style.display = 'none';
    document.getElementById('game-view').style.display = 'none';
    document.getElementById('brawl-room-view').style.display = 'none';
    document.getElementById('brawl-game-view').style.display = 'block';
    if (brawlRoomInterval) clearInterval(brawlRoomInterval);
    if (brawlChatInterval) clearInterval(brawlChatInterval);
}

function startBrawlGamePolling() {
    brawlGameInterval = setInterval(updateBrawlGame, 2000);
    brawlChatInterval = setInterval(fetchBrawlGameChat, 2000);
    updateBrawlGame();
    fetchBrawlGameChat();
}

function updateBrawlGame() {
    fetch(`api/get_brawl_state.php?room_id=${brawlRoomId}`)
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                alert('游戏已结束');
                exitBrawlGame();
                return;
            }
            brawlPlayers = data.players;
            brawlTurn = data.turn;
            brawlBoardData = data.board;
            brawlGameActive = (data.status === 'playing');
            brawlGameOver = (data.winner !== null);
            brawlLastMoveX = data.last_move_x;
            brawlLastMoveY = data.last_move_y;

            brawlMyPlayerIndex = brawlPlayers.findIndex(p => p.username === window.currentUser);
            
            updateBrawlGamePlayers();
            drawBrawlBoard();
            updateBrawlTurnDisplay();

            if (brawlGameOver) {
                const animDiv = document.getElementById('result-animation');
                if (data.winner === brawlPlayers[brawlMyPlayerIndex]?.color) {
                    animDiv.innerText = '🏆 胜利！';
                    animDiv.className = 'result-animation show victory';
                } else {
                    animDiv.innerText = '😢 失败';
                    animDiv.className = 'result-animation show defeat';
                }
                setTimeout(() => animDiv.className = 'result-animation', 1500);
            }

            // AI自动落子逻辑
            if (brawlGameActive && !brawlGameOver && !isAIMoving) {
                const currentPlayer = brawlPlayers[brawlTurn];
                if (currentPlayer && currentPlayer.isAI) {
                    isAIMoving = true;
                    setTimeout(() => {
                        fetch('api/brawl_ai_move.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ room_id: brawlRoomId })
                        })
                        .then(res => res.json())
                        .then(aiData => {
                            isAIMoving = false;
                            if (aiData.success) {
                                updateBrawlGame();
                            } else {
                                console.log('AI落子失败:', aiData.message);
                                updateBrawlGame();
                            }
                        })
                        .catch(err => {
                            isAIMoving = false;
                            console.log('AI落子请求失败', err);
                            updateBrawlGame();
                        });
                    }, 500);
                }
            }
        });
}

function updateBrawlGamePlayers() {
    const blackList = document.getElementById('brawl-black-players');
    const whiteList = document.getElementById('brawl-white-players');
    blackList.innerHTML = '';
    whiteList.innerHTML = '';
    brawlPlayers.forEach(p => {
        const li = document.createElement('li');
        let statusText = p.isAI ? 'AI' : (p.surrendered ? '已投降' : '在线');
        let statusClass = p.isAI ? 'status-ai' : (p.surrendered ? 'status-surrendered' : 'status-online');
        li.innerHTML = `
            <span class="player-name">${p.username}</span>
            <span class="player-status ${statusClass}">${statusText}</span>
        `;
        if (p.color === 'black') {
            blackList.appendChild(li);
        } else {
            whiteList.appendChild(li);
        }
    });
}

function drawBrawlBoard() {
    if (!brawlCtx) return;
    
    // 填充棋盘背景色
    brawlCtx.fillStyle = '#e0c9a6';
    brawlCtx.fillRect(0, 0, 480, 480);
    
    brawlCtx.lineWidth = 1.5;
    brawlCtx.strokeStyle = '#8b5a2b';
    for (let i = 0; i < boardSize; i++) {
        brawlCtx.beginPath();
        brawlCtx.moveTo(i * cellSize + cellSize/2, cellSize/2);
        brawlCtx.lineTo(i * cellSize + cellSize/2, 480 - cellSize/2);
        brawlCtx.stroke();
        brawlCtx.beginPath();
        brawlCtx.moveTo(cellSize/2, i * cellSize + cellSize/2);
        brawlCtx.lineTo(480 - cellSize/2, i * cellSize + cellSize/2);
        brawlCtx.stroke();
    }
    
    const stars = [[7,7], [3,3], [11,3], [3,11], [11,11]];
    brawlCtx.fillStyle = '#8b5a2b';
    stars.forEach(([x, y]) => {
        brawlCtx.beginPath();
        brawlCtx.arc(x * cellSize + cellSize/2, y * cellSize + cellSize/2, 4, 0, 2*Math.PI);
        brawlCtx.fill();
    });
    
    for (let i = 0; i < boardSize; i++) {
        for (let j = 0; j < boardSize; j++) {
            if (brawlBoardData[i][j] !== 0) {
                const x = i * cellSize + cellSize/2;
                const y = j * cellSize + cellSize/2;
                const gradient = brawlCtx.createRadialGradient(x-4, y-4, 5, x, y, cellSize/2);
                
                if (brawlBoardData[i][j] === 1) {
                    gradient.addColorStop(0, '#2b2b2b');
                    gradient.addColorStop(1, '#1a1a1a');
                } else {
                    gradient.addColorStop(0, '#f5f5f5');
                    gradient.addColorStop(1, '#cccccc');
                }
                
                brawlCtx.beginPath();
                brawlCtx.arc(x, y, cellSize/2 * 0.8, 0, 2*Math.PI);
                brawlCtx.fillStyle = gradient;
                brawlCtx.fill();
                brawlCtx.strokeStyle = '#444';
                brawlCtx.lineWidth = 1;
                brawlCtx.stroke();
            }
        }
    }
    
    // 选中高亮
    if (brawlSelectedX >= 0 && brawlSelectedY >= 0 && brawlGameActive && !brawlGameOver && brawlPlayers[brawlTurn]?.username === window.currentUser) {
        if (brawlBoardData[brawlSelectedX][brawlSelectedY] === 0) {
            const x = brawlSelectedX * cellSize + cellSize/2;
            const y = brawlSelectedY * cellSize + cellSize/2;
            brawlCtx.save();
            brawlCtx.strokeStyle = '#00ff00';
            brawlCtx.lineWidth = 4;
            brawlCtx.beginPath();
            brawlCtx.arc(x, y, cellSize/2 * 0.9, 0, 2*Math.PI);
            brawlCtx.stroke();
            brawlCtx.restore();
        }
    }
    
    // 最后一子红色高亮
    if (brawlGameOver && brawlLastMoveX >= 0 && brawlLastMoveY >= 0) {
        const x = brawlLastMoveX * cellSize + cellSize/2;
        const y = brawlLastMoveY * cellSize + cellSize/2;
        brawlCtx.save();
        brawlCtx.strokeStyle = '#ff0000';
        brawlCtx.lineWidth = 6;
        brawlCtx.shadowColor = '#ff0000';
        brawlCtx.shadowBlur = 10;
        brawlCtx.beginPath();
        brawlCtx.arc(x, y, cellSize/2 * 0.9, 0, 2*Math.PI);
        brawlCtx.stroke();
        brawlCtx.restore();
    }
}

function updateBrawlTurnDisplay() {
    const turnEl = document.getElementById('brawl-turn-indicator');
    if (!brawlGameActive) {
        turnEl.innerText = '游戏未开始';
        return;
    }
    if (brawlGameOver) {
        turnEl.innerText = '游戏已结束';
        return;
    }
    const currentPlayer = brawlPlayers[brawlTurn];
    if (!currentPlayer) return;
    if (currentPlayer.username === window.currentUser) {
        turnEl.innerText = `⏳ 轮到你了 (${currentPlayer.color === 'black' ? '黑方' : '白方'})`;
    } else {
        turnEl.innerText = `⏳ 轮到 ${currentPlayer.username} (${currentPlayer.color === 'black' ? '黑方' : '白方'})`;
    }
}

// 玩家落子
function brawlMakeMove(x, y) {
    if (!brawlGameActive || brawlGameOver) return;
    const currentPlayer = brawlPlayers[brawlTurn];
    if (!currentPlayer || currentPlayer.username !== window.currentUser) return;
    
    fetch('api/brawl_move.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ room_id: brawlRoomId, x, y })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            brawlSelectedX = -1;
            brawlSelectedY = -1;
            updateBrawlGame();
        } else {
            alert('落子失败：' + data.message);
        }
    });
}

// 画布点击事件
if (brawlCanvas) {
    brawlCanvas.addEventListener('click', (e) => {
        if (window.gameMode !== 'brawl-game' || !brawlGameActive || brawlGameOver) return;
        const currentPlayer = brawlPlayers[brawlTurn];
        if (!currentPlayer || currentPlayer.username !== window.currentUser) return;
        
        const rect = brawlCanvas.getBoundingClientRect();
        const scaleX = brawlCanvas.width / rect.width;
        const scaleY = brawlCanvas.height / rect.height;
        const mouseX = (e.clientX - rect.left) * scaleX;
        const mouseY = (e.clientY - rect.top) * scaleY;
        const col = Math.floor(mouseX / cellSize);
        const row = Math.floor(mouseY / cellSize);
        
        if (col < 0 || col >= boardSize || row < 0 || row >= boardSize) return;
        if (brawlBoardData[col][row] !== 0) return;
        
        if (brawlSelectedX === col && brawlSelectedY === row) {
            brawlMakeMove(col, row);
        } else {
            brawlSelectedX = col;
            brawlSelectedY = row;
            drawBrawlBoard();
        }
    });
}

// 投降
document.getElementById('brawl-surrender-btn').addEventListener('click', () => {
    if (!brawlGameActive || brawlGameOver) return;
    if (confirm('确定要投降吗？需要同阵营所有真人玩家同意。')) {
        fetch('api/brawl_surrender.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ room_id: brawlRoomId })
        })
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                alert('投降请求已提交');
                setTimeout(updateBrawlGame, 500);
            } else {
                alert('操作失败：' + data.message);
            }
        });
    }
});

// 退出游戏
document.getElementById('brawl-game-exit').addEventListener('click', () => {
    if (confirm('确定退出游戏吗？')) {
        fetch('api/leave_game.php', { method: 'POST' })
            .then(() => exitBrawlGame());
    }
});

function exitBrawlGame() {
    if (brawlGameInterval) clearInterval(brawlGameInterval);
    if (brawlChatInterval) clearInterval(brawlChatInterval);
    brawlRoomId = null;
    window.switchToHall();
}

// 聊天通用函数
function sendBrawlChat(inputId, roomId, chatDivId) {
    const input = document.getElementById(inputId);
    const msg = input.value.trim();
    if (!msg) return;
    fetch('api/brawl_chat.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ room_id: roomId, message: msg })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            input.value = '';
            if (chatDivId === 'brawl-room-chat') {
                fetchBrawlRoomChat();
            } else {
                fetchBrawlGameChat();
            }
        } else {
            alert('发送失败：' + data.message);
        }
    });
}

function displayBrawlChat(containerId, chats) {
    const container = document.getElementById(containerId);
    container.innerHTML = '';
    chats.forEach(chat => {
        const msgDiv = document.createElement('div');
        msgDiv.className = 'chat-message' + (chat.username === window.currentUser ? ' self' : '');
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

function fetchBrawlGameChat() {
    fetch(`api/get_brawl_chat.php?room_id=${brawlRoomId}`)
        .then(res => res.json())
        .then(data => {
            if (data.success) {
                displayBrawlChat('brawl-game-chat', data.chats);
            }
        });
}

document.getElementById('brawl-game-chat-send').addEventListener('click', () => {
    sendBrawlChat('brawl-game-chat-input', brawlRoomId, 'brawl-game-chat');
});