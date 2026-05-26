let currentTab = 'dashboard';
let currentPage = 1;
let currentUserId = null;

function setCurrentUserId(id) {
    currentUserId = id;
}

document.querySelectorAll('.sidebar-item').forEach(item => {
    item.addEventListener('click', () => {
        const tab = item.dataset.tab;
        switchTab(tab);
    });
});

function switchTab(tab) {
    currentTab = tab;
    document.querySelectorAll('.sidebar-item').forEach(i => i.classList.remove('active'));
    document.querySelector(`.sidebar-item[data-tab="${tab}"]`).classList.add('active');
    document.querySelectorAll('.tab-content').forEach(c => c.classList.add('hidden'));
    document.getElementById(`tab-${tab}`).classList.remove('hidden');
    
    loadTabData(tab);
}

function loadTabData(tab) {
    switch(tab) {
        case 'dashboard': loadDashboard(); break;
        case 'products': loadProducts(); break;
        case 'categories': loadCategories(); break;
        case 'variants': loadVariantTypes(); break;
        case 'orders': loadOrders(); break;
        case 'users': loadUsers(); break;
        case 'reports': initReports(); break;
        case 'chat': loadChat(); break;
    }
}

async function loadDashboard() {
    const res = await fetch('../api/orders.php?action=daily');
    const data = await res.json();
    
    if (data.success) {
        document.getElementById('todaySales').textContent = '₱' + parseFloat(data.today.total_sales).toFixed(2);
        document.getElementById('todayOrders').textContent = data.today.total_orders + ' orders';
        document.getElementById('yesterdaySales').textContent = '₱' + parseFloat(data.yesterday.total_sales).toFixed(2);
        document.getElementById('weekSales').textContent = '₱' + parseFloat(data.week.total_sales).toFixed(2);
        
        const productsRes = await fetch('../api/products.php?action=list');
        const productsData = await productsRes.json();
        document.getElementById('totalProducts').textContent = productsData.products.length;
        
        renderTopProducts(data.top_products);
        loadRecentOrders();
    }
}

function renderTopProducts(products) {
    const container = document.getElementById('topProductsList');
    if (!products || products.length === 0) {
        container.innerHTML = '<p class="text-center text-base-content/60">No sales today</p>';
        return;
    }
    container.innerHTML = products.map((p, i) => `
        <div class="flex items-center justify-between p-2 bg-base-200 rounded">
            <div class="flex items-center gap-3">
                <span class="w-6 h-6 bg-primary rounded-full flex items-center justify-center text-xs text-primary-content">${i+1}</span>
                <span class="font-medium">${p.name}</span>
            </div>
            <div class="text-right">
                <span class="font-bold">${p.total_sold}</span>
                <span class="text-xs text-base-content/60"> sold</span>
            </div>
        </div>
    `).join('');
}

async function loadRecentOrders() {
    const res = await fetch('../api/orders.php?action=list&limit=5');
    const data = await res.json();
    
    const container = document.getElementById('recentOrdersList');
    if (!data.orders || data.orders.length === 0) {
        container.innerHTML = '<p class="text-center text-base-content/60">No recent orders</p>';
        return;
    }
    container.innerHTML = data.orders.map(o => `
        <div class="flex items-center justify-between p-2 bg-base-200 rounded">
            <div>
                <span class="font-medium">${o.order_number}</span>
                <span class="text-xs text-base-content/60 block">${o.full_name}</span>
            </div>
            <div class="text-right">
                <span class="font-bold">₱${parseFloat(o.total_amount).toFixed(2)}</span>
                <span class="text-xs text-base-content/60 block">${new Date(o.created_at).toLocaleTimeString()}</span>
            </div>
        </div>
    `).join('');
}

