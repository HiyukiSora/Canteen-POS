<?php
/**
 * Variants API
 * Handles variant types and product variants (e.g., Size, Flavor options)
 * 
 * Variant Types (templates for variants):
 * - types: List all variant types
 * - addType: Create variant type with predefined values (e.g., Size: Small, Medium, Large)
 * - updateType: Update variant type
 * - deleteType: Delete variant type
 * 
 * Product Variants (actual variant options for products):
 * - list: Get variants for a specific product
 * - add: Add variant to a product
 * - update: Update variant details
 * - delete: Soft delete variant
 * - updateStock: Update stock quantity
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
    case 'types':
        getVariantTypes();
        break;
    case 'addType':
        addVariantType();
        break;
    case 'updateType':
        updateVariantType();
        break;
    case 'deleteType':
        deleteVariantType();
        break;
    case 'list':
        getProductVariants();
        break;
    case 'add':
        addVariant();
        break;
    case 'update':
        updateVariant();
        break;
    case 'delete':
        deleteVariant();
        break;
    case 'deleteByProduct':
        deleteVariantsByProduct();
        break;
    case 'updateStock':
        updateStock();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get all variant types (e.g., Size, Flavor, Color)
 * Decodes JSON values to array for frontend use
 * @return JSON array of variant types with decoded values
 */
function getVariantTypes() {
    $stmt = db()->query("SELECT * FROM variant_types ORDER BY name");
    $types = $stmt->fetchAll();
    
    // Decode JSON values or split comma-separated values
    foreach ($types as &$type) {
        $val = $type['variant_values'];
        $decoded = json_decode($val, true);
        $type['values'] = is_array($decoded) ? $decoded : explode(',', $val);
    }
    
    echo json_encode(['success' => true, 'types' => $types]);
}

/**
 * Add new variant type (template for variants)
 * POST data: name (e.g., "Size"), values array (e.g., ["Small", "Medium", "Large"])
 * @return JSON with success status and new type ID
 */
function addVariantType() {
    $name = sanitize($_POST['name'] ?? '');
    $values = $_POST['values'] ?? [];
    
    if (empty($name) || empty($values)) {
        echo json_encode(['success' => false, 'message' => 'Name and values required']);
        return;
    }
    
    // Store values as JSON
    db()->query(
        "INSERT INTO variant_types (name, variant_values) VALUES (?, ?)",
        [$name, json_encode($values)]
    );
    
    echo json_encode(['success' => true, 'id' => db()->lastInsertId()]);
}

/**
 * Update variant type
 * POST data: id, name, values array
 * @return JSON with success status
 */
function updateVariantType() {
    $id = (int)($_POST['id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');
    $values = $_POST['values'] ?? [];
    
    if (empty($id) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'ID and name required']);
        return;
    }
    
    db()->query(
        "UPDATE variant_types SET name = ?, variant_values = ? WHERE id = ?",
        [$name, json_encode($values), $id]
    );
    
    echo json_encode(['success' => true]);
}

/**
 * Delete variant type by ID
 * Query param: id
 * @return JSON with success status
 */
function deleteVariantType() {
    $id = (int)($_GET['id'] ?? 0);
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        return;
    }
    
    db()->query("DELETE FROM variant_types WHERE id = ?", [$id]);
    echo json_encode(['success' => true]);
}

/**
 * Get all variants for a specific product
 * Query param: product_id
 * @return JSON array of product variants
 */
function getProductVariants() {
    $product_id = (int)($_GET['product_id'] ?? 0);
    
    if (empty($product_id)) {
        echo json_encode(['success' => false, 'message' => 'Product ID required']);
        return;
    }
    
    $stmt = db()->query(
        "SELECT pv.*, vt.name as variant_type_name, vt.variant_values as variant_type_values
         FROM product_variants pv 
         JOIN variant_types vt ON pv.variant_type_id = vt.id 
         WHERE pv.product_id = ? AND pv.is_active = 1",
        [$product_id]
    );
    
    $variants = $stmt->fetchAll();
    
    // Decode variant type values
    foreach ($variants as &$variant) {
        $val = $variant['variant_type_values'];
        $decoded = json_decode($val, true);
        $variant['variant_type_values'] = is_array($decoded) ? $decoded : explode(',', $val);
    }
    
    echo json_encode(['success' => true, 'variants' => $variants]);
}

