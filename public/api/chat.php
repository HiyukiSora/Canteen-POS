<?php
/**
 * Chat API
 * Handles messaging between admin and cashiers
 * 
 * Endpoints (action parameter):
 * - messages: Get chat messages between current user and another user
 * - send: Send a message to another user
 * - unread: Get count of unread messages
 * - test: Debug endpoint to test functionality
 */

session_start();
require_once '../api/config.php';

header('Content-Type: application/json');

// Debug: Log all requests
error_log("Chat API called: " . ($_GET['action'] ?? 'none') . " by user: " . ($_SESSION['user_id'] ?? 'not logged in'));

// SECURITY: Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - not logged in', 'debug' => ['session' => $_SESSION]]);
    exit;
}

$action = $_GET['action'] ?? '';

switch ($action) {
    case 'messages':
        getMessages();
        break;
    case 'send':
        sendMessage();
        break;
    case 'unread':
        getUnreadCount();
        break;
    case 'test':
        testChat();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action: ' . $action]);
}

/**
 * Test endpoint - returns debug info about chat system
 */
function testChat() {
    $current_user = $_SESSION['user_id'];
    
    // Get all messages
    $stmt = db()->query("SELECT * FROM chat_messages ORDER BY created_at DESC LIMIT 10");
    $allMessages = $stmt->fetchAll();
    
    // Get users
    $stmt = db()->query("SELECT id, username, role FROM users WHERE is_active = 1");
    $users = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'debug' => [
            'current_user_id' => $current_user,
            'all_messages' => $allMessages,
            'users' => $users,
            'session' => $_SESSION
        ]
    ]);
}

/**
 * Get chat messages between current user and another user
 */
function getMessages() {
    $user_id = (int)($_GET['user_id'] ?? 0);
    $current_user = $_SESSION['user_id'];
    
    if (empty($user_id)) {
        echo json_encode(['success' => false, 'message' => 'User ID required']);
        return;
    }
    
    // Get messages in both directions
    $stmt = db()->query(
        "SELECT * FROM chat_messages 
         WHERE (sender_id = ? AND receiver_id = ?) OR (sender_id = ? AND receiver_id = ?)
         ORDER BY created_at ASC",
        [$current_user, $user_id, $user_id, $current_user]
    );
    
    $messages = $stmt->fetchAll();
    
    // Mark as read
    db()->query(
        "UPDATE chat_messages SET is_read = 1 WHERE sender_id = ? AND receiver_id = ?",
        [$user_id, $current_user]
    );
    
    echo json_encode([
        'success' => true, 
        'messages' => $messages,
        'debug' => ['requested_user_id' => $user_id, 'current_user' => $current_user]
    ]);
}

/**
 * Send a message to another user
 */
function sendMessage() {
    $receiver_id = (int)($_POST['receiver_id'] ?? 0);
    $message = sanitize($_POST['message'] ?? '');
    
    if (empty($receiver_id) || empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Receiver and message required', 'debug' => ['receiver_id' => $receiver_id, 'message_length' => strlen($message)]]);
        return;
    }
    
    $sender_id = $_SESSION['user_id'];
    
    db()->query(
        "INSERT INTO chat_messages (sender_id, receiver_id, message) VALUES (?, ?, ?)",
        [$sender_id, $receiver_id, $message]
    );
    
    $lastId = db()->lastInsertId();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Message sent',
        'debug' => ['sender' => $sender_id, 'receiver' => $receiver_id, 'insert_id' => $lastId]
    ]);
}

/**
 * Get count of unread messages
 */
function getUnreadCount() {
    $stmt = db()->query(
        "SELECT COUNT(*) as count FROM chat_messages WHERE receiver_id = ? AND is_read = 0",
        [$_SESSION['user_id']]
    );
    
    echo json_encode(['success' => true, 'count' => $stmt->fetch()['count']]);
}