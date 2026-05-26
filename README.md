# Canteen POS - Point of Sale System

A modern Point of Sale system for school canteens built with PHP, MySQL, and DaisyUI.

## Features

- 👤 **User Management** - Admin and Cashier roles
- 📦 **Product Management** - Products with images and flexible variants
- 🎨 **Flexible Variants** - Customizable options (size, color, etc.)
- 📊 **Stock Management** - Real-time inventory tracking
- 🛒 **POS Interface** - Quick product selection with cart
- 💰 **Checkout** - Automatic change calculator
- 📈 **Admin Dashboard** - Sales monitoring and reports
- 💬 **Real-time Chat** - Admin can message cashiers
- 🎨 **DaisyUI Theme** - Clean business theme

## Requirements

- PHP 8.0+
- MySQL 5.7+
- Composer
- Node.js (for Tailwind CSS)

## Installation

### 1. Install Dependencies

```bash
# PHP dependencies
composer install

# CSS dependencies
npm install
```

### 2. Database Setup

1. Create a new MySQL database named `canteen_pos`
2. Import the database schema:
   ```bash
   mysql -u root -p canteen_pos < db/setup.sql
   ```

### 3. Configure Environment

Copy `.env.example` to `.env` and update database credentials:
```env
DB_HOST=localhost
DB_NAME=canteen_pos
DB_USER=root
DB_PASS=
```

### 4. Build CSS

```bash
npm run build
```

### 5. Start Servers

**Web Server (PHP):**
```bash
# Using built-in PHP server
php -S localhost:8000 -t public
```

**WebSocket Server (for Chat):**
```bash
php server.php
```

## Default Login

| Role   | Username | Password |
|--------|----------|----------|
| Admin  | admin    | admin123 |
| Cashier| cashier  | cashier123|

## Project Structure

```
canteen-pos/
├── public/           # Web root
│   ├── api/         # API endpoints
│   ├── admin/       # Admin dashboard
│   ├── assets/      # CSS, JS, images
│   ├── uploads/     # Product images
│   ├── index.php    # Login page
│   ├── pos.php      # Main POS screen
│   └── admin.php    # Admin panel entry
├── db/              # Database schema
├── src/             # Source CSS
└── vendor/          # PHP dependencies
```

## License

MIT