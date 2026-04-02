-- ═══════════════════════════════════════════
-- HobbyLoop Database Schema + Seed Data
-- Database: hobbyloop_db
-- 27 tables covering 10 data domains
-- ═══════════════════════════════════════════

DROP DATABASE IF EXISTS hobbyloop_db;
CREATE DATABASE hobbyloop_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hobbyloop_db;

-- ── 1. Users (Customer Data) ──
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    username VARCHAR(50) UNIQUE DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    avatar_initials VARCHAR(5),
    avatar_color VARCHAR(7) DEFAULT '#0D7C6E',
    role ENUM('buyer','seller','admin') DEFAULT 'buyer',
    admin_level ENUM('super','editor','viewer') DEFAULT NULL,
    trust_badge VARCHAR(50) DEFAULT 'New Member',
    is_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ── 2. User Addresses ──
CREATE TABLE user_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    label VARCHAR(30) DEFAULT 'Home',
    address_type ENUM('shipping','billing') DEFAULT 'shipping',
    street VARCHAR(255) NOT NULL,
    city VARCHAR(100) NOT NULL,
    province VARCHAR(100),
    zip VARCHAR(10) NOT NULL,
    is_default TINYINT(1) DEFAULT 0,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 3. Sessions ──
CREATE TABLE sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 4. Categories ──
CREATE TABLE categories (
    id VARCHAR(20) PRIMARY KEY,
    label VARCHAR(50) NOT NULL,
    sort_order INT DEFAULT 0
) ENGINE=InnoDB;

-- ── 5. Products (Product Data) ──
CREATE TABLE products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    category_id VARCHAR(20) NOT NULL,
    name VARCHAR(150) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    original_price DECIMAL(10,2),
    sku VARCHAR(50),
    stock_qty INT DEFAULT 1,
    condition_label VARCHAR(30) NOT NULL,
    brand VARCHAR(100),
    badge ENUM('hot','top','new') DEFAULT NULL,
    bg_gradient VARCHAR(100),
    image_url VARCHAR(500),
    rating DECIMAL(2,1) DEFAULT 0,
    review_count INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (seller_id) REFERENCES users(id),
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- ── 6. Product Images ──
CREATE TABLE product_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    image_url VARCHAR(500) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 7. Product Variants ──
CREATE TABLE product_variants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    variant_name VARCHAR(100) NOT NULL,
    variant_value VARCHAR(100) NOT NULL,
    price_modifier DECIMAL(10,2) DEFAULT 0,
    stock_qty INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 8. Sellers (extended user profile) ──
CREATE TABLE sellers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    city VARCHAR(100),
    badge VARCHAR(30),
    total_sales INT DEFAULT 0,
    seller_rating DECIMAL(2,1) DEFAULT 0,
    specialty_categories TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 9. Orders (Order Data) ──
CREATE TABLE orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(30) NOT NULL UNIQUE,
    user_id INT NOT NULL,
    subtotal DECIMAL(10,2) NOT NULL,
    discount DECIMAL(10,2) DEFAULT 0,
    shipping_fee DECIMAL(10,2) DEFAULT 280.00,
    total DECIMAL(10,2) NOT NULL,
    status ENUM('Processing','Shipped','Delivered','Cancelled') DEFAULT 'Processing',
    shipping_name VARCHAR(100),
    shipping_email VARCHAR(100),
    shipping_phone VARCHAR(20),
    shipping_address VARCHAR(255),
    shipping_city VARCHAR(100),
    shipping_zip VARCHAR(10),
    promo_code VARCHAR(20),
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ── 10. Order Items ──
CREATE TABLE order_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    product_id INT NOT NULL,
    product_name VARCHAR(150) NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    qty INT NOT NULL DEFAULT 1,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ── 11. Payments (Payment Data) ──
CREATE TABLE payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    method ENUM('card','gcash','bank','cod') NOT NULL,
    transaction_id VARCHAR(100),
    status ENUM('pending','completed','failed','refunded') DEFAULT 'pending',
    amount DECIMAL(10,2) NOT NULL,
    billing_name VARCHAR(100),
    billing_address VARCHAR(255) DEFAULT NULL,
    billing_city VARCHAR(100) DEFAULT NULL,
    billing_zip VARCHAR(10) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 12. Cart Items (server-side cart) ──
