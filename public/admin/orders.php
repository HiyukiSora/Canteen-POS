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
    <title>Orders - CanteenPOS Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .order-detail-row { display: flex; justify-content: space-between; padding: 6px 0; border-bottom: 1px solid #f1f5f9; }
    </style>
</head>
<body>
        <div class="header">
            <div class="logo">
                <div class="logo-icon">📋</div>
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
            <a class="sidebar-item active" href="orders.php">📋 Orders</a>
            <a class="sidebar-item" href="tickets.php">🎫 Tickets</a>
            <a class="sidebar-item" href="users.php">👥 Users</a>
            <a class="sidebar-item" href="reports.php">📈 Reports</a>
            <a class="sidebar-item" href="chat.php">💬 Chat</a>
        </div>
        
        <div class="content">
            <h2>Orders</h2>
            
            <div class="card">
                <div style="margin-bottom:15px;">
                    <input type="date" id="orderDateFilter" style="padding:8px;border:1px solid #ddd;border-radius:4px;">
                    <button class="btn btn-outline" onclick="loadOrders()">Filter</button>
                </div>
                <table>
                    <thead><tr><th>Order #</th><th>Customer</th><th>Date</th><th>Cashier</th><th>Total</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody id="ordersTable"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Order Detail Modal -->
    <div id="orderDetailModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
        <div style="background:white;padding:25px;border-radius:8px;width:500px;max-height:80vh;overflow:auto;">
            <h3 style="margin-bottom:15px;">Order Details</h3>
            <div id="orderDetails"></div>
            <div style="margin-top:15px;">
                <button class="btn btn-outline" onclick="document.getElementById('orderDetailModal').style.display='none'">Close</button>
            </div>
        </div>
    </div>

    <script>
        async function loadOrders() {
            const date = document.getElementById('orderDateFilter').value;
            let url = '../api/orders.php?action=list';
            if (date) url += '&date=' + date;
            
            const res = await fetch(url);
            const data = await res.json();
            const tbody = document.getElementById('ordersTable');
            
            if (!data.orders || data.orders.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#888;">No orders found</td></tr>';
                return;
            }
            
            tbody.innerHTML = data.orders.map(o => `
                <tr>
                    <td>${o.order_number}</td>
                    <td>${o.customer_name || '—'}</td>
                    <td>${new Date(o.created_at).toLocaleString()}</td>
                    <td>${o.full_name}</td>
                    <td>₱${parseFloat(o.total_amount).toFixed(2)}</td>
                    <td><span class="badge ${o.status === 'completed' ? 'badge-success' : 'badge-error'}">${o.status}</span></td>
                    <td><button class="btn btn-sm btn-outline" onclick="viewOrder(${o.id})">View</button></td>
                </tr>
            `).join('');
        }

        async function viewOrder(id) {
            const res = await fetch(`../api/orders.php?action=get&id=${id}`);
            const data = await res.json();
            
            if (data.success) {
                const o = data.order;
                let itemsHtml = o.items.map(i => `<div style="display:flex;justify-content:space-between;padding:5px 0;border-bottom:1px solid #eee;"><span>${i.product_name}${i.variant_value ? ' (' + i.variant_value + ')' : ''} x${i.quantity}</span><span>₱${parseFloat(i.subtotal).toFixed(2)}</span></div>`).join('');
                
                document.getElementById('orderDetails').innerHTML = `
                    <p><strong>Order:</strong> ${o.order_number}</p>
                    <p><strong>Customer:</strong> ${o.customer_name || '—'}</p>
                    <p><strong>Date:</strong> ${new Date(o.created_at).toLocaleString()}</p>
                    <p><strong>Cashier:</strong> ${o.full_name}</p>
                    <p><strong>Status:</strong> ${o.status}</p>
                    <div style="margin:15px 0;border-top:1px solid #eee;padding-top:15px;">
                        <strong>Items:</strong>
                        ${itemsHtml}
                    </div>
                    <div style="display:flex;justify-content:space-between;font-weight:700;"><span>Total</span><span>₱${parseFloat(o.total_amount).toFixed(2)}</span></div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;color:#666;"><span>Cash</span><span>₱${parseFloat(o.cash_tendered).toFixed(2)}</span></div>
                    <div style="display:flex;justify-content:space-between;font-size:13px;color:#22c55e;"><span>Change</span><span>₱${parseFloat(o.change_given).toFixed(2)}</span></div>
                `;
                document.getElementById('orderDetailModal').style.display = 'flex';
            }
        }

        document.getElementById('orderDateFilter').valueAsDate = new Date();
        loadOrders();
    </script>
</body>
</html>