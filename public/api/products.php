<?php
/**
 * Products API
 * Handles all product-related operations: CRUD, listing, searching
 * 
 * Endpoints (action parameter):
 * - list / pos: Get all products (pos includes variant data)
 * - get: Get single product by ID
 * - add: Create new product (POST data: name, description, category_id, base_price, image)
 * - update: Update existing product (POST data: id, name, description, category_id, base_price, is_active, image)
 * - delete: Soft delete product (sets is_active = 0)
 * - search: Search products by name
 */

// Start session - required for authentication check
session_start();

// Include database configuration
require_once '../api/config.php';

// Set response header to JSON
header('Content-Type: application/json');

// SECURITY: Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Get action from URL query string
$action = $_GET['action'] ?? '';

// Route to appropriate function
switch ($action) {
    case 'list':
    case 'pos':
        getProducts();
        break;
    case 'get':
        getProduct();
        break;
    case 'add':
        addProduct();
        break;
    case 'update':
        updateProduct();
        break;
    case 'delete':
        deleteProduct();
        break;
    case 'search':
        searchProducts();
        break;
    case 'stocks':
        getStocks();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}

/**
 * Get all products, optionally filtered by category
 * 
 * Query params:
 * - category_id (optional): Filter by category
 * - include_variants (optional): Include variant data in response
 * 
 * @return JSON list of products with optional variants
 */
function getProducts() {
    // Get optional filters from URL
    $category_id = $_GET['category_id'] ?? null;
    $include_variants = $_GET['include_variants'] ?? false;
    
    // Base SQL: get products with category name
    $sql = "SELECT p.*, c.name as category_name FROM products p 
            LEFT JOIN categories c ON p.category_id = c.id 
            WHERE p.is_active = 1";
    $params = [];
    
    // Add category filter if specified
    if ($category_id) {
        $sql .= " AND p.category_id = ?";
        $params[] = $category_id;
    }
    
    // Order by name alphabetically
    $sql .= " ORDER BY p.name";
    
    // Execute query
    $stmt = db()->query($sql, $params);
    $products = $stmt->fetchAll();
    
    // If include_variants is true, fetch variants for each product
    // Variants are product options like Small/Medium/Large
    if ($include_variants) {
        foreach ($products as &$product) {
            $variantStmt = db()->query(
                "SELECT pv.*, vt.name as variant_type_name 
                 FROM product_variants pv 
                 JOIN variant_types vt ON pv.variant_type_id = vt.id 
                 WHERE pv.product_id = ? AND pv.is_active = 1",
                [$product['id']]
            );
            $product['variants'] = $variantStmt->fetchAll();
            foreach ($product['variants'] as &$variant) {
                $variant['effective_price'] = $variant['price'] !== null
                    ? (float)$variant['price']
                    : (float)$product['base_price'] + (float)$variant['price_modifier'];
            }
        }
    }
    
    echo json_encode(['success' => true, 'products' => $products]);
}

/**
 * Get single product by ID with variants
 * 
 * Query params:
 * - id: Product ID to retrieve
 * 
 * @return JSON product object with variants
 */
function getProduct() {
    $id = (int)($_GET['id'] ?? 0);
    
    // Get product with category info
    $stmt = db()->query(
        "SELECT p.*, c.name as category_name FROM products p 
         LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ?",
        [$id]
    );
    $product = $stmt->fetch();
    
    // Return error if product not found
    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found']);
        return;
    }
    
    // Get variants for this product
    $variantStmt = db()->query(
        "SELECT pv.*, vt.name as variant_type_name, vt.variant_values as variant_type_values
         FROM product_variants pv 
         JOIN variant_types vt ON pv.variant_type_id = vt.id 
         WHERE pv.product_id = ? AND pv.is_active = 1",
        [$id]
    );
    $product['variants'] = $variantStmt->fetchAll();
    foreach ($product['variants'] as &$variant) {
        $variant['effective_price'] = $variant['price'] !== null
            ? (float)$variant['price']
            : (float)$product['base_price'] + (float)$variant['price_modifier'];
    }
    
    echo json_encode(['success' => true, 'product' => $product]);
}

/**
 * Add new product
 * 
 * POST data required: name
 * POST data optional: description, category_id, base_price, image
 * 
 * @return JSON with success status and new product ID
 */
