<?php
session_start();
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'admin') {
    header('Location: dashboard.php');
    exit;
} elseif (isset($_SESSION['user_id'])) {
    header('../pos.php');
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - CanteenPOS</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        body { background: linear-gradient(135deg, #f0f2f5 0%, #e2e8f0 100%); min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 20px; }
        .login-box { background: white; padding: 40px; border-radius: 20px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); width: 380px; max-width: 100%; }
        .logo { text-align: center; margin-bottom: 24px; }
        .logo-icon { width: 64px; height: 64px; background: linear-gradient(135deg, #2563eb, #1d4ed8); border-radius: 16px; display: inline-flex; align-items: center; justify-content: center; color: white; font-size: 28px; margin-bottom: 12px; box-shadow: 0 4px 16px rgba(37,99,235,0.3); }
        h1 { font-size: 22px; color: #0f172a; margin-bottom: 4px; font-weight: 700; }
        .subtitle { font-size: 13px; color: #94a3b8; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; color: #475569; margin-bottom: 6px; font-weight: 500; }
        input { width: 100%; height: 48px; padding: 0 16px; border: 1.5px solid #e2e8f0; border-radius: 12px; font-size: 15px; outline: none; transition: all 0.2s; }
        input:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }
        select { width: 100%; height: 48px; padding: 0 16px; border: 1.5px solid #e2e8f0; border-radius: 12px; font-size: 15px; outline: none; }
        .btn { width: 100%; height: 48px; border-radius: 12px; font-size: 15px; justify-content: center; }
        .btn-outline { margin-top: 10px; }
        .error { background: #fef2f2; color: #ef4444; padding: 12px; border-radius: 10px; margin-bottom: 16px; font-size: 13px; display: none; border: 1px solid #fecaca; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">
            <div class="logo-icon">⚙️</div>
            <h1>Admin Panel</h1>
            <p class="subtitle">CanteenPOS Administration</p>
        </div>
        
        <form id="adminLoginForm">
            <div id="loginMessage" class="error"></div>
            
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="username" required>
            </div>
            
            <div class="form-group">
                <label>Password</label>
                <input type="password" id="password" required>
            </div>
            
            <button type="submit" class="btn">Login to Admin</button>
            <a href="../pos.php"><button type="button" class="btn btn-outline">Back to POS</button></a>
        </form>
    </div>

    <script>
        document.getElementById('adminLoginForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button[type="submit"]');
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            btn.disabled = true;
            btn.textContent = 'Verifying...';

            try {
                const res = await fetch('../api/auth.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`
                });
                const data = await res.json();
                
                if (data.success) {
                    if (data.user.role === 'admin') {
                        window.location.href = 'dashboard.php';
                    } else {
                        document.getElementById('loginMessage').textContent = 'Admin access required';
                        document.getElementById('loginMessage').style.display = 'block';
                    }
                } else {
                    document.getElementById('loginMessage').textContent = data.message;
                    document.getElementById('loginMessage').style.display = 'block';
                }
            } catch (err) {
                document.getElementById('loginMessage').textContent = 'Connection error';
                document.getElementById('loginMessage').style.display = 'block';
            } finally {
                btn.disabled = false;
                btn.textContent = 'Login to Admin';
            }
        });
    </script>
</body>
</html>