<?php
/**
 * Orders API
 * Handles order creation, listing, retrieval, cancellation, and daily statistics
 * 
 * Endpoints (action parameter):
 * - create: Create new order from cart (POST JSON data)
 * - list: Get orders with pagination and filters (page, limit, status, date)
 * - get: Get single order by ID
 * - cancel: Cancel an order and restore stock
 * - daily: Get daily sales statistics
 */

session_start();
require_once '../api/config.php';

header('Content-Type: application/json');

// SECURITY: Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'create':
        createOrder();
        break;
    case 'list':
        getOrders();
        break;
    case 'get':
        getOrder();
        break;
    case 'cancel':
        cancelOrder();
        break;
    case 'daily':
        getDailyStats();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Create new order from cart data
 * 
 * Expected JSON payload:
 * {
 *   "total_amount": 150.00,
 *   "cash_tendered": 200.00,
 *   "change_given": 50.00,
 *   "items": [
 *     {
 *       "product_id": 1,
 *       "variant_id": 2 (optional),
 *       "quantity": 2,
 *       "unit_price": 75.00,
 *       "subtotal": 150.00
 *     }
 *   ]
 * }
 * 
 * Actions:
 * 1. Validates order has items and payment is sufficient
 * 2. Creates order record with generated order number
 * 3. Creates order_items for each cart item
 * 4. Decrements variant stock for each item
 * 
 * @return JSON with success status and order details
 */
function createOrder() {
    // Read JSON input from request body
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Extract order data
    $total = (float)($data['total_amount'] ?? 0);
    $cash = (float)($data['cash_tendered'] ?? 0);
    $change = (float)($data['change_given'] ?? 0);
    $customer_name = sanitize($data['customer_name'] ?? '');
    $items = $data['items'] ?? [];
    
    // Validate order has items
    if (empty($items)) {
        echo json_encode(['success' => false, 'message' => 'No items in order']);
        return;
    }
    
    // Validate customer paid enough
    if ($cash < $total) {
        echo json_encode(['success' => false, 'message' => 'Insufficient payment']);
        return;
    }
    
    // Generate unique order number: ORD-YYYYMMDD-XXXX
    $orderNumber = generateOrderNumber();
    
    try {
        // Insert order record
        db()->query(
            "INSERT INTO orders (order_number, user_id, customer_name, total_amount, cash_tendered, change_given, status) 
             VALUES (?, ?, ?, ?, ?, ?, 'completed')",
            [$orderNumber, $_SESSION['user_id'], $customer_name ?: null, $total, $cash, $change]
        );
        
        // Get the new order's ID
        $orderId = db()->lastInsertId();
        
        // Insert each item and update stock
        foreach ($items as $item) {
            // Create order_item record
            db()->query(
                "INSERT INTO order_items (order_id, product_id, variant_id, quantity, unit_price, subtotal) 
                 VALUES (?, ?, ?, ?, ?, ?)",
                [
                    $orderId,
                    $item['product_id'],
                    $item['variant_id'] ?: null,  // null if no variant
                    $item['quantity'],
                    $item['unit_price'],
                    $item['subtotal']
                ]
            );
            
            // Decrement stock
            if (!empty($item['variant_id'])) {
                db()->query(
                    "UPDATE product_variants SET stock = stock - ? WHERE id = ?",
                    [$item['quantity'], $item['variant_id']]
                );
            } elseif (!empty($item['product_id'])) {
                db()->query(
                    "UPDATE products SET stock = GREATEST(stock - ?, 0) WHERE id = ? AND stock IS NOT NULL",
                    [$item['quantity'], $item['product_id']]
                );
            }
        }
        
        // Return success with order details
        echo json_encode([
            'success' => true,
            'order' => [
                'id' => $orderId,
                'order_number' => $orderNumber,
                'total_amount' => $total,
                'cash_tendered' => $cash,
                'change_given' => $change
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Get orders with pagination and optional filters
 * 
 * Query params:
 * - page: Page number (default: 1)
 * - limit: Items per page (default: 20)
 * - status: Filter by status (completed, cancelled)
 * - date: Filter by date (YYYY-MM-DD)
 * 
 * Each order includes its items array
 * 
 * @return JSON with orders array and pagination info
 */
function getOrders() {
    // Get pagination params
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    
    // Get filter params
    $status = $_GET['status'] ?? '';
    $date = $_GET['date'] ?? '';
    $userId = $_GET['user_id'] ?? '';
    
    // Build WHERE clause
    $where = "1=1";
    $params = [];
    
    // Add status filter
    if ($status) {
        $where .= " AND o.status = ?";
        $params[] = $status;
    }
    
    // Add date filter
    if ($date) {
        $where .= " AND DATE(o.created_at) = ?";
        $params[] = $date;
    }
    
    // Add user filter
    if ($userId) {
        $where .= " AND o.user_id = ?";
        $params[] = $userId;
    }
    
    // Get total count for pagination
    $countStmt = db()->query("SELECT COUNT(*) as total FROM orders o WHERE $where", $params);
    $total = $countStmt->fetch()['total'];
    
    // Get paginated orders with user info
    $stmt = db()->query(
        "SELECT o.*, u.username, u.full_name FROM orders o 
         JOIN users u ON o.user_id = u.id 
         WHERE $where ORDER BY o.created_at DESC LIMIT $limit OFFSET $offset",
        $params
    );
    
    $orders = $stmt->fetchAll();
    
    // Get items for each order
    foreach ($orders as &$order) {
        $itemsStmt = db()->query(
            "SELECT oi.*, p.name as product_name, pv.variant_value 
             FROM order_items oi 
             JOIN products p ON oi.product_id = p.id 
             LEFT JOIN product_variants pv ON oi.variant_id = pv.id 
             WHERE oi.order_id = ?",
            [$order['id']]
        );
        $order['items'] = $itemsStmt->fetchAll();
    }
    
    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $limit)
    ]);
}

/**
 * Get single order by ID with all details
 * 
 * Query params:
 * - id: Order ID to retrieve
 * 
 * @return JSON with order object including items
 */
function getOrder() {
    $id = (int)($_GET['id'] ?? 0);
    
    // Get order with user info
    $stmt = db()->query(
        "SELECT o.*, u.username, u.full_name FROM orders o 
         JOIN users u ON o.user_id = u.id WHERE o.id = ?",
        [$id]
    );
    $order = $stmt->fetch();
    
    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        return;
    }
    
    // Get order items with product and variant info
    $itemsStmt = db()->query(
        "SELECT oi.*, p.name as product_name, pv.variant_value 
         FROM order_items oi 
         JOIN products p ON oi.product_id = p.id 
         LEFT JOIN product_variants pv ON oi.variant_id = pv.id 
         WHERE oi.order_id = ?",
        [$id]
    );
    $order['items'] = $itemsStmt->fetchAll();
    
    echo json_encode(['success' => true, 'order' => $order]);
}

/**
 * Cancel an order and restore stock
 * 
 * POST data:
 * - id: Order ID to cancel
 * 
 * Actions:
 * 1. Gets all items in the order
 * 2. Restores variant stock for each item
 * 3. Updates order status to 'cancelled'
 * 
 * @return JSON with success status
 */
function cancelOrder() {
    $id = (int)($_POST['id'] ?? 0);
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'Order ID required']);
        return;
    }
    
    // Get all items in this order
    $stmt = db()->query("SELECT * FROM order_items WHERE order_id = ?", [$id]);
    $items = $stmt->fetchAll();
    
    // Restore stock for each item
    foreach ($items as $item) {
        if ($item['variant_id']) {
            db()->query(
                "UPDATE product_variants SET stock = stock + ? WHERE id = ?",
                [$item['quantity'], $item['variant_id']]
            );
        }
    }
    
    // Mark order as cancelled
    db()->query("UPDATE orders SET status = 'cancelled' WHERE id = ?", [$id]);
    
    echo json_encode(['success' => true]);
}

