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
    <title>Categories - CanteenPOS Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
        <div class="header">
            <div class="logo">
                <div class="logo-icon">🏷️</div>
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
            <a class="sidebar-item active" href="categories.php">🏷️ Categories</a>
            <a class="sidebar-item" href="orders.php">📋 Orders</a>
            <a class="sidebar-item" href="tickets.php">🎫 Tickets</a>
            <a class="sidebar-item" href="users.php">👥 Users</a>
            <a class="sidebar-item" href="reports.php">📈 Reports</a>
            <a class="sidebar-item" href="chat.php">💬 Chat</a>
        </div>
        
        <div class="content">
            <h2>Categories <button class="btn btn-primary btn-sm" onclick="showModal()">+ Add Category</button></h2>
            
            <div class="grid-3" id="categoriesGrid"></div>
        </div>
    </div>

    <!-- Modal -->
    <div id="categoryModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
        <div style="background:white;padding:25px;border-radius:8px;width:400px;">
            <h3 id="modalTitle" style="margin-bottom:15px;">Add Category</h3>
            <input type="hidden" id="categoryId">
            <div class="form-group">
                <label>Name</label>
                <input type="text" id="categoryName">
            </div>
            <div class="form-group">
                <label>Description</label>
                <textarea id="categoryDesc" rows="3"></textarea>
            </div>
            <div style="display:flex;gap:10px;">
                <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveCategory()">Save</button>
            </div>
        </div>
    </div>

    <script>
        async function loadCategories() {
            const res = await fetch('../api/categories.php?action=list');
            const data = await res.json();
            const grid = document.getElementById('categoriesGrid');
            
            if (!data.categories || data.categories.length === 0) {
                grid.innerHTML = '<p style="grid-column:1/-1;text-align:center;color:#888;">No categories found</p>';
                return;
            }
            
            grid.innerHTML = data.categories.map(c => `
                <div class="card">
                    <h3 style="margin-bottom:10px;">${c.name}</h3>
                    <p style="color:#666;font-size:13px;margin-bottom:15px;">${c.description || 'No description'}</p>
                    <button class="btn btn-sm btn-outline" onclick="editCategory(${c.id})">Edit</button>
                </div>
            `).join('');
        }

        function showModal(c = null) {
            document.getElementById('categoryModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = c ? 'Edit Category' : 'Add Category';
            
            if (c) {
                document.getElementById('categoryId').value = c.id;
                document.getElementById('categoryName').value = c.name;
                document.getElementById('categoryDesc').value = c.description || '';
            } else {
                document.getElementById('categoryId').value = '';
                document.getElementById('categoryName').value = '';
                document.getElementById('categoryDesc').value = '';
            }
        }

        function closeModal() {
            document.getElementById('categoryModal').style.display = 'none';
        }

        async function saveCategory() {
            const id = document.getElementById('categoryId').value;
            const name = document.getElementById('categoryName').value;
            const description = document.getElementById('categoryDesc').value;
            
            if (!name) {
                alert('Please enter a name');
                return;
            }
            
            const res = await fetch(`../api/categories.php?action=${id ? 'update' : 'add'}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}&name=${encodeURIComponent(name)}&description=${encodeURIComponent(description)}`
            });
            const data = await res.json();
            
            if (data.success) {
                closeModal();
                loadCategories();
            } else {
                alert(data.message);
            }
        }

        function editCategory(id) {
            fetch('../api/categories.php?action=list')
                .then(r => r.json())
                .then(d => {
                    const c = d.categories.find(x => x.id === id);
                    if (c) showModal(c);
                });
        }

        loadCategories();
    </script>
</body>
</html>