function addProduct() {
    // Get and sanitize input data
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $base_price = (float)($_POST['base_price'] ?? 0);
    $stock = isset($_POST['stock']) && $_POST['stock'] !== '' ? (int)$_POST['stock'] : null;
    
    // Validate required field
    if (empty($name)) {
        echo json_encode(['success' => false, 'message' => 'Product name required']);
        return;
    }
    
    // Handle image upload if provided
    $imagePath = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $imagePath = uploadImage($_FILES['image']);
    }
    
    // Insert into database
    try {
        db()->query(
            "INSERT INTO products (name, description, category_id, base_price, stock, image) VALUES (?, ?, ?, ?, ?, ?)",
            [$name, $description, $category_id ?: null, $base_price, $stock, $imagePath]
        );
        echo json_encode(['success' => true, 'id' => db()->lastInsertId()]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Update existing product
 * 
 * POST data required: id, name
 * POST data optional: description, category_id, base_price, is_active, image
 * 
 * @return JSON with success status
 */
function updateProduct() {
    // Get input data
    $id = (int)($_POST['id'] ?? 0);
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $category_id = (int)($_POST['category_id'] ?? 0);
    $base_price = (float)($_POST['base_price'] ?? 0);
    $is_active = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 1;
    $stock = isset($_POST['stock']) && $_POST['stock'] !== '' ? (int)$_POST['stock'] : null;
    
    // Validate required fields
    if (empty($id) || empty($name)) {
        echo json_encode(['success' => false, 'message' => 'ID and name required']);
        return;
    }
    
    // Keep existing image unless new one uploaded
    $imagePath = $_POST['existing_image'] ?? '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $imagePath = uploadImage($_FILES['image']);
    }
    
    // Update product in database
    try {
        db()->query(
            "UPDATE products SET name = ?, description = ?, category_id = ?, base_price = ?, stock = ?, image = ?, is_active = ? WHERE id = ?",
            [$name, $description, $category_id ?: null, $base_price, $stock, $imagePath, $is_active, $id]
        );
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
}

/**
 * Delete (soft delete) product
 * Sets is_active = 0 instead of actually deleting from database
 * 
 * Query params:
 * - id: Product ID to delete
 * 
 * @return JSON with success status
 */
function deleteProduct() {
    $id = (int)($_GET['id'] ?? 0);
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID required']);
        return;
    }
    
    // Soft delete - set is_active to 0
    db()->query("UPDATE products SET is_active = 0 WHERE id = ?", [$id]);
    echo json_encode(['success' => true]);
}

/**
 * Search products by name
 * 
 * Query params:
 * - q: Search query string
 * 
 * @return JSON list of matching products
 */
function searchProducts() {
    $query = sanitize($_GET['q'] ?? '');
    
    // Search products by name (LIKE pattern)
    $stmt = db()->query(
        "SELECT p.*, c.name as category_name FROM products p 
         LEFT JOIN categories c ON p.category_id = c.id 
         WHERE p.is_active = 1 AND p.name LIKE ? 
         ORDER BY p.name LIMIT 50",
        ['%' . $query . '%']
    );
    
    echo json_encode(['success' => true, 'products' => $stmt->fetchAll()]);
}

/**
 * Handle image file upload
 * Validates file type and moves to uploads directory
 * 
 * @param array $file - Uploaded file from $_FILES
 * @return string - Relative path to uploaded file, or empty string on failure
 */
function getStocks() {
    $stmt = db()->query(
        "SELECT p.id, p.name, p.base_price, c.name as category_name,
                pv.id as variant_id, pv.variant_value, pv.stock, pv.price_modifier,
                vt.name as variant_type
         FROM products p
         LEFT JOIN categories c ON p.category_id = c.id
         LEFT JOIN product_variants pv ON pv.product_id = p.id AND pv.is_active = 1
         LEFT JOIN variant_types vt ON pv.variant_type_id = vt.id
         WHERE p.is_active = 1
         ORDER BY c.name, p.name, pv.variant_value"
    );
    $rows = $stmt->fetchAll();

    $products = [];
    $productStock = []; // track product-level stock for variantless items
    $stmt2 = db()->query("SELECT id, name, stock FROM products WHERE is_active = 1 AND stock IS NOT NULL");
    foreach ($stmt2->fetchAll() as $ps) {
        $productStock[$ps['id']] = (int)$ps['stock'];
    }

    foreach ($rows as $row) {
        $pid = $row['id'];
        if (!isset($products[$pid])) {
            $products[$pid] = [
                'id' => $pid,
                'name' => $row['name'],
                'base_price' => $row['base_price'],
                'category_name' => $row['category_name'],
                'stock' => $productStock[$pid] ?? null,
                'variants' => []
            ];
        }
        if ($row['variant_id']) {
            $products[$pid]['variants'][] = [
                'id' => $row['variant_id'],
                'variant_value' => $row['variant_value'],
                'variant_type' => $row['variant_type'],
                'stock' => (int)$row['stock'],
                'price_modifier' => $row['price_modifier']
            ];
        }
    }

    $lowStockCount = 0;
    $totalProducts = count($products);
    $totalStockItems = 0;
    foreach ($products as $p) {
        if (!empty($p['variants'])) {
            foreach ($p['variants'] as $v) {
                $totalStockItems++;
                if ($v['stock'] <= 10) $lowStockCount++;
            }
        } elseif ($p['stock'] !== null) {
            $totalStockItems++;
            if ($p['stock'] <= 10) $lowStockCount++;
        }
    }

    echo json_encode([
        'success' => true,
        'products' => array_values($products),
        'summary' => [
            'total_products' => $totalProducts,
            'total_variants' => $totalStockItems,
            'low_stock_count' => $lowStockCount,
            'low_stock_threshold' => 10
        ]
    ]);
}

function uploadImage($file) {
    // Only allow image file types
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    if (!in_array($file['type'], $allowedTypes)) {
        return '';
    }
    
    // Generate unique filename to prevent overwrites
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = uniqid('product_') . '.' . $extension;
    $uploadDir = __DIR__ . '/../uploads/products/';
    
    // Create upload directory if it doesn't exist
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    $targetPath = $uploadDir . $filename;
    
    // Move uploaded file to destination
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        return 'uploads/products/' . $filename;
    }
    
    return '';
}