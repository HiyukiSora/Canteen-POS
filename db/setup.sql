DROP DATABASE IF EXISTS canteen_pos;
CREATE DATABASE canteen_pos;
USE canteen_pos;

-- Users
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('admin', 'cashier') DEFAULT 'cashier',
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Categories
CREATE TABLE categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    image VARCHAR(255)
) ENGINE=InnoDB;

-- Variant Types
CREATE TABLE variant_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL,
    variant_values VARCHAR(255) NOT NULL
) ENGINE=InnoDB;

-- Products
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    category_id INT,
    image VARCHAR(255),
    base_price DECIMAL(10, 2) NOT NULL DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Product Variants
CREATE TABLE product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    variant_type_id INT NOT NULL,
    variant_value VARCHAR(50) NOT NULL,
    sku VARCHAR(50) UNIQUE,
    price_modifier DECIMAL(10, 2) DEFAULT 0,
    stock INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_type_id) REFERENCES variant_types(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Orders
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) UNIQUE NOT NULL,
    user_id INT,
    total_amount DECIMAL(10, 2) NOT NULL,
    cash_tendered DECIMAL(10, 2) NOT NULL,
    change_given DECIMAL(10, 2) NOT NULL,
    status ENUM('completed', 'cancelled', 'refunded') DEFAULT 'completed',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Order Items - inline FK
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    variant_id INT,
    quantity INT NOT NULL,
    unit_price DECIMAL(10, 2) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (variant_id) REFERENCES product_variants(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Chat Messages
CREATE TABLE chat_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Insert data
-- Password for all users is: admin123
INSERT INTO users (username, password, full_name, role) VALUES 
('admin', '$2y$10$G0D9.A7IdHl5f7SUly8JJ.362.ERQF.aHXfkTAl1TQXEGe0nZO8ZG', 'System Administrator', 'admin'),
('cashier', '$2y$10$G0D9.A7IdHl5f7SUly8JJ.362.ERQF.aHXfkTAl1TQXEGe0nZO8ZG', 'Default Cashier', 'cashier');

INSERT INTO variant_types (name, variant_values) VALUES 
('Size', 'Small,Medium,Large,Extra Large'),
('Color', 'Red,Blue,Green,Black,White'),
('Flavor', 'Vanilla,Chocolate,Strawberry,Coffee'),
('Weight', '100g,250g,500g,1kg');

INSERT INTO categories (name, description) VALUES 
('Beverages', 'Drinks and beverages'),
('Snacks', 'Assorted snacks and chips'),
('Meals', 'Main meals and rice dishes'),
('Desserts', 'Sweets and desserts');

INSERT INTO products (name, description, category_id, base_price) VALUES 
('Coke', 'Soft drink can', 1, 25.00),
('Water', 'Mineral water bottle', 1, 15.00),
('Coffee', 'Hot brewed coffee', 1, 30.00),
('Juice Box', 'Assorted fruit juice', 1, 20.00),
('Chips', 'Potato chips', 2, 25.00),
('Biscuits', 'Sweet biscuits pack', 2, 15.00),
('Chocolate', 'Chocolate bar', 2, 20.00),
('Noodles', 'Instant noodles', 3, 35.00),
('Rice Meal', 'Rice with viand', 3, 50.00),
('Sandwich', 'Ham and cheese sandwich', 3, 40.00),
('Ice Cream', 'Scoop ice cream', 4, 30.00),
('Cake Slice', 'Slice of cake', 4, 35.00);

INSERT INTO product_variants (product_id, variant_type_id, variant_value, sku, price_modifier, stock) VALUES 
(1, 1, 'Small', 'COKE-SM', 0, 50),
(1, 1, 'Medium', 'COKE-MD', 5, 30),
(1, 1, 'Large', 'COKE-LG', 10, 20),
(2, 1, 'Small', 'WATER-SM', 0, 100),
(2, 1, 'Large', 'WATER-LG', 5, 50);