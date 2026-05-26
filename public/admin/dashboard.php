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
    <title>Dashboard - CanteenPOS Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .low-stock-banner { background: #fef2f2; border: 1.5px solid #fecaca; border-radius: 12px; padding: 16px 20px; margin-bottom: 20px; display: flex; align-items: center; gap: 12px; }
        .low-stock-banner .icon { font-size: 24px; }
        .low-stock-banner .text { flex: 1; }
        .low-stock-banner .text strong { color: #ef4444; font-size: 16px; }
        .low-stock-banner .text p { color: #64748b; font-size: 13px; margin-top: 2px; }

        .stock-table-wrap { max-height: 400px; overflow-y: auto; border-radius: 10px; border: 1px solid #e2e8f0; }
        .stock-table-wrap table { margin: 0; }
        .stock-table-wrap thead th { position: sticky; top: 0; z-index: 2; }
        .low-stock { background: #fef2f2 !important; }
        .low-stock td { color: #ef4444 !important; font-weight: 600; }
        .stock-bar { display: inline-flex; align-items: center; gap: 8px; }
        .stock-bar-fill { height: 8px; border-radius: 4px; background: #e2e8f0; width: 100px; overflow: hidden; display: inline-block; vertical-align: middle; }
        .stock-bar-fill span { display: block; height: 100%; border-radius: 4px; transition: width 0.3s; }

        .cat-filter-bar { display: flex; gap: 12px; align-items: center; margin-bottom: 20px; flex-wrap: wrap; }
        .cat-filter-bar select { height: 42px; padding: 0 14px; border-radius: 10px; border: 1.5px solid #e2e8f0; background: white; font-size: 14px; outline: none; min-width: 180px; }
        .cat-filter-bar select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }

        .notif-badge { position: relative; }
        .notif-dot { position: absolute; top: -2px; right: -2px; width: 10px; height: 10px; background: #ef4444; border-radius: 50%; border: 2px solid white; display: none; }
        .notif-bell { position: relative; cursor: pointer; font-size: 22px; padding: 6px; border-radius: 8px; transition: background 0.15s; }
        .notif-bell:hover { background: #f1f5f9; }
        .notif-bell .count { position: absolute; top: -2px; right: -2px; background: #ef4444; color: white; font-size: 10px; font-weight: 700; border-radius: 50%; width: 18px; height: 18px; display: flex; align-items: center; justify-content: center; border: 2px solid white; display: none; }
        .notif-dropdown { position: absolute; top: 100%; right: 0; width: 360px; background: white; border-radius: 12px; box-shadow: 0 8px 30px rgba(0,0,0,0.15); border: 1px solid #e2e8f0; display: none; z-index: 1000; max-height: 400px; overflow: auto; }
        .notif-dropdown.active { display: block; }
        .notif-item { display: block; padding: 14px 16px; border-bottom: 1px solid #f1f5f9; text-decoration: none; color: #0f172a; transition: background 0.1s; }
        .notif-item:hover { background: #f8faff; }
        .notif-item:last-child { border-bottom: none; }
        .notif-item .msg { font-size: 13px; }
        .notif-item .time { font-size: 11px; color: #94a3b8; margin-top: 4px; }
        .notif-item.unread { background: #eff6ff; }
        .notif-empty { padding: 30px; text-align: center; color: #94a3b8; font-size: 13px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">
            <div class="logo-icon">📊</div>
            <div><h1>CanteenPOS</h1><span>Admin Dashboard</span></div>
        </div>
        <div class="header-right" style="position:relative;">
            <a href="../pos.php" style="display:none;"><button class="btn btn-outline">POS</button></a>
            <div class="notif-bell" onclick="toggleNotifDropdown(event)">
                🔔
                <span id="notifCount" class="count">0</span>
                <div id="notifDropdown" class="notif-dropdown"></div>
            </div>
            <span style="font-size:14px;color:#475569;font-weight:500;"><?= htmlspecialchars($user['full_name']) ?></span>
            <a href="../api/logout.php"><button class="btn btn-outline">Logout</button></a>
        </div>
    </div>

    <div class="main">
        <div class="sidebar">
            <a class="sidebar-item active" href="dashboard.php">📊 Dashboard</a>
            <a class="sidebar-item" href="products.php">📦 Products</a>
            <a class="sidebar-item" href="categories.php">🏷️ Categories</a>
            <a class="sidebar-item" href="orders.php">📋 Orders</a>
            <a class="sidebar-item" href="tickets.php" class="notif-badge">🎫 Tickets <span id="ticketNotifDot" class="notif-dot"></span></a>
            <a class="sidebar-item" href="users.php">👥 Users</a>
            <a class="sidebar-item" href="reports.php">📈 Reports</a>
            <a class="sidebar-item" href="chat.php">💬 Chat</a>
        </div>

        <div class="content">
            <h2>📊 Dashboard</h2>

            <div id="lowStockBanner" class="low-stock-banner" style="display:none;">
                <div class="icon">⚠️</div>
                <div class="text">
                    <strong id="lowStockCount">0</strong> item(s) running low on stock
                    <p>Stock threshold is set at 10 or below. Restock soon!</p>
                </div>
                <button class="btn btn-sm btn-danger" onclick="document.getElementById('stockSection').scrollIntoView({behavior:'smooth'})">View Stock</button>
            </div>

            <div class="cat-filter-bar">
                <span style="font-weight:600;color:#475569;font-size:14px;">Sales by Category:</span>
                <select id="categorySalesFilter">
                    <option value="">All Categories</option>
                </select>
            </div>

            <div class="stats-grid">
                <div class="stat-card"><div class="label">Today's Sales</div><div class="value" id="todaySales">₱0.00</div></div>
                <div class="stat-card"><div class="label">Yesterday</div><div class="value" id="yesterdaySales">₱0.00</div></div>
                <div class="stat-card"><div class="label">This Week</div><div class="value" id="weekSales">₱0.00</div></div>
                <div class="stat-card"><div class="label">Total Products</div><div class="value" id="totalProducts">0</div></div>
            </div>

            <div class="grid-3">
                <div class="card">
                    <h3>🔥 Top Products Today</h3>
                    <div id="topProducts"></div>
                </div>
                <div class="card">
                    <h3>🕐 Recent Orders</h3>
                    <div id="recentOrders"></div>
                </div>
            </div>

            <div class="card" id="stockSection">
                <h3>📦 Stock Overview <span id="lowStockBadge" style="font-size:12px;font-weight:400;color:#64748b;"></span></h3>
                <div class="stock-table-wrap">
                    <table>
                        <thead><tr><th>Product</th><th>Category</th><th>Variant</th><th>Stock</th></tr></thead>
                        <tbody id="stockTableBody"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentUserId = <?php echo $_SESSION['user_id']; ?>;
        let ADMIN_USER_ID = null;

        async function loadCategories() {
            const res = await fetch('../api/categories.php?action=list');
            const data = await res.json();
            const sel = document.getElementById('categorySalesFilter');
            data.categories.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                sel.appendChild(opt);
            });
        }

        async function loadDashboard() {
            const cat = document.getElementById('categorySalesFilter').value;
            let url = '../api/orders.php?action=daily';
            if (cat) url += '&category_id=' + cat;

            const res = await fetch(url);
            const data = await res.json();
            if (data.success) {
                document.getElementById('todaySales').textContent = '₱' + parseFloat(data.today.total_sales).toFixed(2);
                document.getElementById('yesterdaySales').textContent = '₱' + parseFloat(data.yesterday.total_sales).toFixed(2);
                document.getElementById('weekSales').textContent = '₱' + parseFloat(data.week.total_sales).toFixed(2);

                let html = '';
                if (data.top_products && data.top_products.length > 0) {
                    data.top_products.forEach((p, i) => {
                        html += `<div style="padding:10px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;">
                            <span><span style="display:inline-block;width:24px;height:24px;background:${i===0?'#2563eb':'#e2e8f0'};color:${i===0?'white':'#475569'};border-radius:6px;text-align:center;line-height:24px;font-size:12px;font-weight:700;margin-right:10px;">${i+1}</span>${p.name}</span>
                            <span style="font-weight:600;">${p.total_sold} sold (₱${parseFloat(p.total_revenue).toFixed(2)})</span>
                        </div>`;
                    });
                } else {
                    html = '<div class="empty-state"><p>No sales today</p></div>';
                }
                document.getElementById('topProducts').innerHTML = html;
            }

            const pres = await fetch('../api/products.php?action=list');
            const pdata = await pres.json();
            if (pdata.success) {
                document.getElementById('totalProducts').textContent = pdata.products.length;
            }

            const orres = await fetch('../api/orders.php?action=list&limit=5');
            const ordata = await orres.json();
            let ohtml = '';
            if (ordata.success && ordata.orders && ordata.orders.length > 0) {
                ordata.orders.forEach(o => {
                    ohtml += `<div style="padding:10px 0;border-bottom:1px solid #f1f5f9;display:flex;justify-content:space-between;align-items:center;">
                        <span style="font-weight:500;">${o.order_number}</span>
                        <span style="font-weight:600;color:#2563eb;">₱${parseFloat(o.total_amount).toFixed(2)}</span>
                    </div>`;
                });
            } else {
                ohtml = '<div class="empty-state"><p>No orders yet</p></div>';
            }
            document.getElementById('recentOrders').innerHTML = ohtml;
        }

        async function loadStocks() {
            const res = await fetch('../api/products.php?action=stocks');
            const data = await res.json();
            if (!data.success) return;

            const summary = data.summary;
            let tbody = document.getElementById('stockTableBody');
            let html = '';
            let lowCount = 0;

            data.products.forEach(p => {
                if (p.variants.length === 0) {
                    const hasStock = p.stock !== null;
                    const isLow = hasStock && p.stock <= 10;
                    if (isLow) lowCount++;
                    const pct = hasStock ? Math.min(p.stock / 50 * 100, 100) : 0;
                    const barColor = isLow ? '#ef4444' : (hasStock && p.stock <= 20 ? '#eab308' : '#22c55e');
                    html += `<tr class="${isLow ? 'low-stock' : ''}">
                        <td><strong>${p.name}</strong></td>
                        <td>${p.category_name || '-'}</td>
                        <td class="text-muted">—</td>
                        <td>
                            ${hasStock ? `<div class="stock-bar">
                                <span style="font-weight:700;">${p.stock}</span>
                                <span class="stock-bar-fill"><span style="width:${pct}%;background:${barColor};"></span></span>
                                ${isLow ? '<span style="color:#ef4444;font-size:13px;">⚠️ Low</span>' : ''}
                            </div>` : '<span class="text-muted">Unlimited</span>'}
                        </td>
                    </tr>`;
                } else {
                    p.variants.forEach(v => {
                        const isLow = v.stock <= 10;
                        if (isLow) lowCount++;
                        const pct = Math.min(v.stock / 50 * 100, 100);
                        const barColor = isLow ? '#ef4444' : v.stock <= 20 ? '#eab308' : '#22c55e';
                        html += `<tr class="${isLow ? 'low-stock' : ''}">
                            <td><strong>${p.name}</strong></td>
                            <td>${p.category_name || '-'}</td>
                            <td>${v.variant_value} <span class="text-muted">(${v.variant_type})</span></td>
                            <td>
                                <div class="stock-bar">
                                    <span style="font-weight:700;">${v.stock}</span>
                                    <span class="stock-bar-fill"><span style="width:${pct}%;background:${barColor};"></span></span>
                                    ${isLow ? '<span style="color:#ef4444;font-size:13px;">⚠️ Low</span>' : ''}
                                </div>
                            </td>
                        </tr>`;
                    });
                }
            });

            tbody.innerHTML = html || '<tr><td colspan="4" class="empty-state"><p>No products found</p></td></tr>';

            document.getElementById('lowStockBadge').textContent = `— ${summary.total_variants} stock items, ${summary.low_stock_count} low`;

            if (summary.low_stock_count > 0) {
                document.getElementById('lowStockBanner').style.display = 'flex';
                document.getElementById('lowStockCount').textContent = summary.low_stock_count;
            }
        }

        async function checkNotifications() {
            try {
                const res = await fetch('../api/notifications.php?action=unread');
                const data = await res.json();
                if (data.success && data.count > 0) {
                    document.getElementById('ticketNotifDot').style.display = 'block';
                    document.getElementById('notifCount').style.display = 'flex';
                    document.getElementById('notifCount').textContent = data.count;
                } else {
                    document.getElementById('ticketNotifDot').style.display = 'none';
                    document.getElementById('notifCount').style.display = 'none';
                }
            } catch(e) {}
        }

        async function loadNotifList() {
            try {
                const res = await fetch('../api/notifications.php?action=list');
                const data = await res.json();
                const container = document.getElementById('notifDropdown');
                if (!data.success || !data.notifications || data.notifications.length === 0) {
                    container.innerHTML = '<div class="notif-empty">No notifications</div>';
                    return;
                }
                container.innerHTML = data.notifications.map(n => {
                    let link = n.link || '#';
                    // Fix old relative links that don't work from /admin/ pages
                    if (link.startsWith('admin/')) link = '../' + link;
                    return `<a href="${link}" class="notif-item ${n.is_read ? '' : 'unread'}" onclick="markNotifRead(${n.id})">
                        <div class="msg">${n.message}</div>
                        <div class="time">${new Date(n.created_at).toLocaleString()}</div>
                    </a>`;
                }).join('');
            } catch(e) {
                document.getElementById('notifDropdown').innerHTML = '<div class="notif-empty">Error loading</div>';
            }
        }

        async function markNotifRead(id) {
            await fetch('../api/notifications.php?action=mark_read', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id=' + id
            });
        }

        function toggleNotifDropdown(e) {
            e.stopPropagation();
            const dd = document.getElementById('notifDropdown');
            dd.classList.toggle('active');
            if (dd.classList.contains('active')) loadNotifList();
        }

        document.addEventListener('click', function() {
            document.getElementById('notifDropdown').classList.remove('active');
        });

        document.getElementById('categorySalesFilter').addEventListener('change', loadDashboard);

        loadCategories();
        loadDashboard();
        loadStocks();
        checkNotifications();
        setInterval(checkNotifications, 10000);
    </script>
</body>
</html>
