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
    <title>Products - CanteenPOS Admin</title>
    <link rel="stylesheet" href="../assets/css/styles.css">
    <style>
        .variant-section { background: #f8fafc; padding: 16px; border-radius: 10px; margin-top: 16px; margin-bottom: 16px; border: 1px solid #e2e8f0; }
        .variant-section h4 { margin-bottom: 10px; font-size: 14px; display: flex; justify-content: space-between; align-items: center; color: #0f172a; }
        .variant-row { display: flex; gap: 10px; margin-bottom: 8px; align-items: center; flex-wrap: wrap; }
        .variant-row input { height: 38px; padding: 0 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 13px; }
        .variant-row input[type="text"] { flex: 1; min-width: 120px; }
        .variant-row input[type="number"] { width: 90px; }
        .variant-combo { background: white; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; margin-bottom: 8px; }
    </style>
    <link rel="manifest" href="../manifest.json">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="../assets/icons/icon.svg">
</head>
<body>
        <div class="header">
            <div class="logo">
                <div class="logo-icon">📦</div>
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
            <a class="sidebar-item active" href="products.php">📦 Products</a>
            <a class="sidebar-item" href="categories.php">🏷️ Categories</a>
            <a class="sidebar-item" href="orders.php">📋 Orders</a>
            <a class="sidebar-item" href="tickets.php">🎫 Tickets</a>
            <a class="sidebar-item" href="users.php">👥 Users</a>
            <a class="sidebar-item" href="reports.php">📈 Reports</a>
            <a class="sidebar-item" href="chat.php">💬 Chat</a>
        </div>
        
        <div class="content">
            <h2>Products <button class="btn btn-primary btn-sm" onclick="showModal()">+ Add Product</button></h2>
            
            <div class="card">
                <input type="text" id="searchInput" placeholder="Search products..." style="width:300px;padding:8px;border:1px solid #ddd;border-radius:4px;margin-bottom:15px;">
                <table>
                    <thead><tr><th>Name</th><th>Category</th><th>Price</th><th>Variants</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody id="productsTable"></tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Modal -->
    <div id="productModal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);align-items:center;justify-content:center;overflow:auto;">
        <div style="background:white;padding:25px;border-radius:8px;width:600px;margin:20px;max-height:90vh;overflow:auto;">
            <h3 id="modalTitle" style="margin-bottom:15px;">Add Product</h3>
            <input type="hidden" id="productId">
            
            <div class="form-group">
                <label>Product Name</label>
                <input type="text" id="productName" placeholder="e.g. Cupcake">
            </div>
            <div class="form-group">
                <label>Category</label>
                <select id="productCategory"></select>
            </div>
            <div class="form-group">
                <label>Base Price (fallback price)</label>
                <input type="number" id="productPrice" step="0.01" placeholder="0.00">
            </div>
            <div class="form-group" id="stockField">
                <label>Stock (for products without variants)</label>
                <input type="number" id="productStock" placeholder="Leave empty for unlimited" style="width:200px;">
            </div>
            <div class="form-group">
                <label>Product Image</label>
                <input type="file" id="productImage" accept="image/*" style="padding:8px;">
                <input type="hidden" id="existingImage">
                <div id="imagePreview" style="margin-top:10px;"></div>
            </div>
            
            <div class="variant-section">
                <h4>
                    Variants 
                    <button type="button" class="btn btn-sm btn-outline" onclick="addVariantType()">+ Add Variant Type</button>
                </h4>
                <p style="font-size:12px;color:#666;margin-bottom:15px;">Add multiple variant types (e.g., Size + Flavor). The system will create all possible combinations.</p>
                <div id="variantTypesContainer"></div>
            </div>
            
            <div style="display:flex;gap:10px;margin-top:20px;">
                <button class="btn btn-outline" onclick="closeModal()">Cancel</button>
                <button class="btn btn-primary" onclick="saveProduct()">Save Product</button>
            </div>
        </div>
    </div>

    <script>
        let variantTypesData = [];
        
        async function loadProducts() {
            const res = await fetch('../api/products.php?action=list&include_variants=1');
            const data = await res.json();
            const tbody = document.getElementById('productsTable');
            
            if (!data.products || data.products.length === 0) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#888;">No products found</td></tr>';
                return;
            }
            
            tbody.innerHTML = data.products.map(p => `
                <tr>
                    <td>
                        ${p.image ? `<img src="${p.image}" style="width:40px;height:40px;object-fit:cover;border-radius:4px;margin-right:10px;vertical-align:middle;">` : '<span style="display:inline-block;width:40px;height:40px;background:#eee;border-radius:4px;margin-right:10px;vertical-align:middle;text-align:center;line-height:40px;">📦</span>'}
                        ${p.name}
                    </td>
                    <td>${p.category_name || '-'}</td>
                    <td>₱${parseFloat(p.base_price).toFixed(2)}</td>
                    <td>${p.variants && p.variants.length ? p.variants.length + ' variants' : (p.stock != null ? p.stock + ' in stock' : '—')}</td>
                    <td><span style="padding:4px 8px;background:${p.is_active ? '#dcfce7' : '#fee'};color:${p.is_active ? '#16a34a' : '#c00'};border-radius:4px;font-size:11px;">${p.is_active ? 'Active' : 'Inactive'}</span></td>
                    <td>
                        <button class="btn btn-sm btn-outline" onclick="editProduct(${p.id})">Edit</button>
                        <button class="btn btn-sm btn-outline" style="color:#c00;" onclick="deleteProduct(${p.id})">Delete</button>
                    </td>
                </tr>
            `).join('');
        }

        async function loadCategoriesForSelect() {
            const res = await fetch('../api/categories.php?action=list');
            const data = await res.json();
            document.getElementById('productCategory').innerHTML = '<option value="">Select category</option>' + 
                data.categories.map(c => `<option value="${c.id}">${c.name}</option>`).join('');
        }

        async function loadVariantTypes() {
            const res = await fetch('../api/variants.php?action=types');
            const data = await res.json();
            variantTypesData = data.types || [];
        }

        function addVariantType() {
            const container = document.getElementById('variantTypesContainer');
            const typeId = 'type_' + Date.now();
            
            let options = '<option value="">Select variant type</option>';
            variantTypesData.forEach(t => {
                options += `<option value="${t.id}" data-values="${t.values.join(',')}">${t.name}</option>`;
            });
            
            const div = document.createElement('div');
            div.className = 'variant-section';
            div.id = typeId;
            div.innerHTML = `
                <h4>Variant Type <button type="button" class="btn btn-sm btn-outline" style="color:#c00;padding:2px 8px;" onclick="removeVariantType('${typeId}')">Remove</button></h4>
                <select class="variant-type-select" onchange="variantTypeChanged(this)" style="margin-bottom:10px;">
                    ${options}
                </select>
                <div class="variant-values"></div>
                <button type="button" class="btn btn-sm btn-outline" onclick="addVariantValue(this)">+ Add Value</button>
            `;
            container.appendChild(div);
            toggleStockField();
        }

        function removeVariantType(id) {
            document.getElementById(id).remove();
            toggleStockField();
        }

        function variantTypeChanged(select) {
            const selected = select.options[select.selectedIndex];
            const valuesContainer = select.parentElement.querySelector('.variant-values');
            valuesContainer.innerHTML = '';
            
            if (selected.dataset.values) {
                const values = selected.dataset.values.split(',');
                values.forEach(val => {
                    addVariantValueInput(valuesContainer, val.trim());
                });
            }
        }

        function addVariantValue(btn) {
            const container = btn.previousElementSibling;
            addVariantValueInput(container, '');
        }

        function addVariantValueInput(container, defaultValue = '', defaultPrice = '', defaultStock = '10') {
            const div = document.createElement('div');
            div.className = 'variant-row';
            div.innerHTML = `
                <input type="text" placeholder="Value (e.g. Large)" value="${defaultValue}" class="variant-value-input" style="flex:1;min-width:80px;">
                <span style="font-size:11px;color:#64748b;font-weight:600;white-space:nowrap;">Price</span>
                <input type="number" step="0.01" placeholder="0.00" value="${defaultPrice}" class="variant-price-input" style="width:85px;">
                <span style="font-size:11px;color:#64748b;font-weight:600;white-space:nowrap;">Stock</span>
                <input type="number" placeholder="0" value="${defaultStock}" class="variant-stock-input" style="width:65px;" required>
                <button type="button" class="btn btn-sm btn-outline" style="color:#c00;padding:4px 8px;" onclick="this.parentElement.remove()">×</button>
            `;
            container.appendChild(div);
        }

        function toggleStockField() {
            const hasVariants = document.querySelectorAll('#variantTypesContainer .variant-section').length > 0;
            document.getElementById('stockField').style.display = hasVariants ? 'none' : 'block';
        }

        async function showModal(p = null) {
            document.getElementById('productModal').style.display = 'flex';
            document.getElementById('modalTitle').textContent = p ? 'Edit Product' : 'Add Product';
            
            loadCategoriesForSelect();
            await loadVariantTypes();
            
            document.getElementById('productId').value = '';
            document.getElementById('productName').value = '';
            document.getElementById('productPrice').value = '';
            document.getElementById('productStock').value = '';
            document.getElementById('productImage').value = '';
            document.getElementById('existingImage').value = '';
            document.getElementById('imagePreview').innerHTML = '';
            document.getElementById('variantTypesContainer').innerHTML = '';
            
            if (p) {
                document.getElementById('productId').value = p.id;
                document.getElementById('productName').value = p.name;
                document.getElementById('productPrice').value = p.base_price;
                document.getElementById('productStock').value = p.stock || '';
                document.getElementById('existingImage').value = p.image || '';
                
                if (p.image) {
                    document.getElementById('imagePreview').innerHTML = `<img src="${p.image}" style="max-width:150px;max-height:100px;border-radius:4px;">`;
                }
                
                if (p.variants && p.variants.length > 0) {
                    const typeMap = {};
                    p.variants.forEach(v => {
                        if (!typeMap[v.variant_type_id]) typeMap[v.variant_type_id] = { name: v.variant_type_name, values: [] };
                        typeMap[v.variant_type_id].values.push(v);
                    });
                    
                    Object.keys(typeMap).forEach(typeId => {
                        addVariantTypeWithValues(typeMap[typeId]);
                    });
                }
            }
            toggleStockField();
        }

        async function addVariantTypeWithValues(typeData) {
            const container = document.getElementById('variantTypesContainer');
            const typeId = 'type_' + Date.now();
            
            let options = '<option value="">Select variant type</option>';
            variantTypesData.forEach(t => {
                const isSelected = t.name === typeData.name ? 'selected' : '';
                options += `<option value="${t.id}" data-values="${t.values.join(',')}" ${isSelected}>${t.name}</option>`;
            });
            
            const div = document.createElement('div');
            div.className = 'variant-section';
            div.id = typeId;
            div.innerHTML = `
                <h4>Variant Type <button type="button" class="btn btn-sm btn-outline" style="color:#c00;padding:2px 8px;" onclick="removeVariantType('${typeId}')">Remove</button></h4>
                <select class="variant-type-select" onchange="variantTypeChanged(this)" style="margin-bottom:10px;">
                    ${options}
                </select>
                <div class="variant-values"></div>
                <button type="button" class="btn btn-sm btn-outline" onclick="addVariantValue(this)">+ Add Value</button>
            `;
            container.appendChild(div);
            
            const valuesContainer = div.querySelector('.variant-values');
            typeData.values.forEach(v => {
                addVariantValueInput(valuesContainer, v.variant_value, v.price || '', v.stock);
            });
            toggleStockField();
        }

        function closeModal() {
            document.getElementById('productModal').style.display = 'none';
        }

        async function saveProduct() {
            const id = document.getElementById('productId').value;
            const name = document.getElementById('productName').value;
            const category_id = document.getElementById('productCategory').value;
            const base_price = document.getElementById('productPrice').value;
            const stock = document.getElementById('productStock').value;
            const imageFile = document.getElementById('productImage').files[0];
            const existingImage = document.getElementById('existingImage').value;
            
            if (!name || !base_price) {
                alert('Please fill product name and base price');
                return;
            }
            
            const formData = new FormData();
            formData.append('name', name);
            formData.append('category_id', category_id);
            formData.append('base_price', base_price);
            if (stock) formData.append('stock', stock);
            if (id) formData.append('id', id);
            if (existingImage) formData.append('existing_image', existingImage);
            if (imageFile) formData.append('image', imageFile);
            
            const res = await fetch(`../api/products.php?action=${id ? 'update' : 'add'}`, { method: 'POST', body: formData });
            const data = await res.json();
            
            if (data.success) {
                const productId = id || data.id;
                
                // Delete old variants if editing, so we can replace them cleanly
                if (id) {
                    await fetch(`../api/variants.php?action=deleteByProduct&product_id=${productId}`);
                }
                
                const variantSections = document.querySelectorAll('#variantTypesContainer .variant-section');
                if (variantSections.length > 0) {
                    let missingStock = false;
                    document.querySelectorAll('.variant-stock-input').forEach(inp => {
                        if (!inp.value && inp.value !== '0') missingStock = true;
                    });
                    if (missingStock) {
                        alert('Please fill in stock for all variant values.');
                        return;
                    }
                    const allVariants = [];
                    
                    for (const section of variantSections) {
                        const typeSelect = section.querySelector('.variant-type-select');
                        const typeId = typeSelect.value;
                        const typeName = typeSelect.options[typeSelect.selectedIndex].text;
                        
                        const valueInputs = section.querySelectorAll('.variant-value-input');
                        const priceInputs = section.querySelectorAll('.variant-price-input');
                        const stockInputs = section.querySelectorAll('.variant-stock-input');
                        const values = [];
                        const prices = [];
                        const stocks = [];
                        valueInputs.forEach((input, idx) => {
                            if (input.value.trim()) {
                                values.push(input.value.trim());
                                prices.push(priceInputs[idx] ? priceInputs[idx].value : '');
                                stocks.push(stockInputs[idx] ? stockInputs[idx].value || '0' : '0');
                            }
                        });
                        
                        if (typeId && values.length > 0) {
                            allVariants.push({ typeId, typeName, values, prices, stocks });
                        }
                    }
                    
                    if (allVariants.length > 0) {
                        const combinations = generateCombinations(allVariants);
                        
                        for (const combo of combinations) {
                            const variantValue = combo.values.join(' + ');
                            const sku = combo.values.map(v => v.substring(0, 3).toUpperCase()).join('-');
                            const price = combo.prices[0] || '';
                            const stock = combo.stocks[0] || '0';
                            
                            let body = `product_id=${productId}&variant_type_id=${combo.typeIds[0]}&variant_value=${encodeURIComponent(variantValue)}&price_modifier=0&stock=${stock}&sku=${sku}`;
                            if (price) body += `&price=${price}`;
                            
                            const r = await fetch('../api/variants.php?action=add', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: body
                            });
                            const rd = await r.json();
                            if (!rd.success) {
                                alert('Failed to create variant: ' + (rd.message || 'Unknown error'));
                                return;
                            }
                        }
                    }
                }
                
                closeModal();
                loadProducts();
            } else {
                alert(data.message);
            }
        }

        function generateCombinations(variantTypes) {
            if (variantTypes.length === 0) return [];
            if (variantTypes.length === 1) {
                return variantTypes[0].values.map((v, i) => ({
                    typeIds: [variantTypes[0].typeId],
                    values: [v],
                    prices: [variantTypes[0].prices[i] || ''],
                    stocks: [variantTypes[0].stocks[i] || '0']
                }));
            }
            
            const first = variantTypes[0];
            const rest = generateCombinations(variantTypes.slice(1));
            
            let combinations = [];
            first.values.forEach((v, i) => {
                for (const r of rest) {
                    combinations.push({
                        typeIds: [first.typeId, ...r.typeIds],
                        values: [v, ...r.values],
                        prices: [first.prices[i] || '', ...r.prices],
                        stocks: [first.stocks[i] || '0', ...r.stocks]
                    });
                }
            });
            return combinations;
        }

        async function editProduct(id) {
            const res = await fetch(`../api/products.php?action=get&id=${id}`);
            const data = await res.json();
            if (data.success) showModal(data.product);
        }

        async function deleteProduct(id) {
            if (confirm('Delete this product?')) {
                const res = await fetch(`../api/products.php?action=delete&id=${id}`);
                const data = await res.json();
                if (data.success) loadProducts();
            }
        }

        document.getElementById('searchInput').addEventListener('input', function() {
            const term = this.value.toLowerCase();
            document.querySelectorAll('#productsTable tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(term) ? '' : 'none';
            });
        });

        loadProducts();
    </script>
    <script>if('serviceWorker'in navigator){navigator.serviceWorker.register('../sw.js');}</script>
</body>
</html>