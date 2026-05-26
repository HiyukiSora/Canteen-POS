<?php
session_start();
require_once '../api/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'list':
        getTickets();
        break;
    case 'get':
        getTicket();
        break;
    case 'edit':
        editOrder();
        break;
    case 'history':
        getEditHistory();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getTickets() {
    $page = (int)($_GET['page'] ?? 1);
    $limit = (int)($_GET['limit'] ?? 20);
    $offset = ($page - 1) * $limit;
    $search = $_GET['search'] ?? '';
    $date = $_GET['date'] ?? '';

    $where = "1=1";
    $params = [];

    if ($search) {
        $where .= " AND (o.order_number LIKE ? OR u.full_name LIKE ?)";
        $params[] = "%$search%";
        $params[] = "%$search%";
    }

    if ($date) {
        $where .= " AND DATE(o.created_at) = ?";
        $params[] = $date;
    }

    $countStmt = db()->query(
        "SELECT COUNT(*) as total FROM orders o JOIN users u ON o.user_id = u.id WHERE $where",
        $params
    );
    $total = $countStmt->fetch()['total'];

    $stmt = db()->query(
        "SELECT o.*, u.username, u.full_name FROM orders o
         JOIN users u ON o.user_id = u.id
         WHERE $where ORDER BY o.created_at DESC LIMIT $limit OFFSET $offset",
        $params
    );
    $orders = $stmt->fetchAll();

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

        $editStmt = db()->query("SELECT COUNT(*) as count FROM order_edits WHERE order_id = ?", [$order['id']]);
        $order['edit_count'] = (int)$editStmt->fetch()['count'];
    }

    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'total' => $total,
        'page' => $page,
        'pages' => ceil($total / $limit)
    ]);
}

function getTicket() {
    $id = (int)($_GET['id'] ?? 0);

    $stmt = db()->query(
        "SELECT o.*, u.username, u.full_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ?",
        [$id]
    );
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        return;
    }

    $itemsStmt = db()->query(
        "SELECT oi.*, p.name as product_name, pv.variant_value, pv.stock
         FROM order_items oi
         JOIN products p ON oi.product_id = p.id
         LEFT JOIN product_variants pv ON oi.variant_id = pv.id
         WHERE oi.order_id = ?",
        [$id]
    );
    $order['items'] = $itemsStmt->fetchAll();

    $historyStmt = db()->query(
        "SELECT oe.*, u.full_name as edited_by_name
         FROM order_edits oe
         JOIN users u ON oe.edited_by = u.id
         WHERE oe.order_id = ? ORDER BY oe.created_at DESC",
        [$id]
    );
    $order['edit_history'] = $historyStmt->fetchAll();

    echo json_encode(['success' => true, 'order' => $order]);
}

function editOrder() {
    if ($_SESSION['role'] !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Admin only']);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);
    $order_id = (int)($data['order_id'] ?? 0);
    $items = $data['items'] ?? [];
    $reason = sanitize($data['reason'] ?? '');

    if (empty($order_id) || empty($items)) {
        echo json_encode(['success' => false, 'message' => 'Order ID and items required']);
        return;
    }

    $stmt = db()->query("SELECT * FROM orders WHERE id = ?", [$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found']);
        return;
    }

    $oldItems = db()->query(
        "SELECT oi.*, pv.stock FROM order_items oi LEFT JOIN product_variants pv ON oi.variant_id = pv.id WHERE oi.order_id = ?",
        [$order_id]
    )->fetchAll();

    try {
        db()->getConnection()->beginTransaction();

        // Restore original stock for each old item
        foreach ($oldItems as $oldItem) {
            if ($oldItem['variant_id']) {
                db()->query(
                    "UPDATE product_variants SET stock = stock + ? WHERE id = ?",
                    [$oldItem['quantity'], $oldItem['variant_id']]
                );
            }
        }

        // Remove old order items
        db()->query("DELETE FROM order_items WHERE order_id = ?", [$order_id]);

        // Calculate new totals
        $newTotal = 0;

        // Insert new items and deduct new stock
        foreach ($items as $item) {
            $product_id = (int)($item['product_id'] ?? 0);
            $variant_id = !empty($item['variant_id']) ? (int)$item['variant_id'] : null;
            $quantity = (int)($item['quantity'] ?? 1);
            $unit_price = (float)($item['unit_price'] ?? 0);
            $subtotal = $unit_price * $quantity;
            $newTotal += $subtotal;

            db()->query(
                "INSERT INTO order_items (order_id, product_id, variant_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?)",
                [$order_id, $product_id, $variant_id, $quantity, $unit_price, $subtotal]
            );

            // Deduct new stock
            if ($variant_id) {
                db()->query(
                    "UPDATE product_variants SET stock = stock - ? WHERE id = ?",
                    [$quantity, $variant_id]
                );
            }
        }

        // Update order total. Keep cash_tendered and change_given same, but mark as edited.
        $cashDiff = $newTotal - (float)$order['total_amount'];
        $newChange = (float)$order['change_given'] + $cashDiff;

        db()->query(
            "UPDATE orders SET total_amount = ?, change_given = ? WHERE id = ?",
            [$newTotal, $newChange, $order_id]
        );

        // Save edit audit trail
        db()->query(
            "INSERT INTO order_edits (order_id, edited_by, edit_data, reason) VALUES (?, ?, ?, ?)",
            [
                $order_id,
                $_SESSION['user_id'],
                json_encode(['old_total' => $order['total_amount'], 'new_total' => $newTotal, 'items' => $items]),
                $reason
            ]
        );

        db()->getConnection()->commit();

        echo json_encode(['success' => true, 'new_total' => $newTotal]);
    } catch (Exception $e) {
        db()->getConnection()->rollBack();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

function getEditHistory() {
    $order_id = (int)($_GET['order_id'] ?? 0);

    if (empty($order_id)) {
        echo json_encode(['success' => false, 'message' => 'Order ID required']);
        return;
    }

    $stmt = db()->query(
        "SELECT oe.*, u.full_name as edited_by_name
         FROM order_edits oe
         JOIN users u ON oe.edited_by = u.id
         WHERE oe.order_id = ?
         ORDER BY oe.created_at DESC",
        [$order_id]
    );

    echo json_encode(['success' => true, 'history' => $stmt->fetchAll()]);
}
