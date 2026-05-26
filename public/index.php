<!DOCTYPE html>
<!--
    CanteenPOS Login Page
    Entry point for the POS system.
    Users authenticate here and are redirected based on their role:
    - Admin -> admin/ directory
    - Cashier -> pos.php
    
    Default credentials:
    - Admin: admin / admin123
    - Cashier: cashier / cashier123
-->
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - CanteenPOS</title>
    <link rel="stylesheet" href="assets/css/styles.css">
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
        .btn { width: 100%; height: 48px; border-radius: 12px; font-size: 15px; justify-content: center; }
        .error { background: #fef2f2; color: #ef4444; padding: 12px; border-radius: 10px; margin-bottom: 16px; font-size: 13px; display: none; border: 1px solid #fecaca; }
        .creds { text-align: center; font-size: 12px; color: #94a3b8; margin-top: 20px; padding-top: 16px; border-top: 1px solid #f1f5f9; }
    </style>
</head>
<body>
    <div class="login-box">
        <div class="logo">
            <!-- Logo icon (emoji) -->
            <div class="logo-icon">📦</div>
            <!-- Company name and subtitle -->
            <h1>CanteenPOS</h1>
            <p class="subtitle">Point of Sale System</p>
        </div>
        
        <!-- Login form - sends data to auth.php via AJAX -->
        <form id="loginForm">
            <!-- Error message container (hidden by default) -->
            <div id="loginMessage" class="error"></div>
            
            <!-- Username input field -->
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="username" required placeholder="Enter username">
            </div>
            
            <!-- Password input field -->
            <div class="form-group">
                <label>Password</label>
                <input type="password" id="password" required placeholder="Enter password">
            </div>
            
            <!-- Submit button -->
            <button type="submit" class="btn">Sign In</button>
        </form>
        
        <!-- Default credentials display for reference -->
        <div class="creds">
            Admin: admin / admin123<br>
            Cashier: cashier / cashier123
        </div>
    </div>

    <script>
        // Listen for form submission
        document.getElementById('loginForm').addEventListener('submit', async (e) => {
            // Prevent default form submission (page reload)
            e.preventDefault();
            
            // Get UI elements
            const btn = e.target.querySelector('button');
            const username = document.getElementById('username').value;
            const password = document.getElementById('password').value;
            
            // Disable button and show loading state
            btn.disabled = true;
            btn.textContent = 'Signing in...';

            try {
                // Send login request to API
                const res = await fetch('api/auth.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`
                });
                
                // Debug: Log response status
                console.log('Login response status:', res.status);
                
                // Parse JSON response
                const data = await res.json();
                console.log('Login response data:', data);
                
                if (data.success) {
                    console.log('Login successful, user ID:', data.user.id, 'role:', data.user.role);
                    // Login successful - redirect based on role
                    // Admin goes to admin panel, cashier goes to POS
                    window.location.href = data.user.role === 'admin' ? 'admin/' : 'pos.php';
                } else {
                    // Show error message
                    document.getElementById('loginMessage').textContent = data.message;
                    document.getElementById('loginMessage').style.display = 'block';
                }
            } catch (err) {
                console.error('Login error:', err);
                // Show connection error
                document.getElementById('loginMessage').textContent = 'Connection error';
                document.getElementById('loginMessage').style.display = 'block';
            } finally {
                // Re-enable button
                btn.disabled = false;
                btn.textContent = 'Sign In';
            }
        });
    </script>
</body>
</html>