async function loadProducts() {
    const res = await fetch('../api/products.php?action=list&include_variants=1');
    const data = await res.json();
    
    const categoryRes = await fetch('../api/categories.php?action=list');
    const categoryData = await categoryRes.json();
    
    const categorySelect = document.getElementById('productCategoryFilter');
    categorySelect.innerHTML = '<option value="">All Categories</option>' + 
        categoryData.categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
    
    const tbody = document.getElementById('productsTableBody');
    if (!data.products || data.products.length === 0) {
        tbody.innerHTML = '<tr><td colspan="7" class="text-center text-base-content/60">No products found</td></tr>';
        return;
    }
    
    tbody.innerHTML = data.products.map(p => {
        let stock = 0;
        let hasVariants = false;
        if (p.variants && p.variants.length > 0) {
            hasVariants = true;
            stock = p.variants.reduce((s, v) => s + parseInt(v.stock), 0);
        }
        
        return `
            <tr>
                <td>${p.image ? `<img src="${p.image}" class="w-12 h-12 object-cover rounded">` : '<i class="fas fa-box text-2xl text-base-content/30"></i>'}</td>
                <td>${p.name}</td>
                <td>${p.category_name || '-'}</td>
                <td>₱${parseFloat(p.base_price).toFixed(2)}</td>
                <td>${hasVariants ? stock + ' (variants)' : '-'}</td>
                <td><span class="badge ${p.is_active ? 'badge-success' : 'badge-error'}">${p.is_active ? 'Active' : 'Inactive'}</span></td>
                <td>
                    <button class="btn btn-xs btn-ghost" onclick="editProduct(${p.id})"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-xs btn-ghost text-error" onclick="deleteProduct(${p.id})"><i class="fas fa-trash"></i></button>
                    ${hasVariants ? `<button class="btn btn-xs btn-ghost" onclick="manageVariants(${p.id})"><i class="fas fa-list"></i></button>` : ''}
                </td>
            </tr>
        `;
    }).join('');
}

function showProductModal(product = null) {
    document.getElementById('productModalTitle').textContent = product ? 'Edit Product' : 'Add Product';
    document.getElementById('productForm').reset();
    document.getElementById('productId').value = product ? product.id : '';
    document.getElementById('productImageExisting').value = product ? product.image : '';
    
    if (product) {
        document.getElementById('productName').value = product.name;
        document.getElementById('productDescription').value = product.description || '';
        document.getElementById('productCategory').value = product.category_id || '';
        document.getElementById('productPrice').value = product.base_price;
    }
    
    loadCategoriesForSelect();
    document.getElementById('productModal').showModal();
}

async function loadCategoriesForSelect() {
    const res = await fetch('../api/categories.php?action=list');
    const data = await res.json();
    const select = document.getElementById('productCategory');
    select.innerHTML = '<option value="">Select category</option>' + 
        data.categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
}

document.getElementById('productForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const formData = new FormData();
    formData.append('name', document.getElementById('productName').value);
    formData.append('description', document.getElementById('productDescription').value);
    formData.append('category_id', document.getElementById('productCategory').value);
    formData.append('base_price', document.getElementById('productPrice').value);
    formData.append('existing_image', document.getElementById('productImageExisting').value);
    
    const id = document.getElementById('productId').value;
    if (id) formData.append('id', id);
    
    const imageFile = document.getElementById('productImage').files[0];
    if (imageFile) formData.append('image', imageFile);
    
    const action = id ? 'update' : 'add';
    const res = await fetch(`../api/products.php?action=${action}`, {
        method: 'POST',
        body: formData
    });
    
    const data = await res.json();
    if (data.success) {
        document.getElementById('productModal').close();
        loadProducts();
    } else {
        alert(data.message);
    }
});

async function editProduct(id) {
    const res = await fetch(`../api/products.php?action=get&id=${id}`);
    const data = await res.json();
    if (data.success) {
        showProductModal(data.product);
    }
}

async function deleteProduct(id) {
    if (confirm('Are you sure you want to delete this product?')) {
        const res = await fetch(`../api/products.php?action=delete&id=${id}`);
        const data = await res.json();
        if (data.success) loadProducts();
    }
}

async function manageVariants(productId) {
    const res = await fetch(`../api/products.php?action=get&id=${productId}`);
    const data = await res.json();
    if (data.success) {
        alert('Product: ' + data.product.name + '\nVariants management coming soon!');
    }
}

