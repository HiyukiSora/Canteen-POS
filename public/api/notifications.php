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
        getNotifications();
        break;
    case 'unread':
        getUnreadCount();
        break;
    case 'mark_read':
        markAsRead();
        break;
    case 'create':
        createNotification();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

function getNotifications() {
    $stmt = db()->query(
        "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 50",
        [$_SESSION['user_id']]
    );
    $notifications = $stmt->fetchAll();
    echo json_encode(['success' => true, 'notifications' => $notifications]);
}

function getUnreadCount() {
    $stmt = db()->query(
        "SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0",
        [$_SESSION['user_id']]
    );
    echo json_encode(['success' => true, 'count' => (int)$stmt->fetch()['count']]);
}

function markAsRead() {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        db()->query("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?", [$id, $_SESSION['user_id']]);
    } else {
        db()->query("UPDATE notifications SET is_read = 1 WHERE user_id = ?", [$_SESSION['user_id']]);
    }
    echo json_encode(['success' => true]);
}

function createNotification() {
    $user_id = (int)($_POST['user_id'] ?? 0);
    $type = sanitize($_POST['type'] ?? 'info');
    $message = sanitize($_POST['message'] ?? '');
    $order_id = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;
    $link = sanitize($_POST['link'] ?? '');

    if (empty($user_id) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'User ID and message required']);
        return;
    }

    db()->query(
        "INSERT INTO notifications (user_id, type, message, order_id, link) VALUES (?, ?, ?, ?, ?)",
        [$user_id, $type, $message, $order_id, $link]
    );

    echo json_encode(['success' => true, 'id' => db()->lastInsertId()]);
}