/**
 * Add variant to a product
 * POST data: product_id, variant_type_id, variant_value, sku, price_modifier, stock
 * @return JSON with success status and new variant ID
 */
function addVariant() {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $variant_type_id = (int)($_POST['variant_type_id'] ?? 0);
    $variant_value = sanitize($_POST['variant_value'] ?? '');
    $sku = sanitize($_POST['sku'] ?? '');
    $price_modifier = (float)($_POST['price_modifier'] ?? 0);
    $price = isset($_POST['price']) && $_POST['price'] !== '' ? (float)$_POST['price'] : null;
    $stock = (int)($_POST['stock'] ?? 0);
    
    if (empty($product_id) || empty($variant_type_id) || empty($variant_value)) {
        echo json_encode(['success' => false, 'message' => 'Product, variant type, and value required']);
        return;
    }
    
    db()->query(
        "INSERT INTO product_variants (product_id, variant_type_id, variant_value, sku, price_modifier, price, stock) 
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$product_id, $variant_type_id, $variant_value, $sku, $price_modifier, $price, $stock]
    );
    
    echo json_encode(['success' => true, 'id' => db()->lastInsertId()]);
}

/**
 * Update existing variant
 * POST data: id, variant_value, sku, price_modifier, stock, is_active
 * @return JSON with success status
 */
function updateVariant() {
    $id = (int)($_POST['id'] ?? 0);
    $variant_value = sanitize($_POST['variant_value'] ?? '');
    $sku = sanitize($_POST['sku'] ?? '');
    $price_modifier = (float)($_POST['price_modifier'] ?? 0);
    $price = isset($_POST['price']) && $_POST['price'] !== '' ? (float)$_POST['price'] : null;
    $stock = (int)($_POST['stock'] ?? 0);
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    
    if (empty($id) || empty($variant_value)) {
        echo json_encode(['success' => false, 'message' => 'ID and variant value required']);
        return;
    }
    
    db()->query(
        "UPDATE product_variants SET variant_value = ?, sku = ?, price_modifier = ?, price = ?, stock = ?, is_active = ? WHERE id = ?",
        [$variant_value, $sku, $price_modifier, $price, $stock, $is_active, $id]
    );
    
    echo json_encode(['success' => true]);
}

/**
 * Soft delete variant (set is_active = 0)
 * Query param: id
 * @return JSON with success status
 */
function deleteVariant() {
    $id = (int)($_GET['id'] ?? 0);
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        return;
    }
    
    db()->query("UPDATE product_variants SET is_active = 0 WHERE id = ?", [$id]);
    echo json_encode(['success' => true]);
}

/**
 * Update variant stock quantity
 * POST data: id, stock
 * Used for quick stock adjustments in admin panel
 * @return JSON with success status
 */
function updateStock() {
    $id = (int)($_POST['id'] ?? 0);
    $stock = (int)($_POST['stock'] ?? 0);
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        return;
    }
    
    db()->query("UPDATE product_variants SET stock = ? WHERE id = ?", [$stock, $id]);
    echo json_encode(['success' => true]);
}

/**
 * Soft-delete all variants for a product
 * Used when editing a product to replace all its variants
 * GET param: product_id
 */
function deleteVariantsByProduct() {
    $product_id = (int)($_GET['product_id'] ?? 0);
    if (empty($product_id)) {
        echo json_encode(['success' => false, 'message' => 'Product ID required']);
        return;
    }
    db()->query("UPDATE product_variants SET is_active = 0 WHERE product_id = ?", [$product_id]);
    echo json_encode(['success' => true]);
}