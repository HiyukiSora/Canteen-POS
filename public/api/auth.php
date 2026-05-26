<?php
/**
 * Authentication API
 * Handles user login, logout, and session checking
 * 
 * Endpoints:
 * - ?action=login - Authenticate user
 * - ?action=logout - End user session
 * - ?action=check - Verify current session
 */

session_start();
// Include database configuration
require_once 'config.php';

// Set response header to JSON
header('Content-Type: application/json');

// Get action from URL query string (?action=login)
$action = $_GET['action'] ?? '';

// Route request to appropriate function based on action
switch ($action) {
    case 'login':
        login();
        break;
    case 'logout':
        logout();
        break;
    case 'check':
        checkSession();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Authenticate user with username and password
 * Expected POST data: username, password
 * Returns: success status, user info, redirect URL
 */
function login() {
    // Get and sanitize input
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validate required fields
    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'message' => 'Username and password required']);
        return;
    }

    // Query database for user with matching username
    // Only select active users (is_active = 1)
    $stmt = db()->query(
        "SELECT id, username, password, full_name, role FROM users WHERE username = ? AND is_active = 1",
        [$username]
    );
    $user = $stmt->fetch();

    // Verify password using bcrypt hash
    // password_verify() compares plain text against hashed password
    if (!$user || !password_verify($password, $user['password'])) {
        echo json_encode(['success' => false, 'message' => 'Invalid credentials']);
        return;
    }

    // Store user info in session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];

    // Return success with user data
    echo json_encode([
        'success' => true,
        'user' => [
            'id' => $user['id'],
            'username' => $user['username'],
            'full_name' => $user['full_name'],
            'role' => $user['role']
        ]
    ]);
}

/**
 * Destroy user session (logout)
 * Clears all session variables and destroys the session
 */
function logout() {
    // Destroy the session - removes all session data
    session_destroy();
    echo json_encode(['success' => true]);
}

/**
 * Check if user has valid session
 * Used to verify authentication status on protected pages
 * Returns: user data if logged in, false if not
 */
function checkSession() {
    // Check if user_id exists in session
    if (isset($_SESSION['user_id'])) {
        echo json_encode([
            'success' => true,
            'user' => [
                'id' => $_SESSION['user_id'],
                'username' => $_SESSION['username'],
                'full_name' => $_SESSION['full_name'],
                'role' => $_SESSION['role']
            ]
        ]);
    } else {
        echo json_encode(['success' => false]);
    }
}