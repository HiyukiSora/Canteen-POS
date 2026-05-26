<?php
session_start();
require_once '../api/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'report':
        getSalesReport();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getSalesReport() {
    $from = $_GET['from'] ?? date('Y-m-d', strtotime('-7 days'));
    $to = $_GET['to'] ?? date('Y-m-d');
    
    $stmt = db()->query(
        "SELECT COUNT(*) as total_orders, COALESCE(SUM(total_amount), 0) as total_sales 
         FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status = 'completed'",
        [$from, $to]
    );
    $stats = $stmt->fetch();
    
    $stmt = db()->query(
        "SELECT p.name, SUM(oi.quantity) as total_sold, SUM(oi.subtotal) as total_revenue
         FROM order_items oi 
         JOIN products p ON oi.product_id = p.id
         JOIN orders o ON oi.order_id = o.id
         WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status = 'completed'
         GROUP BY p.id
         ORDER BY total_revenue DESC",
        [$from, $to]
    );
    $products = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'total_orders' => $stats['total_orders'],
        'total_sales' => $stats['total_sales'],
        'products' => $products
    ]);
}