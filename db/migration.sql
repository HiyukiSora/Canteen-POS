-- Add direct price column to product_variants (null = use base_price + price_modifier)
ALTER TABLE product_variants ADD COLUMN price DECIMAL(10,2) DEFAULT NULL AFTER price_modifier;

-- Add stock column to products (for products without variants, null = unlimited)
ALTER TABLE products ADD COLUMN stock INT DEFAULT NULL AFTER base_price;

-- Add customer_name to orders for easy ticket recall
ALTER TABLE orders ADD COLUMN customer_name VARCHAR(100) DEFAULT NULL AFTER user_id;

-- Add new tables for ticket editing and notifications
CREATE TABLE IF NOT EXISTS order_edits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    edited_by INT NOT NULL,
    edit_data JSON NOT NULL,
    reason TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (edited_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL DEFAULT 'info',
    message TEXT NOT NULL,
    order_id INT DEFAULT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;