document.getElementById('productSearch').addEventListener('input', () => {
    const term = document.getElementById('productSearch').value.toLowerCase();
    document.querySelectorAll('#productsTableBody tr').forEach(row => {
        row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
    });
});

document.getElementById('productCategoryFilter').addEventListener('change', () => {
    const cat = document.getElementById('productCategoryFilter').value;
    document.querySelectorAll('#productsTableBody tr').forEach(row => {
        if (!cat) {
            row.style.display = '';
        } else {
            row.styleDisplay = row.children[2].textContent.includes(cat) ? '' : 'none';
        }
    });
});

async function loadCategories() {
    const res = await fetch('../api/categories.php?action=list');
    const data = await res.json();
    
    const grid = document.getElementById('categoriesGrid');
    if (!data.categories || data.categories.length === 0) {
        grid.innerHTML = '<p class="col-span-full text-center text-base-content/60">No categories found</p>';
        return;
    }
    
    grid.innerHTML = data.categories.map(c => `
        <div class="card bg-base-100 shadow">
            <div class="card-body">
                <h3 class="font-bold">${c.name}</h3>
                <p class="text-sm text-base-content/60">${c.description || 'No description'}</p>
                <div class="flex justify-end gap-2 mt-2">
                    <button class="btn btn-xs btn-ghost" onclick="editCategory(${c.id})"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-xs btn-ghost text-error" onclick="deleteCategory(${c.id})"><i class="fas fa-trash"></i></button>
                </div>
            </div>
        </div>
    `).join('');
}

function showCategoryModal(category = null) {
    document.getElementById('categoryModalTitle').textContent = category ? 'Edit Category' : 'Add Category';
    document.getElementById('categoryForm').reset();
    document.getElementById('categoryId').value = category ? category.id : '';
    
    if (category) {
        document.getElementById('categoryName').value = category.name;
        document.getElementById('categoryDescription').value = category.description || '';
    }
    
    document.getElementById('categoryModal').showModal();
}

document.getElementById('categoryForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const id = document.getElementById('categoryId').value;
    const name = document.getElementById('categoryName').value;
    const description = document.getElementById('categoryDescription').value;
    
    const res = await fetch(`../api/categories.php?action=${id ? 'update' : 'add'}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&name=${encodeURIComponent(name)}&description=${encodeURIComponent(description)}`
    });
    
    const data = await res.json();
    if (data.success) {
        document.getElementById('categoryModal').close();
        loadCategories();
    } else {
        alert(data.message);
    }
});

async function editCategory(id) {
    const res = await fetch('../api/categories.php?action=list');
    const data = await res.json();
    const category = data.categories.find(c => c.id === id);
    if (category) showCategoryModal(category);
}

async function deleteCategory(id) {
    if (confirm('Delete this category?')) {
        const res = await fetch(`../api/categories.php?action=delete&id=${id}`);
        const data = await res.json();
        if (data.success) loadCategories();
    }
}

async function loadVariantTypes() {
    const res = await fetch('../api/variants.php?action=types');
    const data = await res.json();
    
    const tbody = document.getElementById('variantTypesBody');
    if (!data.types || data.types.length === 0) {
        tbody.innerHTML = '<tr><td colspan="3" class="text-center text-base-content/60">No variant types found</td></tr>';
        return;
    }
    
    tbody.innerHTML = data.types.map(t => `
        <tr>
            <td class="font-medium">${t.name}</td>
            <td>${t.values.join(', ')}</td>
            <td>
                <button class="btn btn-xs btn-ghost" onclick="editVariantType(${t.id})"><i class="fas fa-edit"></i></button>
                <button class="btn btn-xs btn-ghost text-error" onclick="deleteVariantType(${t.id})"><i class="fas fa-trash"></i></button>
            </td>
        </tr>
    `).join('');
}

function showVariantTypeModal(type = null) {
    document.getElementById('variantTypeModalTitle').textContent = type ? 'Edit Variant Type' : 'Add Variant Type';
    document.getElementById('variantTypeForm').reset();
    document.getElementById('variantTypeId').value = type ? type.id : '';
    
    if (type) {
        document.getElementById('variantTypeName').value = type.name;
        document.getElementById('variantTypeValues').value = type.values.join(', ');
    }
    
    document.getElementById('variantTypeModal').showModal();
}