/**
 * Get daily sales statistics for dashboard
 * 
 * Returns:
 * - today: Today's orders count and total sales
 * - yesterday: Yesterday's stats for comparison
 * - week: Last 7 days stats
 * - top_products: Top 10 selling products today
 * 
 * @return JSON with all statistics
 */
function getDailyStats() {
    $today = date('Y-m-d');
    $category_id = $_GET['category_id'] ?? null;
    $fromFilter = "";
    $params = [];

    if ($category_id) {
        $fromFilter = " AND o.id IN (SELECT DISTINCT oi2.order_id FROM order_items oi2 JOIN products p2 ON oi2.product_id = p2.id WHERE p2.category_id = ?)";
        $params[] = $category_id;
    }

    $stmt = db()->query(
        "SELECT COUNT(*) as total_orders, COALESCE(SUM(o.total_amount), 0) as total_sales 
         FROM orders o WHERE DATE(o.created_at) = ? AND o.status = 'completed'$fromFilter",
        array_merge($params, [$today])
    );
    $todayStats = $stmt->fetch();

    $yesterday = date('Y-m-d', strtotime('-1 day'));
    $stmt = db()->query(
        "SELECT COUNT(*) as total_orders, COALESCE(SUM(o.total_amount), 0) as total_sales 
         FROM orders o WHERE DATE(o.created_at) = ? AND o.status = 'completed'$fromFilter",
        array_merge($params, [$yesterday])
    );
    $yesterdayStats = $stmt->fetch();

    $weekStart = date('Y-m-d', strtotime('-7 days'));
    $stmt = db()->query(
        "SELECT COUNT(*) as total_orders, COALESCE(SUM(o.total_amount), 0) as total_sales 
         FROM orders o WHERE DATE(o.created_at) >= ? AND o.status = 'completed'$fromFilter",
        array_merge($params, [$weekStart])
    );
    $weekStats = $stmt->fetch();

    $topFromFilter = "";
    $topParams = [$today];
    if ($category_id) {
        $topFromFilter = " AND oi.product_id IN (SELECT id FROM products WHERE category_id = ?)";
        $topParams[] = $category_id;
    }
    $stmt = db()->query(
        "SELECT p.name, SUM(oi.quantity) as total_sold, SUM(oi.subtotal) as total_revenue
         FROM order_items oi 
         JOIN products p ON oi.product_id = p.id
         JOIN orders o ON oi.order_id = o.id
         WHERE DATE(o.created_at) = ? AND o.status = 'completed'$topFromFilter
         GROUP BY p.id
         ORDER BY total_sold DESC LIMIT 10",
        $topParams
    );
    $topProducts = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'today' => $todayStats,
        'yesterday' => $yesterdayStats,
        'week' => $weekStats,
        'top_products' => $topProducts
    ]);
}