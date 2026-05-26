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
    <title>Tickets - CanteenPOS Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .edit-form { margin-top: 16px; }
        .edit-item-row { display: flex; gap: 10px; align-items: center; padding: 10px; background: #f8fafc; border-radius: 10px; margin-bottom: 8px; flex-wrap: wrap; }
        .edit-item-row select, .edit-item-row input { padding: 8px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; }
        .edit-item-row input[type="number"] { width: 70px; }
        .edit-history-item { padding: 10px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
        .edit-history-item:last-child { border-bottom: none; }
        .highlight-row { background: #fffbeb !important; }
        .highlight-row td { color: #92400e !important; }
    </style>
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="../assets/icons/icon.svg">
</head>
<body>
    <div class="header">
        <div class="logo">
            <div class="logo-icon">🎫</div>
            <div><h1>CanteenPOS</h1><span>Admin</span></div>
        </div>
        <div class="header-right">
            <a href="dashboard.php"><button class="btn btn-outline">Dashboard</button></a>
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
            <a class="sidebar-item active" href="tickets.php">🎫 Tickets</a>
            <a class="sidebar-item" href="users.php">👥 Users</a>
            <a class="sidebar-item" href="reports.php">📈 Reports</a>
            <a class="sidebar-item" href="chat.php">💬 Chat</a>
        </div>

        <div class="content">
            <h2>🎫 Tickets <span style="font-size:14px;font-weight:400;color:#64748b;">— Edit completed orders</span></h2>

            <div class="card">
                <div style="display:flex;gap:12px;margin-bottom:16px;flex-wrap:wrap;">
                    <input type="text" id="searchInput" class="search-input" placeholder="Search by order number or cashier..." style="max-width:300px;">
                    <input type="date" id="dateFilter" style="height:46px;padding:0 16px;border-radius:12px;border:1.5px solid #e2e8f0;font-size:14px;">
                    <button class="btn btn-primary" onclick="loadTickets()">Search</button>
                </div>
                <table>
                    <thead><tr><th>Order #</th><th>Customer</th><th>Date</th><th>Cashier</th><th>Total</th><th>Edited</th><th>Actions</th></tr></thead>
                    <tbody id="ticketsTable"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Edit Ticket Modal -->
    <div id="editTicketModal" class="modal">
        <div class="modal-content" style="width:650px;">
            <h3>✏️ Edit Order <span id="editOrderNumber" style="font-weight:400;color:#64748b;"></span></h3>
            <input type="hidden" id="editOrderId">
            <div id="editOrderInfo" style="margin-bottom:16px;padding:12px;background:#f8fafc;border-radius:10px;font-size:13px;color:#64748b;"></div>
            <div id="editItemsContainer" class="edit-form"></div>
            <div style="margin-top:12px;">
                <button class="btn btn-sm btn-outline" onclick="addEditItemRow()">+ Add Item</button>
            </div>
            <div style="margin-top:16px;padding:14px;background:#f8fafc;border-radius:10px;display:flex;justify-content:space-between;font-weight:700;font-size:18px;">
                <span>New Total</span>
                <span id="editNewTotal" style="color:#2563eb;">₱0.00</span>
            </div>
            <div class="form-group" style="margin-top:16px;">
                <label>Reason for edit <span style="color:#ef4444;">*</span></label>
                <textarea id="editReason" placeholder="Why is this order being edited? (e.g. Cashier entered wrong amount)" style="min-height:60px;"></textarea>
            </div>
            <div id="editHistory" style="margin-top:12px;padding:12px;background:#fffbeb;border-radius:10px;display:none;">
                <strong style="font-size:13px;">📜 Edit History</strong>
                <div id="editHistoryList"></div>
            </div>
            <div class="modal-actions">
                <button class="btn btn-outline" onclick="closeEditModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveEdit()">💾 Save Changes</button>
            </div>
        </div>
    </div>

    <script>
        let currentEditItems = [];
        let allProducts = [];

        async function loadTickets() {
            const search = document.getElementById('searchInput').value;
            const date = document.getElementById('dateFilter').value;
            let url = '../api/tickets.php?action=list';
            if (search) url += '&search=' + encodeURIComponent(search);
            if (date) url += '&date=' + date;

            const res = await fetch(url);
            const data = await res.json();
            const tbody = document.getElementById('ticketsTable');

            if (!data.orders || data.orders.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" class="empty-state"><p>No completed orders found</p></td></tr>';
                return;
            }

            tbody.innerHTML = data.orders.map(o => `
                <tr class="${o.edit_count > 0 ? 'highlight-row' : ''}">
                    <td><strong>${o.order_number}</strong></td>
                    <td>${o.customer_name || '—'}</td>
                    <td>${new Date(o.created_at).toLocaleString()}</td>
                    <td>${o.full_name}</td>
                    <td>₱${parseFloat(o.total_amount).toFixed(2)}</td>
                    <td>${o.edit_count > 0 ? '<span class="badge badge-warning">Edited ('+o.edit_count+')</span>' : '<span class="text-muted">—</span>'}</td>
                    <td>
                        <button class="btn btn-sm btn-primary" onclick="openEditTicket(${o.id})">Edit</button>
                        <button class="btn btn-sm btn-outline" onclick="viewTicket(${o.id})">View</button>
                    </td>
                </tr>
            `).join('');
        }

        async function loadProductsForEdit() {
            const res = await fetch('../api/products.php?action=pos&include_variants=1');
            const data = await res.json();
            allProducts = data.products || [];
        }

        async function openEditTicket(id) {
            await loadProductsForEdit();

            const res = await fetch('../api/tickets.php?action=get&id=' + id);
            const data = await res.json();
            if (!data.success) { alert(data.message); return; }

            const order = data.order;
            document.getElementById('editOrderId').value = order.id;
            document.getElementById('editOrderNumber').textContent = order.order_number;
            document.getElementById('editOrderInfo').innerHTML = `
                <strong>Customer:</strong> ${order.customer_name || '—'} &nbsp;|&nbsp; 
                <strong>Cashier:</strong> ${order.full_name} &nbsp;|&nbsp; 
                <strong>Date:</strong> ${new Date(order.created_at).toLocaleString()} &nbsp;|&nbsp; 
                <strong>Status:</strong> ${order.status}
            `;
            document.getElementById('editReason').value = '';

            if (order.edit_history && order.edit_history.length > 0) {
                document.getElementById('editHistory').style.display = 'block';
                document.getElementById('editHistoryList').innerHTML = order.edit_history.map(h => `
                    <div class="edit-history-item">
                        <strong>${h.edited_by_name}</strong> — ${new Date(h.created_at).toLocaleString()}
                        <div style="color:#64748b;margin-top:4px;">${h.reason || 'No reason given'}</div>
                    </div>
                `).join('');
            } else {
                document.getElementById('editHistory').style.display = 'none';
            }

            currentEditItems = order.items.map(i => ({
                order_item_id: i.id,
                product_id: i.product_id,
                variant_id: i.variant_id,
                quantity: i.quantity,
                unit_price: parseFloat(i.unit_price),
                product_name: i.product_name,
                variant_value: i.variant_value
            }));

            renderEditItems();
            document.getElementById('editTicketModal').classList.add('active');
        }

        function renderEditItems() {
            const container = document.getElementById('editItemsContainer');
            container.innerHTML = currentEditItems.map((item, idx) => {
                let productOptions = '<option value="">Select product</option>';
                allProducts.forEach(p => {
                    const sel = p.id === item.product_id ? 'selected' : '';
                    productOptions += `<option value="${p.id}" ${sel}>${p.name}</option>`;
                });

                let variantHtml = '';
                const prod = allProducts.find(p => p.id === item.product_id);
                if (prod && prod.variants && prod.variants.length > 0) {
                    variantHtml = `<select onchange="updateEditItemVariant(${idx}, this.value)" style="min-width:100px;">
                        <option value="">No variant</option>
                        ${prod.variants.map(v => {
                            const s = v.id === item.variant_id ? 'selected' : '';
                            const price = parseFloat(v.effective_price).toFixed(2);
                            return `<option value="${v.id}" data-price="${price}" ${s}>${v.variant_value} (₱${price})</option>`;
                        }).join('')}
                    </select>`;
                }

                return `<div class="edit-item-row">
                    <select onchange="updateEditItemProduct(${idx}, this.value)" style="flex:1;min-width:120px;">
                        ${productOptions}
                    </select>
                    ${variantHtml}
                    <input type="number" value="${item.unit_price}" step="0.25" onchange="updateEditItemPrice(${idx}, this.value)" style="width:80px;" title="Unit Price">
                    <span style="color:#64748b;font-size:12px;">×</span>
                    <input type="number" value="${item.quantity}" min="1" onchange="updateEditItemQty(${idx}, this.value)" style="width:60px;" title="Quantity">
                    <span style="font-weight:600;color:#2563eb;min-width:60px;">₱${(item.unit_price * item.quantity).toFixed(2)}</span>
                    <button class="btn btn-sm btn-outline" style="color:#ef4444;" onclick="removeEditItem(${idx})">✕</button>
                </div>`;
            }).join('');

            updateEditTotal();
        }

        function updateEditItemProduct(idx, productId) {
            const prod = allProducts.find(p => p.id == productId);
            if (prod) {
                currentEditItems[idx].product_id = parseInt(productId);
                currentEditItems[idx].product_name = prod.name;
                currentEditItems[idx].unit_price = parseFloat(prod.base_price);
                currentEditItems[idx].variant_id = null;
                currentEditItems[idx].variant_value = null;
                renderEditItems();
            }
        }

        function updateEditItemVariant(idx, variantId) {
            const item = currentEditItems[idx];
            const prod = allProducts.find(p => p.id === item.product_id);
            if (prod && prod.variants) {
                const variant = prod.variants.find(v => v.id == variantId);
                if (variant) {
                    item.variant_id = variant.id;
                    item.variant_value = variant.variant_value;
                    item.unit_price = parseFloat(variant.effective_price);
                    renderEditItems();
                }
            }
        }

        function updateEditItemPrice(idx, price) {
            currentEditItems[idx].unit_price = parseFloat(price) || 0;
            updateEditTotal();
        }

        function updateEditItemQty(idx, qty) {
            currentEditItems[idx].quantity = parseInt(qty) || 1;
            updateEditTotal();
        }

        function removeEditItem(idx) {
            currentEditItems.splice(idx, 1);
            renderEditItems();
        }

        function addEditItemRow() {
            currentEditItems.push({
                order_item_id: null,
                product_id: allProducts[0]?.id || 0,
                variant_id: null,
                quantity: 1,
                unit_price: allProducts[0] ? parseFloat(allProducts[0].base_price) : 0,
                product_name: allProducts[0]?.name || 'New Item',
                variant_value: null
            });
            renderEditItems();
        }

        function updateEditTotal() {
            const total = currentEditItems.reduce((s, i) => s + (i.unit_price * i.quantity), 0);
            document.getElementById('editNewTotal').textContent = '₱' + total.toFixed(2);
        }

        async function saveEdit() {
            const orderId = document.getElementById('editOrderId').value;
            const reason = document.getElementById('editReason').value.trim();
            if (!reason) { alert('Please provide a reason for the edit.'); return; }
            if (currentEditItems.length === 0) { alert('Order must have at least one item.'); return; }

            const items = currentEditItems.map(i => ({
                product_id: i.product_id,
                variant_id: i.variant_id,
                quantity: i.quantity,
                unit_price: i.unit_price,
                subtotal: i.unit_price * i.quantity
            }));

            const res = await fetch('../api/tickets.php?action=edit', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ order_id: parseInt(orderId), items, reason })
            });
            const data = await res.json();

            if (data.success) {
                alert('✅ Order updated successfully! New total: ₱' + parseFloat(data.new_total).toFixed(2));
                closeEditModal();
                loadTickets();
            } else {
                alert('Error: ' + data.message);
            }
        }

        function closeEditModal() {
            document.getElementById('editTicketModal').classList.remove('active');
        }

        function viewTicket(id) {
            window.open('orders.php?view=' + id, '_blank');
        }

        document.getElementById('dateFilter').valueAsDate = new Date();
        document.getElementById('searchInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') loadTickets();
        });

        // Auto-open edit modal if ticket ID is in URL (e.g., from notification link)
        const urlParams = new URLSearchParams(window.location.search);
        const ticketId = urlParams.get('id');
        if (ticketId) {
            // Wait for products to load then open edit
            (async function() {
                await loadProductsForEdit();
                await openEditTicket(parseInt(ticketId));
            })();
        }

        loadTickets();
    </script>
    <script>if('serviceWorker'in navigator){navigator.serviceWorker.register('../sw.js');}</script>
</body>
</html>