document.getElementById('variantTypeForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const id = document.getElementById('variantTypeId').value;
    const name = document.getElementById('variantTypeName').value;
    const values = document.getElementById('variantTypeValues').value.split(',').map(v => v.trim()).filter(v => v);
    
    const res = await fetch(`../api/variants.php?action=${id ? 'updateType' : 'addType'}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&name=${encodeURIComponent(name)}&values=${encodeURIComponent(JSON.stringify(values))}`
    });
    
    const data = await res.json();
    if (data.success) {
        document.getElementById('variantTypeModal').close();
        loadVariantTypes();
    } else {
        alert(data.message);
    }
});

async function editVariantType(id) {
    const res = await fetch('../api/variants.php?action=types');
    const data = await res.json();
    const type = data.types.find(t => t.id === id);
    if (type) showVariantTypeModal(type);
}

async function deleteVariantType(id) {
    if (confirm('Delete this variant type?')) {
        const res = await fetch(`../api/variants.php?action=deleteType&id=${id}`);
        const data = await res.json();
        if (data.success) loadVariantTypes();
    }
}

async function loadOrders(page = 1) {
    currentPage = page;
    const date = document.getElementById('orderDateFilter').value;
    const status = document.getElementById('orderStatusFilter').value;
    
    let url = `../api/orders.php?action=list&page=${page}&limit=20`;
    if (date) url += `&date=${date}`;
    if (status) url += `&status=${status}`;
    
    const res = await fetch(url);
    const data = await res.json();
    
    const tbody = document.getElementById('ordersTableBody');
    if (!data.orders || data.orders.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-base-content/60">No orders found</td></tr>';
        return;
    }
    
    tbody.innerHTML = data.orders.map(o => `
        <tr>
            <td class="font-medium">${o.order_number}</td>
            <td>${new Date(o.created_at).toLocaleString()}</td>
            <td>${o.full_name}</td>
            <td class="font-bold">₱${parseFloat(o.total_amount).toFixed(2)}</td>
            <td><span class="badge badge-${o.status === 'completed' ? 'success' : o.status === 'cancelled' ? 'error' : 'warning'}">${o.status}</span></td>
            <td>
                <button class="btn btn-xs btn-ghost" onclick="viewOrder(${o.id})"><i class="fas fa-eye"></i></button>
                ${o.status === 'completed' ? `<button class="btn btn-xs btn-ghost text-error" onclick="cancelOrder(${o.id})"><i class="fas fa-times"></i></button>` : ''}
            </td>
        </tr>
    `).join('');
    
    const pagination = document.getElementById('ordersPagination');
    if (data.pages > 1) {
        let html = '<div class="join">';
        for (let i = 1; i <= data.pages; i++) {
            html += `<button class="join-item btn btn-sm ${i === page ? 'btn-active' : ''}" onclick="loadOrders(${i})">${i}</button>`;
        }
        html += '</div>';
        pagination.innerHTML = html;
    } else {
        pagination.innerHTML = '';
    }
}

async function viewOrder(id) {
    const res = await fetch(`../api/orders.php?action=get&id=${id}`);
    const data = await res.json();
    
    if (data.success) {
        const o = data.order;
        document.getElementById('orderDetailContent').innerHTML = `
            <div class="space-y-2">
                <p><strong>Order:</strong> ${o.order_number}</p>
                <p><strong>Date:</strong> ${new Date(o.created_at).toLocaleString()}</p>
                <p><strong>Cashier:</strong> ${o.full_name}</p>
                <p><strong>Status:</strong> <span class="badge badge-${o.status === 'completed' ? 'success' : 'error'}">${o.status}</span></p>
                <div class="divider">Items</div>
                ${o.items.map(i => `
                    <div class="flex justify-between">
                        <span>${i.product_name}${i.variant_value ? ' (' + i.variant_value + ')' : ''} x${i.quantity}</span>
                        <span>₱${parseFloat(i.subtotal).toFixed(2)}</span>
                    </div>
                `).join('')}
                <div class="divider"></div>
                <div class="flex justify-between font-bold">
                    <span>Total</span>
                    <span>₱${parseFloat(o.total_amount).toFixed(2)}</span>
                </div>
                <div class="flex justify-between">
                    <span>Cash Tendered</span>
                    <span>₱${parseFloat(o.cash_tendered).toFixed(2)}</span>
                </div>
                <div class="flex justify-between text-success">
                    <span>Change</span>
                    <span>₱${parseFloat(o.change_given).toFixed(2)}</span>
                </div>
            </div>
        `;
        document.getElementById('orderDetailModal').showModal();
    }
}

