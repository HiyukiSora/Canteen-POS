<?php
/**
 * Categories API
 * Handles product category CRUD operations
 * 
 * Endpoints (action parameter):
 * - list: Get all categories
 * - add: Create new category (POST: name, description)
 * - update: Update category (POST: id, name, description)
 * - delete: Delete category by ID
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
    case 'list':
        getCategories();
        break;
    case 'add':
        addCategory();
        break;
    case 'update':
        updateCategory();
        break;
    case 'delete':
        deleteCategory();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get all categories ordered by name
 * @return JSON array of categories
 */
function getCategories() {
    $stmt = db()->query("SELECT * FROM categories ORDER BY name");
    $categories = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'categories' => $categories]);
}

/**
 * Add new category
 * POST data: name (required), description (optional)
 * @return JSON with success status and new category ID
 */
function addCategory() {
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Category name required']);
        return;
    }
    
    db()->query(
        "INSERT INTO categories (name, description) VALUES (?, ?)",
        [$name, $description]
    );
    
    echo json_encode(['success' => true, 'id' => db()->lastInsertId()]);
}

/**
 * Update existing category
 * POST data: id (required), name (required), description (optional)
 * @return JSON with success status
 */
function updateCategory() {
    $id = (int)($_POST['id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    
    if (empty($id) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'ID and name required']);
        return;
    }
    
    db()->query(
        "UPDATE categories SET name = ?, description = ? WHERE id = ?",
        [$name, $description, $id]
    );
    
    echo json_encode(['success' => true]);
}

/**
 * Delete category by ID
 * Query param: id
 * @return JSON with success status
 */
function deleteCategory() {
    $id = (int)($_GET['id'] ?? 0);
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        return;
    }
    
    db()->query("DELETE FROM categories WHERE id = ?", [$id]);
    echo json_encode(['success' => true]);
}