<?php
/**
 * Users API
 * Handles user management (admin only operations)
 * 
 * Endpoints (action parameter):
 * - list: Get all users (admin only)
 * - add: Create new user (admin only)
 * - update: Update user details (admin only)
 * - delete: Deactivate user (admin only)
 * - cashiers: Get list of active cashiers for chat
 * - admin: Get admin user info (for cashier chat)
 */

session_start();
require_once '../api/config.php';

header('Content-Type: application/json');

// SECURITY: Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// SECURITY: Some actions require admin role
$action = $_GET['action'] ?? '';
$adminOnlyActions = ['list', 'add', 'update', 'delete'];

if (in_array($action, $adminOnlyActions) && $_SESSION['role'] !== 'admin') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized - Admin only']);
    exit;
}

switch ($action) {
    case 'list':
        getUsers();
        break;
    case 'add':
        addUser();
        break;
    case 'update':
        updateUser();
        break;
    case 'delete':
        deleteUser();
        break;
    case 'cashiers':
        getCashiers();
        break;
    case 'admin':
        getAdmin();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get all users (admin view)
 * Removes password hash from results for security
 * @return JSON array of users (without passwords)
 */
function getUsers() {
    $stmt = db()->query("SELECT * FROM users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
    
    // Remove password field from each user for security
    foreach ($users as &$user) {
        unset($user['password']);
    }
    
    echo json_encode(['success' => true, 'users' => $users]);
}

/**
 * Get active cashiers only (for chat functionality)
 * @return JSON array of cashiers
 */
function getCashiers() {
    $stmt = db()->query("SELECT id, username, full_name, role FROM users WHERE role = 'cashier' AND is_active = 1 ORDER BY full_name");
    $users = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'users' => $users]);
}

/**
 * Get admin user for cashier chat
 * Returns the first admin user that the cashier can chat with
 * @return JSON with admin user info
 */
function getAdmin() {
    $stmt = db()->query("SELECT id, username, full_name, role FROM users WHERE role = 'admin' AND is_active = 1 LIMIT 1");
    $admin = $stmt->fetch();
    
    if (!$admin) {
        echo json_encode(['success' => false, 'message' => 'No admin found']);
        return;
    }
    
    echo json_encode(['success' => true, 'admin' => $admin]);
}

/**
 * Create new user (add cashiers or admins)
 * POST data: username, full_name, role, password
 * @return JSON with success status and new user ID
 */
function addUser() {
    $username = sanitize($_POST['username'] ?? '');
    $full_name = sanitize($_POST['full_name'] ?? '');
    $role = sanitize($_POST['role'] ?? 'cashier');
    $password = $_POST['password'] ?? '';
    
    // Validate required fields
    if (empty($username) || empty($full_name) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'All fields required']);
        return;
    }
    
    // Check if username already exists
    $check = db()->query("SELECT id FROM users WHERE username = ?", [$username]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        return;
    }
    
    // Hash password using bcrypt
    $hash = password_hash($password, PASSWORD_DEFAULT);
    
    db()->query(
        "INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)",
        [$username, $hash, $full_name, $role]
    );
    
    echo json_encode(['success' => true, 'id' => db()->lastInsertId()]);
}

/**
 * Update existing user
 * POST data: id, username, full_name, role, password (optional - only if changing)
 * @return JSON with success status
 */
function updateUser() {
    $id = (int)($_POST['id'] ?? 0);
    $username = sanitize($_POST['username'] ?? '');
    $full_name = sanitize($_POST['full_name'] ?? '');
    $role = sanitize($_POST['role'] ?? 'cashier');
    $password = $_POST['password'] ?? '';
    
    // Validate required fields
    if (empty($id) || empty($username) || empty($full_name)) {
        echo json_encode(['success' => false, 'message' => 'ID, username, and full name required']);
        return;
    }
    
    // Check username uniqueness (exclude current user)
    $check = db()->query("SELECT id FROM users WHERE username = ? AND id != ?", [$username, $id]);
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Username already exists']);
        return;
    }
    
    // Update with or without password change
    if ($password) {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db()->query(
            "UPDATE users SET username = ?, full_name = ?, role = ?, password = ? WHERE id = ?",
            [$username, $full_name, $role, $hash, $id]
        );
    } else {
        db()->query(
            "UPDATE users SET username = ?, full_name = ?, role = ? WHERE id = ?",
            [$username, $full_name, $role, $id]
        );
    }
    
    echo json_encode(['success' => true]);
}

/**
 * Deactivate user (soft delete - sets is_active = 0)
 * Prevents admin from deleting their own account
 * Query param: id
 * @return JSON with success status
 */
function deleteUser() {
    $id = (int)($_GET['id'] ?? 0);
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        return;
    }
    
    // Prevent admin from deleting their own account
    if ($id === $_SESSION['user_id']) {
        echo json_encode(['success' => false, 'message' => 'Cannot delete your own account']);
        return;
    }
    
    // Soft delete - set is_active to 0
    db()->query("UPDATE users SET is_active = 0 WHERE id = ?", [$id]);
    echo json_encode(['success' => true]);
}