async function cancelOrder(id) {
    if (confirm('Cancel this order? Stock will be restored.')) {
        const res = await fetch(`../api/orders.php?action=cancel`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}`
        });
        const data = await res.json();
        if (data.success) loadOrders();
    }
}

async function loadUsers() {
    const res = await fetch('../api/users.php?action=list');
    const data = await res.json();
    
    const tbody = document.getElementById('usersTableBody');
    if (!data.users || data.users.length === 0) {
        tbody.innerHTML = '<tr><td colspan="6" class="text-center text-base-content/60">No users found</td></tr>';
        return;
    }
    
    tbody.innerHTML = data.users.map(u => `
        <tr>
            <td class="font-medium">${u.username}</td>
            <td>${u.full_name}</td>
            <td><span class="badge ${u.role === 'admin' ? 'badge-primary' : 'badge-secondary'}">${u.role}</span></td>
            <td><span class="badge ${u.is_active ? 'badge-success' : 'badge-error'}">${u.is_active ? 'Active' : 'Inactive'}</span></td>
            <td>${new Date(u.created_at).toLocaleDateString()}</td>
            <td>
                <button class="btn btn-xs btn-ghost" onclick="editUser(${u.id})"><i class="fas fa-edit"></i></button>
                ${u.id != currentUserId ? `<button class="btn btn-xs btn-ghost text-error" onclick="deleteUser(${u.id})"><i class="fas fa-trash"></i></button>` : ''}
            </td>
        </tr>
    `).join('');
}

function showUserModal(user = null) {
    document.getElementById('userModalTitle').textContent = user ? 'Edit User' : 'Add User';
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = user ? user.id : '';
    document.getElementById('userPassword').required = !user;
    
    if (user) {
        document.getElementById('userUsername').value = user.username;
        document.getElementById('userFullName').value = user.full_name;
        document.getElementById('userRole').value = user.role;
    }
    
    document.getElementById('userModal').showModal();
}

document.getElementById('userForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    
    const id = document.getElementById('userId').value;
    const username = document.getElementById('userUsername').value;
    const full_name = document.getElementById('userFullName').value;
    const role = document.getElementById('userRole').value;
    const password = document.getElementById('userPassword').value;
    
    const res = await fetch(`../api/users.php?action=${id ? 'update' : 'add'}`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&username=${encodeURIComponent(username)}&full_name=${encodeURIComponent(full_name)}&role=${role}&password=${encodeURIComponent(password)}`
    });
    
    const data = await res.json();
    if (data.success) {
        document.getElementById('userModal').close();
        loadUsers();
    } else {
        alert(data.message);
    }
});

async function editUser(id) {
    const res = await fetch('../api/users.php?action=list');
    const data = await res.json();
    const user = data.users.find(u => u.id === id);
    if (user) showUserModal(user);
}

async function deleteUser(id) {
    if (confirm('Delete this user?')) {
        const res = await fetch(`../api/users.php?action=delete&id=${id}`);
        const data = await res.json();
        if (data.success) loadUsers();
    }
}

function initReports() {
    document.getElementById('reportFrom').value = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
    document.getElementById('reportTo').value = new Date().toISOString().split('T')[0];
}

