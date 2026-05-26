<?php
session_start();
require_once 'api/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SESSION['role'] === 'admin') {
    header('Location: admin/dashboard.php');
    exit;
}

$user = ['id' => $_SESSION['user_id'], 'username' => $_SESSION['username'], 'full_name' => $_SESSION['full_name'], 'role' => $_SESSION['role']];
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>POS - CanteenPOS</title>
    <link rel="stylesheet" href="assets/css/styles.css">
    <style>
        body { height: 100vh; display: flex; flex-direction: column; background: #f0f2f5; }
        .user-info { font-size: 14px; color: #475569; font-weight: 500; }

        .pos-main { display: flex; flex: 1; overflow: hidden; gap: 0; }

        .products-section { flex: 1; padding: 24px; overflow: auto; }
        .filters { display: flex; gap: 12px; margin-bottom: 24px; }
        .filters .search-input { flex: 1; max-width: 280px; height: 52px; font-size: 16px; padding: 0 16px 0 46px; background-position: 16px center; background-size: 20px; }
        .filters select { min-width: 360px; height: 52px; padding: 0 12px; border-radius: 12px; border: 1.5px solid #e2e8f0; background: white; font-size: 14px; outline: none; cursor: pointer; }
        .filters select:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }

        .products-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(170px, 1fr)); gap: 16px; }
        .product-card { background: white; border-radius: 14px; padding: 20px 16px; text-align: center; cursor: pointer; box-shadow: 0 1px 2px rgba(0,0,0,0.04), 0 4px 12px rgba(0,0,0,0.06); border: 1.5px solid transparent; transition: all 0.2s ease; }
        .product-card:hover { transform: translateY(-3px); box-shadow: 0 4px 16px rgba(0,0,0,0.1); border-color: #2563eb; }
        .product-card:active { transform: scale(0.97); }
        .product-img { width: 80px; height: 80px; background: #f1f5f9; border-radius: 12px; margin: 0 auto 12px; display: flex; align-items: center; justify-content: center; font-size: 32px; color: #94a3b8; }
        .product-name { font-size: 15px; font-weight: 600; color: #0f172a; margin-bottom: 4px; }
        .product-category { font-size: 12px; color: #94a3b8; margin-bottom: 8px; }
        .product-price { font-size: 18px; font-weight: 700; color: #2563eb; }
        .product-stock { font-size: 11px; font-weight: 600; margin-top: 6px; padding: 2px 10px; border-radius: 20px; display: inline-block; }
        .product-stock.ok { background: #dcfce7; color: #16a34a; }
        .product-stock.low { background: #fef2f2; color: #ef4444; }

        .cart { width: 380px; background: white; border-left: 1px solid #e2e8f0; display: flex; flex-direction: column; min-width: 320px; }
        .cart-header { padding: 20px 20px 16px; border-bottom: 1px solid #e2e8f0; display: flex; justify-content: space-between; align-items: center; }
        .cart-header h2 { font-size: 17px; font-weight: 700; color: #0f172a; }
        .clear-btn { color: #ef4444; background: none; border: none; cursor: pointer; font-size: 13px; font-weight: 500; padding: 6px 12px; border-radius: 8px; transition: background 0.15s; }
        .clear-btn:hover { background: #fef2f2; }
        .cart-items { flex: 1; overflow: auto; padding: 16px; }
        .cart-empty { text-align: center; color: #94a3b8; padding: 60px 20px; font-size: 14px; }

        .cart-item { display: flex; justify-content: space-between; align-items: center; padding: 14px; background: #f8fafc; border-radius: 12px; margin-bottom: 10px; border: 1px solid #f1f5f9; }
        .cart-item-info { flex: 1; min-width: 0; }
        .cart-item-name { font-size: 14px; font-weight: 600; color: #0f172a; }
        .cart-item-variant { font-size: 12px; color: #94a3b8; margin-top: 2px; }
        .cart-item-price { font-size: 13px; font-weight: 600; color: #2563eb; margin-top: 4px; }
        .cart-item-qty { display: flex; align-items: center; gap: 12px; margin-left: 12px; }
        .qty-btn { width: 32px; height: 32px; border: 1.5px solid #e2e8f0; background: white; border-radius: 8px; cursor: pointer; font-size: 16px; font-weight: 600; color: #475569; display: flex; align-items: center; justify-content: center; transition: all 0.15s; }
        .qty-btn:hover { background: #f1f5f9; border-color: #2563eb; color: #2563eb; }
        .cart-item-qty span { font-size: 16px; font-weight: 600; color: #0f172a; min-width: 20px; text-align: center; }

        .cart-footer { padding: 20px; border-top: 1px solid #e2e8f0; }
        .cart-total { display: flex; justify-content: space-between; font-size: 22px; font-weight: 800; color: #0f172a; margin-bottom: 16px; }
        .checkout-btn { width: 100%; padding: 16px; border: none; border-radius: 12px; cursor: pointer; font-size: 16px; font-weight: 700; background: linear-gradient(135deg, #2563eb, #1d4ed8); color: white; transition: all 0.2s; }
        .checkout-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 20px rgba(37,99,235,0.35); }
        .checkout-btn:disabled { opacity: 0.4; cursor: not-allowed; }

        .variant-option { display: flex; justify-content: space-between; align-items: center; padding: 14px 16px; border: 1.5px solid #e2e8f0; border-radius: 10px; margin-bottom: 8px; cursor: pointer; transition: all 0.15s; }
        .variant-option:hover { border-color: #2563eb; background: #f8faff; }
        .variant-option.selected { border-color: #2563eb; background: #eff6ff; }
        .variant-price { font-weight: 700; color: #2563eb; font-size: 16px; }

        .checkout-section { margin-bottom: 16px; }
        .checkout-label { font-size: 13px; color: #64748b; margin-bottom: 6px; font-weight: 500; }
        .checkout-total-display { font-size: 28px; font-weight: 800; color: #2563eb; }

        .quick-buttons { display: flex; gap: 8px; margin-top: 10px; flex-wrap: wrap; }
        .quick-btn { padding: 12px 20px; border: 1.5px solid #e2e8f0; background: white; border-radius: 10px; cursor: pointer; font-size: 14px; font-weight: 600; color: #475569; transition: all 0.15s; }
        .quick-btn:hover { background: #f1f5f9; border-color: #2563eb; color: #2563eb; }

        .change-display { font-size: 32px; font-weight: 800; text-align: center; padding: 16px; border-radius: 10px; background: #f8fafc; }
        .change-positive { color: #22c55e; }
        .change-negative { color: #ef4444; }

        .receipt { width: 340px; }
        .receipt-header { text-align: center; border-bottom: 1.5px dashed #e2e8f0; padding-bottom: 16px; margin-bottom: 16px; }
        .receipt-header h2 { font-size: 20px; color: #0f172a; }
        .receipt-header p { font-size: 13px; color: #94a3b8; }
        .receipt-items { margin-bottom: 16px; }
        .receipt-item { display: flex; justify-content: space-between; font-size: 14px; margin-bottom: 8px; padding: 4px 0; }
        .receipt-total { border-top: 1.5px dashed #e2e8f0; padding-top: 12px; font-weight: 700; font-size: 16px; display: flex; justify-content: space-between; }
        .receipt-change { font-size: 13px; color: #22c55e; margin-top: 8px; }

        .request-edit-section { margin-top: 16px; padding-top: 16px; border-top: 1px solid #e2e8f0; }
        .request-edit-section textarea { width: 100%; padding: 12px 16px; border: 1.5px solid #e2e8f0; border-radius: 10px; font-size: 14px; font-family: inherit; resize: vertical; min-height: 60px; outline: none; }
        .request-edit-section textarea:focus { border-color: #2563eb; box-shadow: 0 0 0 3px rgba(37,99,235,0.12); }

        .num-pad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 8px; margin-top: 12px; }
        .num-pad button { height: 52px; border: 1.5px solid #e2e8f0; background: white; border-radius: 10px; font-size: 20px; font-weight: 600; color: #0f172a; cursor: pointer; transition: all 0.1s; }
        .num-pad button:hover { background: #f1f5f9; border-color: #2563eb; }
        .num-pad button:active { transform: scale(0.95); background: #e2e8f0; }
        .num-pad .num-enter { background: #2563eb; color: white; border-color: #2563eb; }
        .num-pad .num-enter:hover { background: #1d4ed8; }
        .num-pad .num-clear { background: #fef2f2; color: #ef4444; border-color: #fecaca; }

        .order-card { display:flex; align-items:center; justify-content:space-between; padding:14px 18px; background:#f8fafc; border-radius:12px; margin-bottom:10px; border:1px solid #f1f5f9; transition:all 0.15s; }
        .order-card:hover { border-color:#2563eb; background:#f8faff; }
        .order-card-info { flex:1; min-width:0; }
        .order-card-number { font-size:15px; font-weight:600; color:#0f172a; }
        .order-card-date { font-size:12px; color:#94a3b8; margin-top:2px; }
        .order-card-total { font-size:16px; font-weight:700; color:#2563eb; margin-top:4px; }
        .order-card-status { font-size:11px; font-weight:600; padding:3px 10px; border-radius:20px; display:inline-block; margin-left:8px; }
        .order-card-status.completed { background:#dcfce7; color:#16a34a; }
        .order-card-status.cancelled { background:#fef2f2; color:#ef4444; }
        .order-card-actions { display:flex; gap:8px; align-items:center; }
        .order-card-actions button { white-space:nowrap; }
        .order-empty { text-align:center; color:#94a3b8; padding:60px 20px; font-size:14px; }

        @media (max-width: 768px) {
            .pos-main { flex-direction: column; }
            .cart { width: 100%; min-width: unset; border-left: none; border-top: 1px solid #e2e8f0; max-height: 50vh; }
            .products-grid { grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); }
            .products-section { padding: 16px; }
        }
    </style>
    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#2563eb">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <link rel="apple-touch-icon" href="assets/icons/icon.svg">
</head>
<body>
    <div class="header">
        <div class="logo">
            <div class="logo-icon">📦</div>
            <div>
                <h1>CanteenPOS</h1>
                <span>Point of Sale</span>
            </div>
        </div>
        <div class="header-right">
            <button class="btn btn-outline" onclick="openChat()">💬 Chat</button>
            <button class="btn btn-outline" onclick="openOrderHistory()">📋 My Orders</button>
            <span class="user-info"><?= htmlspecialchars($user['full_name']) ?></span>
            <a href="api/logout.php" onclick="return confirm('Are you sure you want to logout?')"><button class="btn btn-outline">Logout</button></a>
        </div>
    </div>
    
    <div class="pos-main">
        <div class="products-section">
            <div class="filters">
                <input type="text" id="searchInput" class="search-input" placeholder="Search products...">
                <select id="categoryFilter">
                    <option value="">All Categories</option>
                </select>
            </div>
            <div id="productsGrid" class="products-grid"></div>
        </div>

        <div class="cart">
            <div class="cart-header">
                <h2>🧾 Cart (<span id="cartCount">0</span>)</h2>
                <button class="clear-btn" onclick="clearCart()">Clear all</button>
            </div>
            <div id="cartItems" class="cart-items">
                <div class="cart-empty">Tap products to start adding</div>
            </div>
            <div class="cart-footer">
                <div class="cart-total">
                    <span>Total</span>
                    <span>₱<span id="cartTotal">0.00</span></span>
                </div>
                <button id="checkoutBtn" class="checkout-btn" onclick="openCheckout()" disabled>Checkout</button>
            </div>
        </div>
    </div>

    <!-- ===== MODALS ===== -->

    <!-- Variant Selection Modal - appears when product has variants (e.g., Small/Medium/Large) -->
    <div id="variantModal" class="modal">
        <div class="modal-content" style="width:400px;max-height:90vh;display:flex;flex-direction:column;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;">
                <h3 id="variantModalTitle" style="margin:0;">Select Options</h3>
                <button onclick="closeVariantModal()" style="background:none;border:none;font-size:20px;cursor:pointer;color:#888;">&times;</button>
            </div>
            <!-- Variant options rendered here - scrollable if many -->
            <div id="variantOptions" style="max-height:350px;overflow-y:auto;flex:1;"></div>
            <div class="modal-actions">
                <button class="btn btn-cancel" onclick="closeVariantModal()">Cancel</button>
                <button class="btn btn-primary" onclick="addVariantToCart()">Add to Cart</button>
            </div>
        </div>
    </div>

    <div id="checkoutModal" class="modal">
        <div class="modal-content">
            <h3>💳 Checkout</h3>

            <div class="checkout-section">
                <div class="checkout-label">Customer Name (optional)</div>
                <input type="text" id="customerName" placeholder="e.g. Juan Dela Cruz" style="width:100%;height:46px;padding:0 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;outline:none;">
            </div>

            <div class="checkout-section">
                <div class="checkout-label">Total Amount</div>
                <div class="checkout-total-display">₱<span id="checkoutTotal">0.00</span></div>
            </div>

            <div class="checkout-section">
                <div class="checkout-label">Cash Tendered</div>
                <input type="number" id="cashTendered" placeholder="0" step="1" min="0" style="width:100%;height:46px;padding:0 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px;outline:none;margin-bottom:8px;">
                <div class="quick-buttons">
                    <button class="quick-btn" onclick="setCash(20)">₱20</button>
                    <button class="quick-btn" onclick="setCash(50)">₱50</button>
                    <button class="quick-btn" onclick="setCash(100)">₱100</button>
                    <button class="quick-btn" onclick="setCash(200)">₱200</button>
                    <button class="quick-btn" onclick="setCash(500)">₱500</button>
                    <button class="quick-btn" onclick="setCash('exact')">Exact</button>
                </div>
            </div>

            <div class="checkout-section">
                <div class="checkout-label">Change</div>
                <div id="changeDisplay" class="change-display">₱0.00</div>
            </div>

            <div class="modal-actions">
                <button class="btn btn-outline" onclick="document.getElementById('checkoutModal').classList.remove('active')">Cancel</button>
                <button id="completeSaleBtn" class="btn btn-primary" onclick="completeSale()" disabled>Complete Sale</button>
            </div>
        </div>
    </div>

    <div id="receiptModal" class="modal">
        <div class="modal-content receipt">
            <div class="receipt-header">
                <h2>CanteenPOS</h2>
                <p>School Canteen</p>
            </div>
            <div id="receiptDetails"></div>
            <div class="request-edit-section" id="requestEditSection" style="display:none;">
                <label class="checkout-label" style="font-size:13px;color:#64748b;margin-bottom:6px;">Need a correction?</label>
                <textarea id="requestEditReason" placeholder="Tell admin what needs to be fixed... (e.g. wrong amount, wrong item)"></textarea>
                <button class="btn btn-outline" onclick="requestEdit()" style="margin-top:10px;width:100%;">📩 Request Edit from Admin</button>
            </div>
            <div class="modal-actions">
                <button class="btn btn-primary" onclick="closeReceipt()">Done</button>
            </div>
        </div>
    </div>

    <!-- Chat Modal - for messaging with admin -->
    <div id="chatModal" class="modal">
        <div class="modal-content" style="width:450px;max-height:80vh;display:flex;flex-direction:column;">
            <h3>Chat with Admin</h3>
            <div id="chatMessages" style="flex:1;overflow:auto;min-height:200px;max-height:400px;padding:10px;background:#f9f9f9;border-radius:6px;margin:10px 0;"></div>
            <div style="display:flex;gap:10px;">
                <input type="text" id="chatInput" class="checkout-input" placeholder="Type a message..." style="flex:1;">
                <button class="btn btn-primary" onclick="sendChatMessage()">Send</button>
            </div>
            <div class="modal-actions" style="margin-top:10px;">
                <button class="btn btn-cancel" onclick="closeChatModal()">Close</button>
            </div>
        </div>
    </div>

    <!-- ===== ORDER HISTORY MODAL ===== -->
    <div id="orderHistoryModal" class="modal">
        <div class="modal-content" style="width:600px;max-height:85vh;display:flex;flex-direction:column;">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
                <h3 style="margin:0;">📋 My Orders</h3>
                <button onclick="closeOrderHistory()" style="background:none;border:none;font-size:22px;cursor:pointer;color:#888;">&times;</button>
            </div>
            <div style="display:flex;gap:10px;margin-bottom:16px;">
                <input type="text" id="orderSearchInput" class="search-input" placeholder="Search orders..." style="flex:1;height:42px;padding:0 14px;font-size:14px;">
                <select id="orderStatusFilter" style="min-width:120px;height:42px;padding:0 12px;border-radius:10px;border:1.5px solid #e2e8f0;font-size:13px;outline:none;">
                    <option value="">All Status</option>
                    <option value="completed">Completed</option>
                    <option value="cancelled">Cancelled</option>
                </select>
            </div>
            <div id="orderListContainer" style="flex:1;overflow:auto;min-height:200px;"></div>
            <div class="modal-actions" style="margin-top:12px;">
                <button class="btn btn-cancel" onclick="closeOrderHistory()">Close</button>
            </div>
        </div>
    </div>

    <!-- ===== JAVASCRIPT FUNCTIONS ===== -->

        <script>
        let cart = [], products = [], currentProduct = null, lastOrderId = null, lastOrderNumber = null;

        /**
         * Load categories from API and populate dropdown filter
         * Called on page load
         */
        async function loadCategories() {
            const res = await fetch('api/categories.php?action=list');
            const data = await res.json();
            // Add each category as an option in the dropdown
            data.categories.forEach(c => {
                const opt = document.createElement('option');
                opt.value = c.id;
                opt.textContent = c.name;
                document.getElementById('categoryFilter').appendChild(opt);
            });
        }

        /**
         * Load products from API based on selected category
         * Called on page load and when category filter changes
         */
        async function loadProducts() {
            const cat = document.getElementById('categoryFilter').value;
            let url = 'api/products.php?action=pos&include_variants=1';
            if (cat) url += '&category_id=' + cat;
            const res = await fetch(url);
            const data = await res.json();
            products = data.products;
            renderProducts();
        }

        /**
         * Render products in the grid based on search filter
         * Filters products by name matching search input
         */
        function renderProducts() {
            const search = document.getElementById('searchInput').value.toLowerCase();
            // Filter products by search term
            const filtered = products.filter(p => p.name.toLowerCase().includes(search));
            const grid = document.getElementById('productsGrid');
            
            // Show "no products" message if empty
            if (filtered.length === 0) {
                grid.innerHTML = '<p style="text-align:center;color:#888;grid-column:1/-1;">No products found</p>';
                return;
            }
            
            // Generate HTML for each product card
            grid.innerHTML = filtered.map(p => {
                const stock = p.variants && p.variants.length > 0 ? p.variants.reduce((s, v) => s + parseInt(v.stock || 0), 0) : (p.stock != null ? parseInt(p.stock) : null);
                const stockClass = stock !== null ? (stock <= 5 ? 'low' : 'ok') : '';
                return `
                <div class="product-card" onclick="selectProduct(${p.id})">
                    ${p.image 
                        ? `<img src="${p.image}" class="product-img" style="width:60px;height:60px;object-fit:cover;border-radius:8px;margin:0 auto 10px;">` 
                        : '<div class="product-img">📦</div>'}
                    <div class="product-name">${p.name}</div>
                    <div class="product-category">${p.category_name || ''}</div>
                    <div class="product-price">₱${parseFloat(p.base_price).toFixed(2)}</div>
                    ${stock !== null ? `<div class="product-stock ${stockClass}">${stock} left</div>` : ''}
                </div>`;
            }).join('');
        }

        /**
         * Handle product click - show variant modal if product has variants,
         * otherwise add directly to cart
         * @param {number} id - Product ID
         */
        function selectProduct(id) {
            const product = products.find(p => p.id === id);
            currentProduct = product;
            
            // If product has variants, show selection modal; otherwise add directly
            if (product.variants && product.variants.length > 0) {
                showVariantModal(product);
            } else {
                addToCart({...product, variant: null, variantId: null, price: parseFloat(product.base_price), stock: product.stock != null ? parseInt(product.stock) : null});
            }
        }

        /**
         * Show modal for selecting product variant (e.g., size, flavor)
         * @param {object} product - Product object with variants
         */
        function showVariantModal(product) {
            document.getElementById('variantModalTitle').textContent = product.name;
            
            // Generate variant options HTML
            document.getElementById('variantOptions').innerHTML = product.variants.map((v, i) => `
                <div class="variant-option ${i===0?'selected':''}" onclick="selectVariant(this, ${v.id})">
                    <div>
                        <div style="font-weight:600">${v.variant_value}</div>
                        <div style="font-size:12px;color:#888">Stock: ${v.stock}</div>
                    </div>
                    <div class="variant-price">₱${parseFloat(v.effective_price).toFixed(2)}</div>
                </div>
            `).join('');
            
            // Show modal and set first variant as selected
            document.getElementById('variantModal').classList.add('active');
            window.selectedVariantId = product.variants[0].id;
        }

        /**
         * Handle variant option click - highlight selected variant
         * @param {HTMLElement} el - Clicked element
         * @param {number} id - Variant ID
         */
        function selectVariant(el, id) {
            document.querySelectorAll('.variant-option').forEach(o => o.classList.remove('selected'));
            el.classList.add('selected');
            window.selectedVariantId = id;
        }

        /**
         * Close variant selection modal
         */
        function closeVariantModal() {
            document.getElementById('variantModal').classList.remove('active');
        }

        /**
         * Add selected variant to cart with calculated price
         */
        function addVariantToCart() {
            const variant = currentProduct.variants.find(v => v.id === window.selectedVariantId);
            const price = parseFloat(variant.effective_price);
            addToCart({...currentProduct, variant: variant.variant_value, variantId: variant.id, price: price, stock: variant.stock});
            closeVariantModal();
        }

        /**
         * Add item to cart - either adds to existing quantity or creates new entry
         * @param {object} item - Product to add
         */
        function addToCart(item) {
            const stock = item.stock; // null/undefined for products without variants
            const existing = cart.find(c => c.id === item.id && c.variantId === item.variantId);
            if (existing) {
                if (stock != null && existing.quantity >= stock) {
                    alert('Not enough stock available!');
                    return;
                }
                existing.quantity++;
            } else {
                if (stock != null && stock < 1) {
                    alert('This item is out of stock!');
                    return;
                }
                cart.push({...item, quantity: 1});
            }
            renderCart();
        }

        /**
         * Increase or decrease item quantity in cart
         * @param {number} index - Cart item index
         * @param {number} delta - +1 or -1
         */
        function updateQty(index, delta) {
            const item = cart[index];
            if (delta > 0 && item.stock != null && item.quantity >= item.stock) {
                alert('Not enough stock available!');
                return;
            }
            item.quantity += delta;
            if (item.quantity <= 0) cart.splice(index, 1);
            renderCart();
        }

        /**
         * Render cart items and update totals
         */
        function renderCart() {
            // Calculate total
            const total = cart.reduce((s, i) => s + i.price * i.quantity, 0);
            document.getElementById('cartCount').textContent = cart.reduce((s, i) => s + i.quantity, 0);
            document.getElementById('cartTotal').textContent = total.toFixed(2);
            document.getElementById('checkoutBtn').disabled = cart.length === 0;
            
            const container = document.getElementById('cartItems');
            if (cart.length === 0) {
                container.innerHTML = '<div class="cart-empty">Cart is empty</div>';
                return;
            }
            
            // Generate HTML for cart items
            container.innerHTML = cart.map((item, i) => `
                <div class="cart-item">
                    <div class="cart-item-info">
                        <div class="cart-item-name">${item.name}</div>
                        <div class="cart-item-variant">${item.variant || ''}</div>
                        <div class="cart-item-price">₱${item.price.toFixed(2)} x ${item.quantity}</div>
                    </div>
                    <div class="cart-item-qty">
                        <button class="qty-btn" onclick="updateQty(${i}, -1)">-</button>
                        <span>${item.quantity}</span>
                        <button class="qty-btn" onclick="updateQty(${i}, 1)">+</button>
                    </div>
                </div>
            `).join('');
        }

        /**
         * Clear all items from cart (with confirmation)
         */
        function clearCart() {
            if (cart.length && confirm('Clear cart?')) {
                cart = [];
                renderCart();
            }
        }

        /**
         * Open checkout modal and initialize payment fields
         */
        function openCheckout() {
            const total = cart.reduce((s, i) => s + i.price * i.quantity, 0);
            document.getElementById('checkoutTotal').textContent = total.toFixed(2);
            document.getElementById('customerName').value = '';
            document.getElementById('cashTendered').value = '';
            document.getElementById('changeDisplay').textContent = '₱0.00';
            document.getElementById('changeDisplay').className = 'change-display';
            document.getElementById('completeSaleBtn').disabled = true;
            document.getElementById('checkoutModal').classList.add('active');
        }

        /**
         * Set cash tendered amount from quick buttons
         * @param {number|string} amount - Quick amount or 'exact' for total
         */
        function setCash(amount) {
            const total = parseFloat(document.getElementById('checkoutTotal').textContent);
            document.getElementById('cashTendered').value = amount === 'exact' ? total : amount;
            calculateChange();
        }

        // Listen for cash input changes
        document.getElementById('cashTendered').addEventListener('input', calculateChange);

        /**
         * Calculate and display change amount
         * Enables complete sale button only when cash >= total
         */
        function calculateChange() {
            const total = parseFloat(document.getElementById('checkoutTotal').textContent);
            const cash = parseFloat(document.getElementById('cashTendered').value) || 0;
            const change = cash - total;
            
            const display = document.getElementById('changeDisplay');
            display.textContent = '₱' + change.toFixed(2);
            // Green if positive (sufficient), red if negative (insufficient)
            display.className = 'change-display ' + (change >= 0 ? 'change-positive' : 'change-negative');
            
            // Only enable complete sale if cash is sufficient
            document.getElementById('completeSaleBtn').disabled = change < 0;
        }

        /**
         * Complete the sale - send order to API and show receipt
         */
        async function completeSale() {
            const total = cart.reduce((s, i) => s + i.price * i.quantity, 0);
            const cash = parseFloat(document.getElementById('cashTendered').value);
            const change = cash - total;
            
            const customerName = document.getElementById('customerName').value.trim();
            // Send order to API
            const res = await fetch('api/orders.php?action=create', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    total_amount: total, 
                    cash_tendered: cash, 
                    change_given: change,
                    customer_name: customerName,
                    items: cart.map(i => ({
                        product_id: i.id, 
                        variant_id: i.variantId, 
                        quantity: i.quantity, 
                        unit_price: i.price, 
                        subtotal: i.price * i.quantity
                    }))
                })
            });
            const data = await res.json();
            
            if (data.success) {
                // Close checkout, show receipt, reset cart, refresh stock immediately
                document.getElementById('checkoutModal').classList.remove('active');
                showReceipt(data.order);
                cart = [];
                renderCart();
                loadProducts();
            } else {
                alert('Error: ' + data.message);
            }
        }

        /**
         * Display receipt modal with order details
         * @param {object} order - Order data from API
         */
        function showReceipt(order) {
            lastOrderId = order.id;
            lastOrderNumber = order.order_number;
            document.getElementById('receiptDetails').innerHTML = `
                <p style="font-size:13px;color:#64748b;margin-bottom:16px;text-align:center;font-weight:500;">Order: ${order.order_number}</p>
                <div class="receipt-items">
                    ${cart.map(i => `<div class="receipt-item"><span>${i.name}${i.variant ? ' ('+i.variant+')' : ''} x${i.quantity}</span><span>₱${(i.price*i.quantity).toFixed(2)}</span></div>`).join('')}
                </div>
                <div class="receipt-total"><span>Total</span><span>₱${order.total_amount.toFixed(2)}</span></div>
                <div class="receipt-change" style="display:flex;justify-content:space-between;"><span>Cash: ₱${order.cash_tendered.toFixed(2)}</span><span>Change: ₱${order.change_given.toFixed(2)}</span></div>
            `;
            document.getElementById('requestEditSection').style.display = 'block';
            document.getElementById('requestEditReason').value = '';
            document.getElementById('receiptModal').classList.add('active');
        }

        async function getAdminUserId() {
            if (ADMIN_USER_ID) return ADMIN_USER_ID;
            const res = await fetch('api/users.php?action=admin');
            const data = await res.json();
            if (data.success && data.admin) {
                ADMIN_USER_ID = data.admin.id;
                return ADMIN_USER_ID;
            }
            return null;
        }

        async function requestEdit() {
            const reason = document.getElementById('requestEditReason').value.trim();
            if (!reason) {
                alert('Please describe what needs to be corrected.');
                return;
            }
            if (!lastOrderId) return;

            const adminId = await getAdminUserId();
            if (!adminId) {
                alert('Cannot connect to admin. Please try again.');
                return;
            }

            try {
                const res = await fetch('api/notifications.php?action=create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `user_id=${adminId}&type=edit_request&message=${encodeURIComponent('Edit requested for order ' + lastOrderNumber + ': ' + reason)}&order_id=${lastOrderId}&link=admin/tickets.php?id=${lastOrderId}`
                });
                const data = await res.json();

                if (data.success) {
                    await fetch('api/chat.php?action=send', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `receiver_id=${adminId}&message=${encodeURIComponent('🔧 Edit Request for ' + lastOrderNumber + ': ' + reason)}`
                    });
                    alert('✅ Edit request sent to admin!');
                    document.getElementById('requestEditSection').style.display = 'none';
                } else {
                    alert('Failed to send request: ' + (data.message || 'Unknown error'));
                }
            } catch (e) {
                alert('Error sending request');
            }
        }

        /**
         * Close receipt modal and refresh products (to update stock)
         */
        function closeReceipt() {
            document.getElementById('receiptModal').classList.remove('active');
            document.getElementById('requestEditSection').style.display = 'none';
            loadProducts();
        }

        // ===== ORDER HISTORY FUNCTIONS =====

        let ordersData = [];

        async function openOrderHistory() {
            document.getElementById('orderHistoryModal').classList.add('active');
            document.getElementById('orderListContainer').innerHTML = '<div style="text-align:center;padding:40px;color:#94a3b8;">Loading orders...</div>';
            document.getElementById('orderSearchInput').value = '';
            document.getElementById('orderStatusFilter').value = '';
            await loadOrders();
        }

        function closeOrderHistory() {
            document.getElementById('orderHistoryModal').classList.remove('active');
        }

        async function loadOrders() {
            const userId = <?= $_SESSION['user_id'] ?>;
            try {
                const res = await fetch(`api/orders.php?action=list&user_id=${userId}&limit=50`);
                const data = await res.json();
                if (data.success) {
                    ordersData = data.orders;
                    renderOrders();
                } else {
                    document.getElementById('orderListContainer').innerHTML = '<div class="order-empty">Failed to load orders</div>';
                }
            } catch (e) {
                document.getElementById('orderListContainer').innerHTML = '<div class="order-empty">Error loading orders</div>';
            }
        }

        function renderOrders() {
            const search = document.getElementById('orderSearchInput').value.toLowerCase();
            const status = document.getElementById('orderStatusFilter').value;
            const filtered = ordersData.filter(o => {
                if (status && o.status !== status) return false;
                if (search && !o.order_number.toLowerCase().includes(search) && !o.full_name?.toLowerCase().includes(search)) return false;
                return true;
            });
            const container = document.getElementById('orderListContainer');
            if (filtered.length === 0) {
                container.innerHTML = '<div class="order-empty">No orders found</div>';
                return;
            }
            container.innerHTML = filtered.map(o => `
                <div class="order-card">
                    <div class="order-card-info">
                        <div class="order-card-number">${o.order_number} <span class="order-card-status ${o.status}">${o.status}</span></div>
                        <div class="order-card-date">${o.created_at ? new Date(o.created_at).toLocaleString() : ''}</div>
                        <div class="order-card-total">₱${parseFloat(o.total_amount).toFixed(2)}</div>
                    </div>
                    <div class="order-card-actions">
                        <button class="btn btn-outline" style="padding:8px 14px;font-size:13px;" onclick="requestEditFromHistory(${o.id},'${o.order_number}')">Request Edit</button>
                    </div>
                </div>
            `).join('');
        }

        document.getElementById('orderSearchInput').addEventListener('input', renderOrders);
        document.getElementById('orderStatusFilter').addEventListener('change', renderOrders);

        async function requestEditFromHistory(orderId, orderNumber) {
            const reason = prompt('Describe what needs to be corrected for ' + orderNumber + ':');
            if (!reason || !reason.trim()) return;

            const adminId = await getAdminUserId();
            if (!adminId) {
                alert('Cannot connect to admin. Please try again.');
                return;
            }

            try {
                const res = await fetch('api/notifications.php?action=create', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `user_id=${adminId}&type=edit_request&message=${encodeURIComponent('Edit requested for ' + orderNumber + ': ' + reason)}&order_id=${orderId}&link=admin/tickets.php?id=${orderId}`
                });
                const data = await res.json();

                if (data.success) {
                    await fetch('api/chat.php?action=send', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: `receiver_id=${adminId}&message=${encodeURIComponent('🔧 Edit Request for ' + orderNumber + ': ' + reason)}`
                    });
                    alert('✅ Edit request sent to admin!');
                } else {
                    alert('Failed to send request: ' + (data.message || 'Unknown error'));
                }
            } catch (e) {
                alert('Error sending request');
            }
        }

        // ===== EVENT LISTENERS =====

        // Re-render products when search input changes
        document.getElementById('searchInput').addEventListener('input', renderProducts);
        // Reload products when category filter changes
        document.getElementById('categoryFilter').addEventListener('change', loadProducts);

        // ===== CHAT FUNCTIONS =====
        
        let ADMIN_USER_ID = null;
        
        async function openChat() {
            const adminId = await getAdminUserId();
            if (!adminId) {
                alert('Cannot connect to admin');
                return;
            }
            
            document.getElementById('chatModal').classList.add('active');
            loadChatMessages();
            window.chatInterval = setInterval(loadChatMessages, 3000);
        }
        
        function closeChatModal() {
            document.getElementById('chatModal').classList.remove('active');
            if (window.chatInterval) {
                clearInterval(window.chatInterval);
            }
        }
        
        async function loadChatMessages() {
            console.log('Loading messages for admin:', ADMIN_USER_ID);
            if (!ADMIN_USER_ID) return;
            
            try {
                const res = await fetch(`api/chat.php?action=messages&user_id=${ADMIN_USER_ID}`);
                const data = await res.json();
                console.log('Chat response:', data);
                
                const container = document.getElementById('chatMessages');
                if (!data.success) {
                    container.innerHTML = '<div style="text-align:center;color:#888;padding:20px;">Error: ' + (data.message || 'Unknown error') + '</div>';
                    return;
                }
                
                if (!data.messages || data.messages.length === 0) {
                    container.innerHTML = '<div style="text-align:center;color:#888;padding:20px;">No messages yet. Start a conversation!</div>';
                    return;
                }
                
                // Get current user ID from session info
                const currentUserId = <?php echo $_SESSION['user_id']; ?>;
                console.log('Current user ID:', currentUserId);
                
                container.innerHTML = data.messages.map(m => {
                    // Compare sender_id with current user to determine if sent or received
                    const isSent = parseInt(m.sender_id) === currentUserId;
                    const style = isSent 
                        ? 'background:#2563eb;color:white;margin-left:auto;border-radius:10px 10px 0 10px;' 
                        : 'background:#e5e5ea;color:black;border-radius:10px 10px 10px 0;';
                    return `<div style="max-width:70%;padding:10px 14px;margin-bottom:10px;word-wrap:break-word;${style}">
                        <div style="font-size:14px;">${m.message}</div>
                        <div style="font-size:10px;opacity:0.7;margin-top:4px;">${m.created_at ? new Date(m.created_at).toLocaleTimeString() : ''}</div>
                    </div>`;
                }).join('');
                
                container.scrollTop = container.scrollHeight;
            } catch (e) {
                console.error('Error loading chat:', e);
                document.getElementById('chatMessages').innerHTML = '<div style="color:red;padding:20px;">Error loading messages</div>';
            }
        }
        
        async function sendChatMessage() {
            const input = document.getElementById('chatInput');
            const message = input.value.trim();
            
            if (!message || !ADMIN_USER_ID) return;
            
            try {
                const res = await fetch('api/chat.php?action=send', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `receiver_id=${ADMIN_USER_ID}&message=${encodeURIComponent(message)}`
                });
                const data = await res.json();
                console.log('Send response:', data);
                
                if (data.success) {
                    input.value = '';
                    // Refresh immediately after sending
                    setTimeout(loadChatMessages, 100);
                } else {
                    alert('Failed to send: ' + (data.message || 'Unknown error'));
                }
            } catch (e) {
                console.error('Error sending message:', e);
                alert('Error sending message');
            }
        }
        
        // Also refresh chat when opening the modal
        
        document.getElementById('chatInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') sendChatMessage();
        });

        // ===== INITIALIZATION =====
        
        // Load initial data on page load
        loadCategories();
        loadProducts();
    </script>
    <script>if('serviceWorker'in navigator){navigator.serviceWorker.register('sw.js');}</script>
</body>
</html>