-- HYPERLOCAL FOOD DELIVERY - MySQL Schema
-- Import: phpMyAdmin -> Import, or: mysql -u root < database/schema.sql

CREATE DATABASE IF NOT EXISTS hyperlocal_food CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hyperlocal_food;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS group_activity;
DROP TABLE IF EXISTS group_cart;
DROP TABLE IF EXISTS group_members;
DROP TABLE IF EXISTS group_orders;
DROP TABLE IF EXISTS cart;
DROP TABLE IF EXISTS menu_items;
DROP TABLE IF EXISTS restaurants;
DROP TABLE IF EXISTS users;

SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    role ENUM('user','admin') NOT NULL DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE restaurants (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(160) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    description TEXT,
    image_url VARCHAR(500) NOT NULL,
    rating DECIMAL(2,1) NOT NULL DEFAULT 4.0,
    delivery_time_mins SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    distance_km DECIMAL(5,2) NOT NULL DEFAULT 1.50,
    category VARCHAR(80) NOT NULL,
    prep_time_mins SMALLINT UNSIGNED NOT NULL DEFAULT 15,
    traffic_delay_mins SMALLINT UNSIGNED NOT NULL DEFAULT 5,
    address VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE menu_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    description VARCHAR(500) DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    is_available TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE cart (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    menu_item_id INT UNSIGNED NOT NULL,
    quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_user_item (user_id, menu_item_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE group_orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    invite_token VARCHAR(64) NOT NULL UNIQUE,
    creator_id INT UNSIGNED NOT NULL,
    restaurant_id INT UNSIGNED DEFAULT NULL,
    status ENUM('open','checkout','closed') NOT NULL DEFAULT 'open',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE group_members (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_order_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_group_user (group_order_id, user_id),
    FOREIGN KEY (group_order_id) REFERENCES group_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE group_activity (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_order_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED DEFAULT NULL,
    action_type VARCHAR(40) NOT NULL,
    message VARCHAR(500) NOT NULL,
    meta VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (group_order_id) REFERENCES group_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE group_cart (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    group_order_id INT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NOT NULL,
    menu_item_id INT UNSIGNED NOT NULL,
    quantity SMALLINT UNSIGNED NOT NULL DEFAULT 1,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_group_item_user (group_order_id, menu_item_id, user_id),
    FOREIGN KEY (group_order_id) REFERENCES group_orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    restaurant_id INT UNSIGNED NOT NULL,
    group_order_id INT UNSIGNED DEFAULT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
    tax DECIMAL(10,2) NOT NULL DEFAULT 0,
    total DECIMAL(10,2) NOT NULL,
    eta_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    status ENUM('preparing','packed','out_for_delivery','delivered') NOT NULL DEFAULT 'preparing',
    delivery_address TEXT NOT NULL,
    payment_method VARCHAR(40) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants(id) ON DELETE RESTRICT,
    FOREIGN KEY (group_order_id) REFERENCES group_orders(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    menu_item_id INT UNSIGNED NOT NULL,
    quantity SMALLINT UNSIGNED NOT NULL,
    price_each DECIMAL(10,2) NOT NULL,
    added_by_user_id INT UNSIGNED DEFAULT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE RESTRICT,
    FOREIGN KEY (added_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Seed: bcrypt for 'password' and 'admin123' — use PHP to hash properly.
-- Default hash below is bcrypt for password "password" (same as Laravel dummy).
INSERT INTO users (name, email, password_hash, phone, role) VALUES
('Admin User', 'admin@hyperlocal.test', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9990000001', 'admin'),
('Rahul Sharma', 'rahul@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876500001', 'user'),
('Arun Kumar', 'arun@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876500002', 'user'),
('Priya Nair', 'priya@demo.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', '9876500003', 'user');

INSERT INTO restaurants (name, slug, description, image_url, rating, delivery_time_mins, distance_km, category, prep_time_mins, traffic_delay_mins, address) VALUES
('The Spice Route', 'the-spice-route', 'North Indian curries & tandoor.', 'https://images.unsplash.com/photo-1585937421612-70a008356fbe?w=800', 4.6, 28, 1.2, 'North Indian', 18, 6, 'Block A, MG Road'),
('Coastal Catch', 'coastal-catch', 'Fresh seafood & Kerala meals.', 'https://images.unsplash.com/photo-1563379926898-05f4575a45d8?w=800', 4.4, 32, 2.1, 'South Indian', 20, 8, 'Marine Drive'),
('Urban Wok', 'urban-wok', 'Pan-Asian bowls & dim sum.', 'https://images.unsplash.com/photo-1553621042-f6e147245754?w=800', 4.7, 24, 0.8, 'Chinese', 12, 4, 'Tech Park Gate 2'),
('Firehouse Burgers', 'firehouse-burgers', 'Smash burgers & loaded fries.', 'https://images.unsplash.com/photo-1568901346375-23c9450c58cd?w=800', 4.5, 22, 0.6, 'Fast Food', 10, 3, 'Campus Street'),
('Slice Story', 'slice-story', 'Wood-fired pizzas & pasta.', 'https://images.unsplash.com/photo-1513104890138-7c749659a591?w=800', 4.8, 26, 1.5, 'Italian', 16, 5, 'Student Hub Mall'),
('Green Bowl Co.', 'green-bowl-co', 'Salads, smoothies & bowls.', 'https://images.unsplash.com/photo-1546069901-ba9599a7e63c?w=800', 4.3, 30, 1.0, 'Healthy', 8, 4, 'Wellness Lane');

INSERT INTO menu_items (restaurant_id, name, description, price, image_url) VALUES
(1, 'Butter Chicken', 'Creamy tomato gravy with tender chicken.', 289.00, 'https://images.unsplash.com/photo-1603894584373-5ac82b2ae398?w=600'),
(1, 'Paneer Tikka', 'Chargrilled cottage cheese with mint chutney.', 249.00, 'https://images.unsplash.com/photo-1567188040759-fb8a883dc6d8?w=600'),
(1, 'Dal Makhani', 'Slow-cooked black lentils.', 199.00, 'https://images.unsplash.com/photo-1546833999-b9f581a1996d?w=600'),
(2, 'Fish Curry Meal', 'Rice, fish curry, pickle & papad.', 320.00, 'https://images.unsplash.com/photo-1455619452474-d7be3369c51b?w=600'),
(2, 'Appam & Stew', 'Soft hoppers with vegetable stew.', 220.00, 'https://images.unsplash.com/photo-1589302168068-964664d93dc0?w=600'),
(3, 'Dragon Noodles', 'Wok-tossed noodles with veggies.', 210.00, 'https://images.unsplash.com/photo-1612929633738-8fe44f7ec841?w=600'),
(3, 'Dim Sum Platter', 'Steamed assorted dumplings.', 350.00, 'https://images.unsplash.com/photo-1496116218417-1a781b1c416c?w=600'),
(4, 'Classic Smash Burger', 'Double patty, cheese & secret sauce.', 199.00, 'https://images.unsplash.com/photo-1550547660-d9450f859349?w=600'),
(4, 'Peri Peri Fries', 'Large bucket with dip.', 129.00, 'https://images.unsplash.com/photo-1573080496219-bb080dd4d124?w=600'),
(5, 'Margherita Pizza', 'San Marzano tomato & fresh basil.', 299.00, 'https://images.unsplash.com/photo-1574071318508-1cdbab80d002?w=600'),
(5, 'Pepperoni Feast', 'Loaded pepperoni & mozzarella.', 379.00, 'https://images.unsplash.com/photo-1628840042765-356cda07504e?w=600'),
(6, 'Quinoa Power Bowl', 'Quinoa, avocado, chickpeas & tahini.', 249.00, 'https://images.unsplash.com/photo-1512621776951-a57141f2eefd?w=600'),
(6, 'Berry Blast Smoothie', 'Mixed berries, yogurt & honey.', 159.00, 'https://images.unsplash.com/photo-1505252585461-04db1ebfe574?w=600');