async function generateReport() {
    const from = document.getElementById('reportFrom').value;
    const to = document.getElementById('reportTo').value;
    
    const res = await fetch(`../api/stats.php?action=report&from=${from}&to=${to}`);
    const data = await res.json();
    
    if (data.success) {
        document.getElementById('reportTotalSales').textContent = '₱' + parseFloat(data.total_sales).toFixed(2);
        document.getElementById('reportTotalOrders').textContent = data.total_orders;
        document.getElementById('reportAvgOrder').textContent = '₱' + (data.total_orders > 0 ? (data.total_sales / data.total_orders).toFixed(2) : '0.00');
        
        document.getElementById('reportProductsTable').innerHTML = `
            <table class="table table-zebra table-sm">
                <thead><tr><th>Product</th><th>Quantity</th><th>Revenue</th></tr></thead>
                <tbody>
                    ${data.products.map(p => `
                        <tr>
                            <td>${p.name}</p></td>
                            <td>${p.total_sold}</td>
                            <td>₱${parseFloat(p.total_revenue).toFixed(2)}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
        `;
    }
}

let selectedChatUser = null;
let chatPollInterval = null;

async function loadChat() {
    const res = await fetch('../api/users.php?action=cashiers');
    const data = await res.json();
    
    const list = document.getElementById('cashierList');
    if (!data.users || data.users.length === 0) {
        list.innerHTML = '<p class="text-center text-base-content/60 text-sm">No cashiers available</p>';
        return;
    }
    
    list.innerHTML = data.users.map(u => `
        <div class="flex items-center gap-2 p-2 rounded-lg cursor-pointer hover:bg-base-200 ${selectedChatUser === u.id ? 'bg-primary text-primary-content' : ''}" onclick="selectChatUser(${u.id}, '${u.full_name}')">
            <div class="w-8 h-8 bg-base-200 rounded-full flex items-center justify-center">
                <i class="fas fa-user text-sm"></i>
            </div>
            <span class="text-sm font-medium">${u.full_name}</span>
        </div>
    `).join('');
}

function selectChatUser(userId, userName) {
    selectedChatUser = userId;
    document.getElementById('chatHeader').innerHTML = `<h3 class="font-bold">${userName}</h3>`;
    document.getElementById('chatInput').disabled = false;
    document.getElementById('sendChatBtn').disabled = false;
    
    loadChatMessages();
    
    if (chatPollInterval) clearInterval(chatPollInterval);
    chatPollInterval = setInterval(loadChatMessages, 3000);
}

async function loadChatMessages() {
    if (!selectedChatUser) return;
    
    const res = await fetch(`../api/chat.php?action=messages&user_id=${selectedChatUser}`);
    const data = await res.json();
    
    const container = document.getElementById('chatMessages');
    if (!data.messages || data.messages.length === 0) {
        container.innerHTML = '<div class="text-center text-base-content/60 py-8"><i class="fas fa-comments text-4xl"></i><p class="mt-2">Start a conversation</p></div>';
        return;
    }
    
    container.innerHTML = data.messages.map(m => `
        <div class="flex ${m.sender_id == currentUserId ? 'justify-end' : 'justify-start'}">
            <div class="max-w-xs ${m.sender_id == currentUserId ? 'bg-primary text-primary-content' : 'bg-base-200'} p-3 rounded-lg">
                <p class="text-sm">${m.message}</p>
                <p class="text-xs ${m.sender_id == currentUserId ? 'text-primary-content/70' : 'text-base-content/50'} mt-1">${new Date(m.created_at).toLocaleTimeString()}</p>
            </div>
        </div>
    `).join('');
    
    container.scrollTop = container.scrollHeight;
}

document.getElementById('sendChatBtn').addEventListener('click', sendChatMessage);
document.getElementById('chatInput').addEventListener('keypress', (e) => {
    if (e.key === 'Enter') sendChatMessage();
});

async function sendChatMessage() {
    const input = document.getElementById('chatInput');
    const message = input.value.trim();
    
    if (!message || !selectedChatUser) return;
    
    const res = await fetch('../api/chat.php?action=send', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `receiver_id=${selectedChatUser}&message=${encodeURIComponent(message)}`
    });
    
    input.value = '';
    loadChatMessages();
}

loadDashboard();