CREATE TABLE cart_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    qty INT DEFAULT 1,
    is_selected TINYINT(1) DEFAULT 1,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    UNIQUE KEY unique_cart_item (user_id, product_id)
) ENGINE=InnoDB;

-- ── 13. Inventory Log (Inventory Data) ──
CREATE TABLE inventory_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    change_qty INT NOT NULL,
    reason ENUM('sale','restock','adjustment','return') NOT NULL,
    reference_id INT,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- ── 14. Suppliers ──
CREATE TABLE suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    contact_email VARCHAR(100),
    contact_phone VARCHAR(20),
    address TEXT
) ENGINE=InnoDB;

-- ── 15. Product Suppliers ──
CREATE TABLE product_suppliers (
    product_id INT NOT NULL,
    supplier_id INT NOT NULL,
    reorder_level INT DEFAULT 5,
    lead_time_days INT DEFAULT 7,
    PRIMARY KEY (product_id, supplier_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 16. Shipments (Shipping & Delivery Data) ──
CREATE TABLE shipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    courier VARCHAR(50) DEFAULT 'J&T Express',
    tracking_number VARCHAR(100),
    shipping_fee DECIMAL(10,2),
    status ENUM('pending','picked_up','in_transit','delivered','returned') DEFAULT 'pending',
    estimated_delivery DATE,
    actual_delivery DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 17. Community Posts ──
CREATE TABLE community_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    text TEXT NOT NULL,
    tagged_product_id INT,
    likes_count INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (tagged_product_id) REFERENCES products(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ── 18. Post Likes ──
CREATE TABLE post_likes (
    user_id INT NOT NULL,
    post_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, post_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (post_id) REFERENCES community_posts(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 19. User Follows ──
CREATE TABLE user_follows (
    follower_id INT NOT NULL,
    following_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (follower_id, following_id),
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 20. Notifications ──
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    icon VARCHAR(10),
    text TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 21. Wishlist ──
CREATE TABLE wishlist (
    user_id INT NOT NULL,
    product_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, product_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 22. Reviews (Reviews & Feedback Data — Domain 9) ──
CREATE TABLE reviews (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    is_approved TINYINT(1) DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_user_product_review (user_id, product_id)
) ENGINE=InnoDB;

-- ── 23. User Activity (Analytics Data) ──
CREATE TABLE user_activity (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    session_token VARCHAR(64),
    action ENUM('page_view','product_view','search','add_to_cart','remove_from_cart','checkout_start','checkout_complete','login','logout','cart_abandon') NOT NULL,
    target_id INT,
    target_type VARCHAR(30),
    search_query VARCHAR(255),
    metadata JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_action (user_id, action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

-- ── 24. Promo Codes ──
CREATE TABLE promo_codes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    discount_type ENUM('percent','fixed') NOT NULL,
    discount_value DECIMAL(10,2) NOT NULL,
    min_order DECIMAL(10,2) DEFAULT 0,
    max_uses INT DEFAULT NULL,
    used_count INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    valid_from DATE DEFAULT NULL,
    expires_at DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;


-- ── 25. Promo Usage (Promotions & Discounts — Domain 10, campaign tracking) ──
CREATE TABLE promo_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    promo_id INT NOT NULL,
    order_id INT NOT NULL,
    user_id INT NOT NULL,
    discount_applied DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_promo_user (promo_id, user_id),
    FOREIGN KEY (promo_id) REFERENCES promo_codes(id),
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ── 26. Feedback Messages (Reviews & Feedback — Domain 9) ──
CREATE TABLE feedback_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subject VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('open','in_progress','resolved','closed') DEFAULT 'open',
    admin_reply TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ── 27. System Logs (Admin/Management — Domain 8) ──
CREATE TABLE system_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50),
    entity_id INT,
    details JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id),
    INDEX idx_action (action),
    INDEX idx_created (created_at)
) ENGINE=InnoDB;


-- ═══════════════════════════════════════════
-- SEED DATA
-- ═══════════════════════════════════════════

-- ── Categories ──
INSERT INTO categories (id, label, sort_order) VALUES
('all',        'All Items',         0),
('creative',   'Creative Arts',     1),
('outdoor',    'Outdoor & Nature',  2),
('stem',       'Technical & STEM',  3),
('sports',     'Physical Sports',   4),
('culinary',   'Culinary Arts',     5),
('gaming',     'Gaming & Strategy', 6),
('collecting', 'Collecting',        7);

-- ── Users (sellers + demo buyer + admin) ──
-- All passwords are bcrypt hashed. Seller passwords: 'seller123', Demo: 'password123', Admin: 'admin123'
INSERT INTO users (id, first_name, last_name, email, username, password_hash, phone, avatar_initials, avatar_color, role, admin_level, trust_badge, is_verified) VALUES
(1, 'Jerome',  'Lim',     'jerome.lim@hobbyloop.ph',    'jerome.lim',    '$2y$10$A9p7RsX32CLBmn9ggLQ5ROkTT3Kiazb/VI9whx4NfQ6zFQtd94n/u', '+63 917 123 4501', 'JL', '#D97706', 'seller', NULL,    'Super Seller', 1),
(2, 'Maria',   'Reyes',   'maria.reyes@hobbyloop.ph',   'maria.reyes',   '$2y$10$A9p7RsX32CLBmn9ggLQ5ROkTT3Kiazb/VI9whx4NfQ6zFQtd94n/u', '+63 917 123 4502', 'MR', '#059669', 'seller', NULL,    'Fast Ship',    1),
(3, 'Tony',    'Cruz',    'tony.cruz@hobbyloop.ph',     'tony.cruz',     '$2y$10$A9p7RsX32CLBmn9ggLQ5ROkTT3Kiazb/VI9whx4NfQ6zFQtd94n/u', '+63 917 123 4503', 'TC', '#7C3AED', 'seller', NULL,    'Verified',     1),
(4, 'Sofia',   'Navarro', 'sofia.navarro@hobbyloop.ph', 'sofia.navarro', '$2y$10$A9p7RsX32CLBmn9ggLQ5ROkTT3Kiazb/VI9whx4NfQ6zFQtd94n/u', '+63 917 123 4504', 'SN', '#DC2626', 'seller', NULL,    'Pro Seller',   1),
(5, 'Ryan',    'Santos',  'ryan.santos@hobbyloop.ph',   'ryan.santos',   '$2y$10$A9p7RsX32CLBmn9ggLQ5ROkTT3Kiazb/VI9whx4NfQ6zFQtd94n/u', '+63 917 123 4505', 'RS', '#0284C7', 'seller', NULL,    'Trusted',      1),
(6, 'Alex',    'Kim',     'alex.kim@hobbyloop.ph',      'alex.kim',      '$2y$10$LJErKqco/jla5OmL3fD2rOZ0NoQC4XqZz0D28/.Lm97AUj2QSmJum', '+63 912 345 6789', 'AK', '#0D7C6E', 'buyer',  NULL,    'New Member',   0),
(7, 'Admin',   'User',    'admin@hobbyloop.ph',         'admin',         '$2y$10$jzCaeKMAL6ZtAL4H4n9ccOkad9syuitBY4Hqo18gSkxokrKASpYda', '+63 917 000 0000', 'AU', '#0D7C6E', 'admin',  'super', 'Admin',        1);

-- ── Sellers (extended profiles) ──
INSERT INTO sellers (user_id, city, badge, total_sales, seller_rating, specialty_categories) VALUES
(1, 'Metro Manila',  'Super Seller', 234, 5.0, 'Creative Arts,Gaming'),
(2, 'Cebu City',     'Fast Ship',    189, 4.9, 'Creative Arts,Collecting'),
(3, 'Davao City',    'Verified',     156, 4.9, 'Technical,Culinary'),
(4, 'BGC Taguig',    'Pro Seller',    98, 4.8, 'Sports,Gaming,Collecting'),
(5, 'Quezon City',   'Trusted',      112, 4.8, 'Outdoor,Technical,Sports');

-- ── Products (30 items from data.js) ──
INSERT INTO products (id, seller_id, category_id, name, description, price, original_price, sku, stock_qty, condition_label, brand, badge, bg_gradient, image_url, rating, review_count) VALUES
-- Creative Arts
(1,  1, 'creative',   'Fujifilm X100V Silver',           'Iconic compact camera, shutter perfectly responsive. Minor cosmetic wear on base plate only.',                                    55900.00,  72000.00, 'HL-CRE-001', 3, 'Excellent',   'Fujifilm',    'hot',  'linear-gradient(135deg,#E8D5B7,#C9A87A)', 'https://images.unsplash.com/photo-1516035069371-29a1b244cc32?w=400&h=300&fit=crop',    4.9, 47),
(2,  2, 'creative',   'Leica M6 TTL Black',              'Legendary rangefinder, meter fully functional. Comes with original box and strap.',                                                198000.00, 230000.00,'HL-CRE-002', 2, 'Very Good',   'Leica',       'top',  'linear-gradient(135deg,#D1D5DB,#9CA3AF)', 'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?w=400&h=300&fit=crop',    4.7, 89),
(3,  3, 'creative',   'Sony ZV-E10 Kit 16-50mm',         'Barely used vlogging powerhouse. No scratches, sensor pristine. Box and all accessories included.',                                24500.00,  32000.00, 'HL-CRE-003', 5, 'Like New',    'Sony',        'new',  'linear-gradient(135deg,#BFDBFE,#60A5FA)', 'https://images.unsplash.com/photo-1502920917128-1aa500764cbd?w=400&h=300&fit=crop',    5.0, 12),
(4,  4, 'creative',   'Rode VideoMic Pro+',               'Professional on-camera mic, Rycote Lyre mount intact. Low noise, crystal clear audio.',                                           8900.00,   12500.00, 'HL-CRE-004', 4, 'Very Good',   'Rode',        NULL,   'linear-gradient(135deg,#FDE68A,#F59E0B)', 'https://images.unsplash.com/photo-1590602847861-f357a9332bbc?w=400&h=300&fit=crop',    4.8, 23),
(5,  1, 'creative',   'Wacom Intuos Pro M',              'Professional drawing tablet with multi-touch. All nibs included, working perfectly.',                                              14200.00,  19500.00, 'HL-CRE-005', 3, 'Good',        'Wacom',       NULL,   'linear-gradient(135deg,#E0E7FF,#818CF8)', 'https://images.unsplash.com/photo-1629429408209-1f912961dbd8?w=400&h=300&fit=crop',    4.6, 34),
(6,  2, 'creative',   'Canon EOS R50 Body',              'Mirrorless with 24.2MP sensor. Only 1,200 shutter count. Perfect for aspiring photographers.',                                     38000.00,  48000.00, 'HL-CRE-006', 4, 'Excellent',   'Canon',       'new',  'linear-gradient(135deg,#FED7E2,#F472B6)', 'https://images.unsplash.com/photo-1510127034890-ba27508e9f1c?w=400&h=300&fit=crop',    4.9, 18),
-- Outdoor & Nature
(7,  3, 'outdoor',    'Nikon Monarch M5 8x42',           'Premium birdwatching binoculars, ED glass, dielectric coating. Zero fungus or haze.',                                              15800.00,  22000.00, 'HL-OUT-001', 3, 'Excellent',   'Nikon',       NULL,   'linear-gradient(135deg,#D1FAE5,#34D399)', 'https://images.unsplash.com/photo-1621451537084-482c73073a0f?w=400&h=300&fit=crop',    4.9, 29),
(8,  5, 'outdoor',    'MSR Hubba Hubba NX 2-Person',     'Used twice on weekend trips. Poles and stakes all present. Minor staining on rainfly.',                                            12400.00,  18000.00, 'HL-OUT-002', 2, 'Good',        'MSR',         NULL,   'linear-gradient(135deg,#FDE68A,#FCA5A5)', 'https://images.unsplash.com/photo-1504280390367-361c6d9f38f4?w=400&h=300&fit=crop',    4.5, 41),
(9,  4, 'outdoor',    'Garmin GPSMAP 65s',               'Handheld GPS with multi-band GNSS. Screen protector applied, no scratches.',                                                       18500.00,  24000.00, 'HL-OUT-003', 2, 'Very Good',   'Garmin',      NULL,   'linear-gradient(135deg,#BFDBFE,#3B82F6)', 'https://images.unsplash.com/photo-1551698618-1dfe5d97d256?w=400&h=300&fit=crop',    4.7, 15),
(10, 5, 'outdoor',    'Black Diamond Spot 350 Headlamp',  'Used only once. All batteries replaced. 350 lumens, IPX8 waterproof.',                                                            2200.00,   3200.00,  'HL-OUT-004', 8, 'Like New',    'Black Diamond',  'new', 'linear-gradient(135deg,#E0F2FE,#7DD3FC)', 'https://images.unsplash.com/photo-1590073242678-70ee3fc28e8e?w=400&h=300&fit=crop',    4.8, 8),
-- Technical & STEM
(11, 1, 'stem',       'Celestron NexStar 8SE',           'Computerized Schmidt-Cassegrain telescope. GoTo aligned and working perfectly. All accessories.',                                   64000.00,  89000.00, 'HL-STM-001', 1, 'Very Good',   'Celestron',   'top',  'linear-gradient(135deg,#E9D5FF,#7C3AED)', 'https://images.unsplash.com/photo-1532978379173-523e16f371f2?w=400&h=300&fit=crop',    4.8, 22),
(12, 5, 'stem',       'Raspberry Pi 4 Model B 8GB Kit',  'Complete kit with case, PSU, heatsinks, SD card with Raspbian pre-installed.',                                                     5400.00,   7200.00,  'HL-STM-002', 6, 'Excellent',   'Raspberry Pi','new',  'linear-gradient(135deg,#FEE2E2,#F87171)', 'https://images.unsplash.com/photo-1629292921160-93ed4e344bce?w=400&h=300&fit=crop',    4.9, 56),
(13, 3, 'stem',       'Fluke 87V True RMS Multimeter',   'Industrial-grade multimeter, all leads included. Calibrated 6 months ago.',                                                        8200.00,   11500.00, 'HL-STM-003', 4, 'Good',        'Fluke',       NULL,   'linear-gradient(135deg,#FEF3C7,#FDE68A)', 'https://images.unsplash.com/photo-1588508065123-287b28e013da?w=400&h=300&fit=crop',    4.6, 31),
(14, 2, 'stem',       'Arduino Mega 2560 + Starter Kit', 'Complete starter kit with breadboards, sensors, wires. Perfect for learning embedded systems.',                                     3200.00,   4800.00,  'HL-STM-004', 7, 'Very Good',   'Arduino',     NULL,   'linear-gradient(135deg,#D1FAE5,#6EE7B7)', 'https://images.unsplash.com/photo-1553406830-ef2513450d76?w=400&h=300&fit=crop',    4.7, 44),
-- Physical Sports
(15, 4, 'sports',     'Titleist AP2 Iron Set 4-PW',      'Forged irons with Vokey wedges. Regripped last month. Cosmetic wear on soles only.',                                              28000.00,  42000.00, 'HL-SPT-001', 1, 'Good',        'Titleist',    NULL,   'linear-gradient(135deg,#ECFDF5,#86EFAC)', 'https://images.unsplash.com/photo-1535131749006-b7f58c99034b?w=400&h=300&fit=crop',    4.7, 19),
(16, 5, 'sports',     'Trek Marlin 5 MTB 2021',          '29er hardtail, freshly tuned and cleaned. New brake pads and chain installed.',                                                    35000.00,  52000.00, 'HL-SPT-002', 1, 'Very Good',   'Trek',        NULL,   'linear-gradient(135deg,#FEF9C3,#FCD34D)', 'https://images.unsplash.com/photo-1541625602330-2277a4c46182?w=400&h=300&fit=crop',    4.8, 27),
(17, 2, 'sports',     'Speedo Fastskin Elite Mirror Goggle','Competition-grade goggles, anti-fog lens perfect. Used only in 2 meets.',                                                       2800.00,   4200.00,  'HL-SPT-003', 5, 'Like New',    'Speedo',      'new',  'linear-gradient(135deg,#DBEAFE,#93C5FD)', 'https://images.unsplash.com/photo-1530549387789-4c1017266635?w=400&h=300&fit=crop',    4.9, 11),
(18, 1, 'sports',     'Yonex Arcsaber 11 Pro Badminton', 'Strung at 26lbs BG80. Grip replaced. Minor paint chip near handle, no structural damage.',                                         7500.00,   11000.00, 'HL-SPT-004', 3, 'Very Good',   'Yonex',       NULL,   'linear-gradient(135deg,#FCE7F3,#F9A8D4)', 'https://images.unsplash.com/photo-1613918431703-aa50889e3be2?w=400&h=300&fit=crop',    4.6, 33),
-- Culinary Arts
(19, 4, 'culinary',   'KitchenAid Stand Mixer 5qt Artisan','Empire Red, all original attachments. Motor strong, bowl shows light use scratches only.',                                       18000.00,  26000.00, 'HL-CUL-001', 2, 'Very Good',   'KitchenAid',  NULL,   'linear-gradient(135deg,#FEF3C7,#FDE68A)', 'https://images.unsplash.com/photo-1594385208974-2f8bb07b7a26?w=400&h=300&fit=crop',    4.8, 38),
(20, 5, 'culinary',   'De Buyer Mineral B Carbon Steel Pan','Perfectly seasoned 28cm pan. Non-stick as cast iron. Lifetime cookware at a fraction of cost.',                                  3800.00,   5500.00,  'HL-CUL-002', 4, 'Excellent',   'De Buyer',    'top',  'linear-gradient(135deg,#D1D5DB,#6B7280)', 'https://images.unsplash.com/photo-1585442231018-1e2d5e0a5c3d?w=400&h=300&fit=crop',    4.9, 52),
(21, 3, 'culinary',   'Breville Barista Express BES870', 'Integrated grinder, all portafilters included. Descaled and full service done last month.',                                         32000.00,  48000.00, 'HL-CUL-003', 2, 'Good',        'Breville',    NULL,   'linear-gradient(135deg,#F5F5F4,#D4D4D8)', 'https://images.unsplash.com/photo-1510707577719-ae7c14805e3a?w=400&h=300&fit=crop',    4.7, 24),
(22, 2, 'culinary',   'Victorinox Fibrox Pro 8" Chef Knife','Professionally sharpened. Edge holds well. Handle sanitized and clean. Perfect workhorse knife.',                                2400.00,   3500.00,  'HL-CUL-004', 6, 'Very Good',   'Victorinox',  NULL,   'linear-gradient(135deg,#E2E8F0,#94A3B8)', 'https://images.unsplash.com/photo-1593618998160-e34014e67546?w=400&h=300&fit=crop',    4.8, 67),
-- Gaming & Strategy
(23, 1, 'gaming',     'Nintendo Switch OLED White',      'Screen perfect, no dead pixels or scratches. Includes dock, Pro Controller, and 3 games.',                                          18500.00,  24000.00, 'HL-GAM-001', 3, 'Excellent',   'Nintendo',    'hot',  'linear-gradient(135deg,#FEF9C3,#FCD34D)', 'https://images.unsplash.com/photo-1578303512597-81e6cc155b3e?w=400&h=300&fit=crop',    4.9, 44),
(24, 4, 'gaming',     'Vital Lacerda Lisboa Deluxe',     'Played once at BGG Con. All components mint, sleeved. One of the best games ever made.',                                           6800.00,   9500.00,  'HL-GAM-002', 2, 'Like New',    'Eagle-Gryphon','new', 'linear-gradient(135deg,#DBEAFE,#A5F3FC)', 'https://images.unsplash.com/photo-1611371805429-8b5c1b2c34ba?w=400&h=300&fit=crop',    5.0, 9),
(25, 5, 'gaming',     'Keychron Q3 QMK Custom KB',       'Gateron G Pro Yellow switches, lubed. South-facing PCB, no RGB bleed. QMK configured.',                                            9800.00,   14000.00, 'HL-GAM-003', 3, 'Very Good',   'Keychron',    NULL,   'linear-gradient(135deg,#F3E8FF,#C4B5FD)', 'https://images.unsplash.com/photo-1618384887929-16ec33fab9ef?w=400&h=300&fit=crop',    4.8, 31),
(26, 3, 'gaming',     'Chess.com Membership + DGT Board','DGT Smart Board with Bluetooth. 1-year Chess.com Diamond included. Perfect for serious players.',                                   5200.00,   8000.00,  'HL-GAM-004', 2, 'Good',        'DGT',         NULL,   'linear-gradient(135deg,#F5F5F4,#D4D4D8)', 'https://images.unsplash.com/photo-1529699211952-734e80c4d42b?w=400&h=300&fit=crop',    4.5, 16),
-- Collecting
(27, 2, 'collecting', 'PSA 9 Charizard Base Set Holo',   'Professionally graded. Centered, sharp corners, no print defects. Certificate of authenticity.',                                    42000.00,  55000.00, 'HL-COL-001', 1, 'PSA 9',       'Pokemon',     'top',  'linear-gradient(135deg,#FEF3C7,#FCA5A5)', 'https://images.unsplash.com/photo-1613771404784-3a5686aa2be3?w=400&h=300&fit=crop',    5.0, 7),
(28, 5, 'collecting', 'Seiko SKX007 Diver 200m',         'Vintage diver icon. Recently serviced, new crown seal. Case polished, bezel sharp.',                                               28500.00,  36000.00, 'HL-COL-002', 1, 'Very Good',   'Seiko',       NULL,   'linear-gradient(135deg,#DBEAFE,#60A5FA)', 'https://images.unsplash.com/photo-1524592094714-0f0654e20314?w=400&h=300&fit=crop',    4.8, 42),
(29, 1, 'collecting', 'LEGO Creator Expert 10294 Titanic','Factory sealed, never opened. Receipt and box in perfect condition. Price includes shipping.',                                     22000.00,  28000.00, 'HL-COL-003', 2, 'Sealed',      'LEGO',        'new',  'linear-gradient(135deg,#E0E7FF,#A5B4FC)', 'https://images.unsplash.com/photo-1587654780291-39c9404d7dd0?w=400&h=300&fit=crop',    4.9, 14),
(30, 4, 'collecting', 'Marvel Funko Pop Galactus #1 Convention Exclusive','SDCC exclusive, never displayed. Original bag and sticker included. Extremely limited run.',                       8900.00,   12000.00, 'HL-COL-004', 3, 'Mint in Box', 'Funko',       NULL,   'linear-gradient(135deg,#F5D0A9,#E88B5E)', 'https://images.unsplash.com/photo-1608889825205-eebdb9fc5806?w=400&h=300&fit=crop',    4.7, 21);

-- ── Notifications (for demo user Alex Kim, id=6) ──
INSERT INTO notifications (user_id, icon, text, is_read, created_at) VALUES
(6, '📦', 'Your order #HL-2026-00291 has shipped! Tony Cruz sent your Breville Barista via J&T Express.',                  0, NOW() - INTERVAL 2 MINUTE),
(6, '💰', 'Price drop alert! Fujifilm X100V dropped ₱3,000 — it''s on your watchlist!',                                    0, NOW() - INTERVAL 14 MINUTE),
(6, '⭐', 'New review on your listing. Someone left a 5-star review on your Wacom Intuos listing.',                         0, NOW() - INTERVAL 1 HOUR),
(6, '🤝', 'Ryan Santos accepted your offer. MSR Hubba Hubba NX is ready to checkout!',                                     0, NOW() - INTERVAL 2 HOUR),
(6, '💬', 'Maria Reyes replied to your question about the Leica M6 condition.',                                             1, NOW() - INTERVAL 3 HOUR),
(6, '✅', 'Listing verified! Your Nintendo Switch OLED passed peer review and is now live.',                                 1, NOW() - INTERVAL 5 HOUR),
(6, '🎉', 'Welcome to HobbyLoop! Your account is fully set up. Start exploring 12,400+ items.',                             1, NOW() - INTERVAL 1 DAY),
(6, '🏆', 'You''re now a Trusted Buyer! 10 successful purchases with 5-star ratings.',                                     1, NOW() - INTERVAL 2 DAY);

-- ── Community Posts ──
INSERT INTO community_posts (id, user_id, text, tagged_product_id, likes_count, comments_count, created_at) VALUES
(1, 1, 'Just finished CLA on this Olympus OM-1. Shutter sounds like butter. Mirror damper replaced, light seals fresh. Ready for another 50 years.',                                                                NULL, 47,  12, NOW() - INTERVAL 2 MINUTE),
(2, 2, 'Film tip: CineStill 800T in tungsten lighting is absolutely magical for food photography. The halation around point lights is *chef''s kiss*.',                                                              20,   83,  24, NOW() - INTERVAL 18 MINUTE),
(3, 5, 'Sold the Nikon P1000 and upgrading to this Celestron. Night sky in Batad last weekend was life-changing. Highly recommend getting into astronomy!',                                                          11,   62,  19, NOW() - INTERVAL 1 HOUR),
(4, 4, 'Hot take: the De Buyer carbon steel pan beats cast iron in every single use case except maybe deep frying. Change my mind. Listing mine because upgrading to a larger size.',                                20,   119, 38, NOW() - INTERVAL 3 HOUR),
(5, 3, 'Finally built a proper electronics workspace with my Fluke multimeter and the Arduino kit. Running first IoT project this weekend — soil moisture sensor for my herb garden.',                                13,   34,  7,  NOW() - INTERVAL 5 HOUR),
(6, 1, 'PSA for collectors: Protective sleeves are non-negotiable. Just pulled a near-mint charizard from a Ziploc bag. The penny sleeve alone can double resale value over 5 years.',                               27,   201, 67, NOW() - INTERVAL 8 HOUR);

-- ── Promo Codes ──
INSERT INTO promo_codes (code, discount_type, discount_value, min_order, max_uses, is_active, valid_from, expires_at) VALUES
('HOBBY10',  'percent', 10.00, 1000.00, 100, 1, '2025-01-01', '2026-12-31'),
('WELCOME',  'fixed',   500.00, 2000.00, 50,  1, '2025-01-01', '2026-12-31'),
('LOOP5',    'percent',  5.00,  500.00,  200, 1, '2025-01-01', '2026-12-31');

-- ── Default like on post 4 by user 6 (Alex Kim liked Sofia's post) ──
INSERT INTO post_likes (user_id, post_id) VALUES (6, 4);

-- ── Suppliers (Inventory Data — Domain 5) ──
INSERT INTO suppliers (id, name, contact_email, contact_phone, address) VALUES
(1, 'PH Camera Supply Co.',   'sales@phcamera.ph',     '+63 2 8123 4567', '123 Makati Ave, Makati City'),
(2, 'Outdoor Gear Philippines','orders@outdoorgearph.com','+63 2 8234 5678','456 Bonifacio High St, BGC Taguig'),
(3, 'TechParts Manila',       'info@techpartsmnl.ph',  '+63 2 8345 6789', '789 Quezon Ave, Quezon City');

-- ── Product Suppliers (link products to suppliers with reorder levels) ──
INSERT INTO product_suppliers (product_id, supplier_id, reorder_level) VALUES
(1, 1, 2), (2, 1, 1), (3, 1, 3), (6, 1, 2),
(7, 2, 2), (8, 2, 1), (9, 2, 2), (10, 2, 5),
(11, 3, 1), (12, 3, 3), (13, 3, 2), (14, 3, 4);

-- ── Reviews (Domain 9 — sample product reviews) ──
INSERT INTO reviews (product_id, user_id, rating, comment, is_approved, created_at) VALUES
(1,  6, 5, 'Absolutely stunning camera. Shutter response is snappy and image quality is incredible. Seller packaged it perfectly.', 1, NOW() - INTERVAL 10 DAY),
(12, 6, 5, 'Complete kit as described. Raspberry Pi booted right away with Raspbian. Great for my home automation project.', 1, NOW() - INTERVAL 8 DAY),
(20, 6, 4, 'Pan is well-seasoned but slightly heavier than expected. Non-stick performance is excellent though.', 1, NOW() - INTERVAL 5 DAY),
(23, 6, 5, 'Switch OLED screen is gorgeous. No dead pixels, dock works perfectly. Pro Controller was a bonus!', 1, NOW() - INTERVAL 3 DAY),
(1,  5, 5, 'Best compact camera I have ever owned. Jerome shipped it fast and it arrived in perfect condition.', 1, NOW() - INTERVAL 15 DAY),
(20, 3, 5, 'This pan has replaced my entire non-stick collection. The seasoning is phenomenal. 10/10 recommend.', 1, NOW() - INTERVAL 12 DAY),
(12, 4, 4, 'Good kit overall. One jumper wire was bent but everything works. SD card pre-loaded which saved time.', 1, NOW() - INTERVAL 7 DAY),
(23, 2, 5, 'Screen quality blew me away. Jerome is a fantastic seller with great communication.', 1, NOW() - INTERVAL 1 DAY);

-- ── Feedback Messages (Domain 9 — user support tickets) ──
INSERT INTO feedback_messages (user_id, subject, message, status, admin_reply, created_at) VALUES
(6, 'How do I track my order?', 'I placed an order 2 days ago but I can''t find the tracking number anywhere. Can you help?', 'resolved', 'You can find tracking info in the Orders tab. Click on your order to see the J&T Express tracking number. Let us know if you need anything else!', NOW() - INTERVAL 3 DAY),
(6, 'Request for seller verification', 'I''d like to become a verified seller on HobbyLoop. What are the requirements?', 'open', NULL, NOW() - INTERVAL 1 DAY),
(5, 'Payment not reflecting', 'My GCash payment for order #HL-2026-00198 was debited but the order still shows as pending.', 'in_progress', NULL, NOW() - INTERVAL 2 DAY);
