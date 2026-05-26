<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}
$user = ['id' => $_SESSION['user_id'], 'username' => $_SESSION['username'], 'full_name' => $_SESSION['full_name'], 'role' => $_SESSION['role']];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat - CanteenPOS Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="../assets/icons/icon.svg">
    <style>
        .chat-container { display: flex; height: calc(100vh - 160px); background: white; border-radius: 14px; box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 4px 12px rgba(0,0,0,0.06); overflow: hidden; border: 1px solid #e2e8f0; }
        .chat-users { width: 220px; border-right: 1px solid #e2e8f0; overflow: auto; }
        .chat-user { padding: 16px 18px; border-bottom: 1px solid #f1f5f9; cursor: pointer; font-size: 14px; color: #475569; transition: all 0.1s; }
        .chat-user:hover { background: #f8fafc; }
        .chat-user.active { background: #2563eb; color: white; font-weight: 500; }
        
        .chat-area { flex: 1; display: flex; flex-direction: column; }
        .chat-header { padding: 16px 20px; border-bottom: 1px solid #e2e8f0; font-weight: 600; color: #0f172a; }
        .chat-messages { flex: 1; padding: 20px; overflow: auto; }
        .chat-input { padding: 16px 20px; border-top: 1px solid #e2e8f0; display: flex; gap: 12px; }
        .chat-input input { flex: 1; height: 46px; padding: 0 16px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 14px; outline: none; }
        .chat-input input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
    </style>
</head>
<body>
        <div class="header">
            <div class="logo">
                <div class="logo-icon">💬</div>
                <div><h1>CanteenPOS</h1><span>Admin</span></div>
            </div>
            <div class="header-right">
                <a href="dashboard.php"><button class="btn btn-outline">📊 Dashboard</button></a>
                <span style="font-size:14px;color:#475569;font-weight:500;"><?= htmlspecialchars($user['full_name']) ?></span>
                <a href="../api/logout.php"><button class="btn btn-outline">Logout</button></a>
            </div>
        </div>
    
    <div class="main">
        <div class="sidebar">
            <a class="sidebar-item" href="dashboard.php">📊 Dashboard</a>
            <a class="sidebar-item" href="products.php">📦 Products</a>
            <a class="sidebar-item" href="categories.php">🏷️ Categories</a>
            <a class="sidebar-item" href="orders.php">📋 Orders</a>
            <a class="sidebar-item" href="tickets.php">🎫 Tickets</a>
            <a class="sidebar-item" href="users.php">👥 Users</a>
            <a class="sidebar-item" href="reports.php">📈 Reports</a>
            <a class="sidebar-item active" href="chat.php">💬 Chat</a>
        </div>
        
        <div class="content">
            <h2>Chat with Cashiers</h2>
            
            <div class="chat-container">
                <div class="chat-users" id="cashierList"></div>
                <div class="chat-area">
                    <div class="chat-header" id="chatHeader">Select a cashier to start chatting</div>
                    <div class="chat-messages" id="chatMessages"></div>
                    <div class="chat-input">
                        <input type="text" id="chatInput" placeholder="Type a message..." disabled>
                        <button class="btn btn-primary" id="sendChatBtn" onclick="sendChat()" disabled>Send</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let selectedChatUser = null;
        let currentUserId = <?php echo $_SESSION['user_id']; ?>;
        
        async function loadCashiers() {
            const res = await fetch('../api/users.php?action=cashiers');
            const data = await res.json();
            
            const list = document.getElementById('cashierList');
            if (!data.users || data.users.length === 0) {
                list.innerHTML = '<div style="padding:15px;color:#888;">No cashiers available</div>';
                return;
            }
            
            list.innerHTML = data.users.map(u => `
                <div class="chat-user" onclick="selectChatUser(${u.id}, '${u.full_name}')">${u.full_name}</div>
            `).join('');
        }
        
        function selectChatUser(userId, userName) {
            selectedChatUser = userId;
            document.getElementById('chatHeader').textContent = userName;
            document.getElementById('chatInput').disabled = false;
            document.getElementById('sendChatBtn').disabled = false;
            
            document.querySelectorAll('.chat-user').forEach(u => u.classList.remove('active'));
            event.target.classList.add('active');
            
            loadChatMessages();
            
            // Auto-refresh every 3 seconds for real-time chat
            if (window.chatInterval) clearInterval(window.chatInterval);
            window.chatInterval = setInterval(loadChatMessages, 3000);
        }
        
        // Load cashiers on page load and also load messages if there's only one cashier
        loadCashiers().then(() => {
            const cashierElements = document.querySelectorAll('.chat-user');
            if (cashierElements.length === 1) {
                // Auto-select the only cashier
                const cashierId = parseInt(cashierElements[0].getAttribute('onclick').match(/\d+/)[0]);
                const cashierName = cashierElements[0].textContent;
                selectChatUser(cashierId, cashierName);
            }
        });
        
        async function loadChatMessages() {
            if (!selectedChatUser) return;
            
            const res = await fetch(`../api/chat.php?action=messages&user_id=${selectedChatUser}`);
            const data = await res.json();
            console.log('Chat messages response:', data);
            
            const container = document.getElementById('chatMessages');
            if (!data.success) {
                container.innerHTML = '<div style="text-align:center;color:red;padding:20px;">Error: ' + (data.message || 'Unknown error') + '</div>';
                return;
            }
            
            if (!data.messages || data.messages.length === 0) {
                container.innerHTML = '<div style="text-align:center;color:#888;padding:20px;">No messages yet</div>';
                return;
            }
            
            const currentUserId = <?php echo $_SESSION['user_id']; ?>;
            
            container.innerHTML = data.messages.map(m => {
                const isSent = parseInt(m.sender_id) === currentUserId;
                const style = isSent 
                    ? 'background:#2563eb;color:white;margin-left:auto;border-radius:10px 10px 0 10px;' 
                    : 'background:#e5e5ea;color:black;border-radius:10px 10px 10px 0;';
                return `<div style="max-width:70%;padding:10px 14px;margin-bottom:10px;word-wrap:break-word;${style}">
                    <div style="font-size:14px;">${m.message}</div>
                    <div style="font-size:10px;opacity:0.7;margin-top:4px;">${m.created_at ? new Date(m.created_at).toLocaleTimeString() : ''}</div>
                </div>`;
            }).join('');
            
            container.scrollTop = container.scrollHeight;
        }
        
        async function sendChat() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            
            if (!message || !selectedChatUser) return;
            
            const res = await fetch('../api/chat.php?action=send', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `receiver_id=${selectedChatUser}&message=${encodeURIComponent(message)}`
            });
            
            const data = await res.json();
            console.log('Send response:', data);
            
            input.value = '';
            
            // Reload messages immediately after sending
            setTimeout(loadChatMessages, 100);
        }
        
        document.getElementById('chatInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendChat();
        });
        
        loadCashiers();
    </script>
    <script>if('serviceWorker'in navigator){navigator.serviceWorker.register('../sw.js');}</script>
</body>
</html>