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
    <title>Users - CanteenPOS Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
</head>
<body>
        <div class="header">
            <div class="logo">
                <div class="logo-icon">👥</div>
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
            <a class="sidebar-item active" href="users.php">👥 Users</a>
            <a class="sidebar-item" href="reports.php">📈 Reports</a>
            <a class="sidebar-item" href="chat.php">💬 Chat</a>
        </div>
        
        <div class="content">
            <h2>Users <button class="btn btn-primary btn-sm" onclick="showModal()">+ Add User</button></h2>
            
            <div class="card">
                <table>
                    <thead><tr><th>Username</th><th>Full Name</th><th>Role</th><th>Status</th><th>Created</th><th>Actions</th></tr></thead>
                    <tbody id="usersTable"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="userModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;">
        <div style="background:white;padding:25px;border-radius:8px;width:400px;">
            <h3 id="modalTitle" style="margin-bottom:15px;">Add User</h3>
            <input type="hidden" id="userId">
            <div class="form-group">
                <label>Username</label>
                <input type="text" id="userUsername">
            </div>
            <div class="form-group">
                <label>Full Name</label>
                <input type="text" id="userFullName">
            </div>
            <div class="form-group">
                <label>Role</label>
                <select id="userRole">
                    <option value="cashier">Cashier</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" id="userPassword">
            </div>
            <div style="display:flex;gap:10px;">
                <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveUser()">Save</button>
            </div>
        </div>
    </div>

    <script>
        let currentUserId = <?php echo $_SESSION['user_id']; ?>;
        
        async function loadUsers() {
            const res = await fetch('../api/users.php?action=list');
            const data = await res.json();
            const tbody = document.getElementById('usersTable');
            
            if (!data.users || data.users.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#888;">No users found</td></tr>';
                return;
            }
            
            tbody.innerHTML = data.users.map(u => `
                <tr>
                    <td>${u.username}</td>
                    <td>${u.full_name}</td>
                    <td><span class="badge ${u.role === 'admin' ? 'badge-primary' : ''}">${u.role}</span></td>
                    <td><span class="badge ${u.is_active ? 'badge-success' : 'badge-error'}">${u.is_active ? 'Active' : 'Inactive'}</span></td>
                    <td>${new Date(u.created_at).toLocaleDateString()}</td>
                    <td>
                        ${u.id != currentUserId ? `<button class="btn btn-sm btn-outline" onclick="editUser(${u.id})">Edit</button>` : ''}
                    </td>
                </tr>
            `).join('');
        }

        function showModal(u = null) {
            document.getElementById('userModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = u ? 'Edit User' : 'Add User';
            
            if (u) {
                document.getElementById('userId').value = u.id;
                document.getElementById('userUsername').value = u.username;
                document.getElementById('userFullName').value = u.full_name;
                document.getElementById('userRole').value = u.role;
                document.getElementById('userPassword').value = '';
            } else {
                document.getElementById('userId').value = '';
                document.getElementById('userUsername').value = '';
                document.getElementById('userFullName').value = '';
                document.getElementById('userRole').value = 'cashier';
                document.getElementById('userPassword').value = '';
            }
        }

        function closeModal() {
            document.getElementById('userModal').style.display = 'none';
        }

        async function saveUser() {
            const id = document.getElementById('userId').value;
            const username = document.getElementById('userUsername').value;
            const full_name = document.getElementById('userFullName').value;
            const role = document.getElementById('userRole').value;
            const password = document.getElementById('userPassword').value;
            
            if (!username || !full_name || (!id && !password)) {
                alert('Please fill all required fields');
                return;
            }
            
            const res = await fetch(`../api/users.php?action=${id ? 'update' : 'add'}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${id}&username=${encodeURIComponent(username)}&full_name=${encodeURIComponent(full_name)}&role=${role}&password=${encodeURIComponent(password)}`
            });
            const data = await res.json();
            
            if (data.success) {
                closeModal();
                loadUsers();
            } else {
                alert(data.message);
            }
        }

        function editUser(id) {
            fetch('../api/users.php?action=list')
                .then(r => r.json())
                .then(d => {
                    const u = d.users.find(x => x.id === id);
                    if (u) showModal(u);
                });
        }

        loadUsers();
    </script>
</body>
</html>