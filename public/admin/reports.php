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
    <title>Reports - CanteenPOS Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="../assets/icons/icon.svg">
</head>
<body>
        <div class="header">
            <div class="logo">
                <div class="logo-icon">📈</div>
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
            <a class="sidebar-item active" href="reports.php">📈 Reports</a>
            <a class="sidebar-item" href="chat.php">💬 Chat</a>
        </div>
        
        <div class="content">
            <h2>Sales Reports</h2>
            
            <div class="card" style="margin-bottom:20px;">
                <div style="display:flex;gap:10px;align-items:center;">
                    <input type="date" id="reportFrom" style="padding:8px;border:1px solid #ddd;border-radius:4px;">
                    <span>to</span>
                    <input type="date" id="reportTo" style="padding:8px;border:1px solid #ddd;border-radius:4px;">
                    <button class="btn btn-primary" onclick="generateReport()">Generate</button>
                </div>
            </div>
            
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="label">Total Sales</div>
                    <div class="value" id="reportTotalSales">₱0.00</div>
                </div>
                <div class="stat-card">
                    <div class="label">Total Orders</div>
                    <div class="value" id="reportTotalOrders">0</div>
                </div>
                <div class="stat-card">
                    <div class="label">Average Order</div>
                    <div class="value" id="reportAvgOrder">₱0.00</div>
                </div>
            </div>
            
            <div class="card">
                <h3 style="margin-bottom:15px;">Sales by Product</h3>
                <table>
                    <thead><tr><th>Product</th><th>Quantity Sold</th><th>Revenue</th></tr></thead>
                    <tbody id="reportProducts"></tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('reportFrom').valueAsDate = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
        document.getElementById('reportTo').valueAsDate = new Date();
        
        async function generateReport() {
            const from = document.getElementById('reportFrom').value;
            const to = document.getElementById('reportTo').value;
            
            const res = await fetch(`../api/stats.php?action=report&from=${from}&to=${to}`);
            const data = await res.json();
            console.log('Report response:', data); // Debug
            
            if (data.success) {
                document.getElementById('reportTotalSales').textContent = '₱' + parseFloat(data.total_sales).toFixed(2);
                document.getElementById('reportTotalOrders').textContent = data.total_orders;
                document.getElementById('reportAvgOrder').textContent = '₱' + (data.total_orders > 0 ? (data.total_sales / data.total_orders).toFixed(2) : '0.00');
                
                const tbody = document.getElementById('reportProducts');
                if (!data.products || data.products.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="3" style="text-align:center;color:#888;">No data</td></tr>';
                } else {
                    tbody.innerHTML = data.products.map(p => `
                        <tr>
                            <td>${p.name}</td>
                            <td>${p.total_sold}</td>
                            <td>₱${parseFloat(p.total_revenue).toFixed(2)}</td>
                        </tr>
                    `).join('');
                }
            } else {
                console.error('Error generating report:', data.message);
            }
        }
        
        generateReport();
    </script>
    <script>if('serviceWorker'in navigator){navigator.serviceWorker.register('../sw.js');}</script>
</body>
</html>