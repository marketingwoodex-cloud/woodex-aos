-- Woodex live schema updates
-- Safe to run more than once on the selected Woodex database.
-- Run this in phpMyAdmin or MySQL after selecting the live database.

SET NAMES utf8mb4;

DELIMITER $$

DROP PROCEDURE IF EXISTS wdx_add_column$$
CREATE PROCEDURE wdx_add_column(
    IN p_table_name VARCHAR(64),
    IN p_column_name VARCHAR(64),
    IN p_column_definition TEXT
)
BEGIN
    IF EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
    ) AND NOT EXISTS (
        SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = p_table_name
          AND COLUMN_NAME = p_column_name
    ) THEN
        SET @wdx_sql = CONCAT(
            'ALTER TABLE `', REPLACE(p_table_name, '`', ''),
            '` ADD COLUMN `', REPLACE(p_column_name, '`', ''),
            '` ', p_column_definition
        );
        PREPARE wdx_stmt FROM @wdx_sql;
        EXECUTE wdx_stmt;
        DEALLOCATE PREPARE wdx_stmt;
    END IF;
END$$

DELIMITER ;

-- Categories: hierarchy, category hero fields, home carousel, hot products.
CALL wdx_add_column('categories', 'parent_id', 'INT NULL DEFAULT NULL');
CALL wdx_add_column('categories', 'level', 'INT NOT NULL DEFAULT 1');
CALL wdx_add_column('categories', 'path', 'VARCHAR(255) NULL DEFAULT NULL');
CALL wdx_add_column('categories', 'hero_layout', 'VARCHAR(20) NOT NULL DEFAULT ''default''');
CALL wdx_add_column('categories', 'hero_series_tag', 'VARCHAR(120) NOT NULL DEFAULT ''''');
CALL wdx_add_column('categories', 'hero_series_subtitle', 'VARCHAR(255) NOT NULL DEFAULT ''''');
CALL wdx_add_column('categories', 'hero_series_description', 'TEXT NULL');
CALL wdx_add_column('categories', 'hero_series_bullets', 'TEXT NULL');
CALL wdx_add_column('categories', 'hero_primary_btn_label', 'VARCHAR(120) NOT NULL DEFAULT ''View Full Series''');
CALL wdx_add_column('categories', 'hero_primary_btn_url', 'VARCHAR(255) NOT NULL DEFAULT ''#cat-products-grid''');
CALL wdx_add_column('categories', 'hero_secondary_btn_label', 'VARCHAR(120) NOT NULL DEFAULT ''Request Quote''');
CALL wdx_add_column('categories', 'hero_secondary_btn_url', 'VARCHAR(255) NOT NULL DEFAULT ''e-quotation''');
CALL wdx_add_column('categories', 'show_in_hot_products', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL wdx_add_column('categories', 'hot_products_sort_order', 'INT NOT NULL DEFAULT 0');
CALL wdx_add_column('categories', 'show_in_home_carousel', 'TINYINT(1) NOT NULL DEFAULT 0');

-- Products: home/sale flags.
CALL wdx_add_column('products', 'is_best_seller', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL wdx_add_column('products', 'is_sale_item', 'TINYINT(1) NOT NULL DEFAULT 0');

-- Product to multiple categories mapping. Existing products.category is kept as legacy fallback.
CREATE TABLE IF NOT EXISTS product_categories (
    product_id INT NOT NULL,
    category_id INT NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (product_id, category_id),
    KEY idx_product_categories_category (category_id),
    KEY idx_product_categories_product (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

DELIMITER $$

DROP PROCEDURE IF EXISTS wdx_backfill_product_categories$$
CREATE PROCEDURE wdx_backfill_product_categories()
BEGIN
    IF EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products'
    ) AND EXISTS (
        SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'products' AND COLUMN_NAME = 'category'
    ) THEN
        INSERT IGNORE INTO product_categories (product_id, category_id)
        SELECT id, category
        FROM products
        WHERE category IS NOT NULL AND category > 0;
    END IF;
END$$

DELIMITER ;

CALL wdx_backfill_product_categories();

-- Hero slider extensions.
CALL wdx_add_column('hero_sliders', 'active_design', 'VARCHAR(20) NOT NULL DEFAULT ''classic''');
CALL wdx_add_column('hero_slides', 'description', 'TEXT NULL');
CALL wdx_add_column('hero_slides', 'button_color', 'VARCHAR(20) NOT NULL DEFAULT ''#78be20''');
CALL wdx_add_column('hero_slides', 'heading_color', 'VARCHAR(20) NOT NULL DEFAULT ''#111111''');
CALL wdx_add_column('hero_slides', 'description_color', 'VARCHAR(20) NOT NULL DEFAULT ''#4f5d6b''');

CREATE TABLE IF NOT EXISTS hero_slides_alt (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slider_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NULL,
    cta_label VARCHAR(80) NULL,
    cta_url VARCHAR(255) NULL,
    button_color VARCHAR(20) NOT NULL DEFAULT '#78be20',
    heading_color VARCHAR(20) NOT NULL DEFAULT '#ffffff',
    description_color VARCHAR(20) NOT NULL DEFAULT '#deebf7',
    background_image_path VARCHAR(255) NOT NULL,
    background_image_alt VARCHAR(160) NULL,
    sort_order INT NOT NULL DEFAULT 1,
    is_published TINYINT(1) NOT NULL DEFAULT 1,
    start_at DATETIME NULL,
    end_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_alt_slider (slider_id, is_published, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CALL wdx_add_column('hero_slides_alt', 'button_color', 'VARCHAR(20) NOT NULL DEFAULT ''#78be20''');
CALL wdx_add_column('hero_slides_alt', 'heading_color', 'VARCHAR(20) NOT NULL DEFAULT ''#ffffff''');
CALL wdx_add_column('hero_slides_alt', 'description_color', 'VARCHAR(20) NOT NULL DEFAULT ''#deebf7''');

-- Footer settings and dynamic footer links.
CREATE TABLE IF NOT EXISTS footer_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    about_title VARCHAR(100) NOT NULL,
    about_tagline VARCHAR(160) NOT NULL,
    about_text TEXT NOT NULL,
    quick_links_title VARCHAR(100) NOT NULL,
    shop_title VARCHAR(100) NOT NULL,
    learn_more_title VARCHAR(100) NOT NULL,
    contact_title VARCHAR(100) NOT NULL,
    address_text VARCHAR(255) NOT NULL,
    email_text VARCHAR(180) NOT NULL,
    hours_text VARCHAR(255) NOT NULL,
    phone_number VARCHAR(60) NOT NULL,
    newsletter_title VARCHAR(100) NOT NULL,
    newsletter_placeholder VARCHAR(160) NOT NULL,
    newsletter_button_text VARCHAR(30) NOT NULL,
    payment_logos_json TEXT NOT NULL,
    social_label VARCHAR(100) NOT NULL,
    social_links_json TEXT NOT NULL,
    copyright_text VARCHAR(255) NOT NULL,
    legal_link_1_title VARCHAR(120) NOT NULL,
    legal_link_1_url VARCHAR(255) NOT NULL,
    legal_link_2_title VARCHAR(120) NOT NULL,
    legal_link_2_url VARCHAR(255) NOT NULL,
    legal_link_3_title VARCHAR(120) NOT NULL,
    legal_link_3_url VARCHAR(255) NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CALL wdx_add_column('footer_settings', 'about_title', 'VARCHAR(100) NOT NULL DEFAULT ''Woodex''');
CALL wdx_add_column('footer_settings', 'about_tagline', 'VARCHAR(160) NOT NULL DEFAULT ''Make your space work®''');
CALL wdx_add_column('footer_settings', 'about_text', 'TEXT NULL');
CALL wdx_add_column('footer_settings', 'quick_links_title', 'VARCHAR(100) NOT NULL DEFAULT ''Quick Links''');
CALL wdx_add_column('footer_settings', 'shop_title', 'VARCHAR(100) NOT NULL DEFAULT ''Shop''');
CALL wdx_add_column('footer_settings', 'learn_more_title', 'VARCHAR(100) NOT NULL DEFAULT ''Learn More''');
CALL wdx_add_column('footer_settings', 'contact_title', 'VARCHAR(100) NOT NULL DEFAULT ''Contact Us''');
CALL wdx_add_column('footer_settings', 'address_text', 'VARCHAR(255) NOT NULL DEFAULT ''''');
CALL wdx_add_column('footer_settings', 'email_text', 'VARCHAR(180) NOT NULL DEFAULT ''''');
CALL wdx_add_column('footer_settings', 'hours_text', 'VARCHAR(255) NOT NULL DEFAULT ''''');
CALL wdx_add_column('footer_settings', 'phone_number', 'VARCHAR(60) NOT NULL DEFAULT ''''');
CALL wdx_add_column('footer_settings', 'newsletter_title', 'VARCHAR(100) NOT NULL DEFAULT ''Newsletter''');
CALL wdx_add_column('footer_settings', 'newsletter_placeholder', 'VARCHAR(160) NOT NULL DEFAULT ''Your email''');
CALL wdx_add_column('footer_settings', 'newsletter_button_text', 'VARCHAR(30) NOT NULL DEFAULT ''->''');
CALL wdx_add_column('footer_settings', 'payment_logos_json', 'TEXT NULL');
CALL wdx_add_column('footer_settings', 'social_label', 'VARCHAR(100) NOT NULL DEFAULT ''''');
CALL wdx_add_column('footer_settings', 'social_links_json', 'TEXT NULL');
CALL wdx_add_column('footer_settings', 'copyright_text', 'VARCHAR(255) NOT NULL DEFAULT ''WoodEx Furniture. All rights reserved.''');
CALL wdx_add_column('footer_settings', 'legal_link_1_title', 'VARCHAR(120) NOT NULL DEFAULT ''Privacy Policy''');
CALL wdx_add_column('footer_settings', 'legal_link_1_url', 'VARCHAR(255) NOT NULL DEFAULT ''privacy-policy''');
CALL wdx_add_column('footer_settings', 'legal_link_2_title', 'VARCHAR(120) NOT NULL DEFAULT ''Terms of Service''');
CALL wdx_add_column('footer_settings', 'legal_link_2_url', 'VARCHAR(255) NOT NULL DEFAULT ''terms-and-conditions''');
CALL wdx_add_column('footer_settings', 'legal_link_3_title', 'VARCHAR(120) NOT NULL DEFAULT ''Sitemap''');
CALL wdx_add_column('footer_settings', 'legal_link_3_url', 'VARCHAR(255) NOT NULL DEFAULT ''sitemap.xml''');
CALL wdx_add_column('footer_settings', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

CREATE TABLE IF NOT EXISTS footer_links (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    section_key VARCHAR(20) NOT NULL,
    link_title VARCHAR(180) NOT NULL,
    link_url VARCHAR(2048) NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_footer_section (section_key, is_active, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO footer_settings (
    id, about_title, about_tagline, about_text, quick_links_title, shop_title,
    learn_more_title, contact_title, address_text, email_text, hours_text,
    phone_number, newsletter_title, newsletter_placeholder, newsletter_button_text,
    payment_logos_json, social_label, social_links_json, copyright_text,
    legal_link_1_title, legal_link_1_url, legal_link_2_title, legal_link_2_url,
    legal_link_3_title, legal_link_3_url
) VALUES (
    1,
    'Woodex',
    'Make your space work®',
    'Premium office furniture solutions for modern workspaces.',
    'Quick Links',
    'Shop',
    'Learn More',
    'Contact Us',
    '',
    'sales@woodexfurniture.pk',
    'Mon-Sat: 9am-7pm',
    '+92 42 111 WOODEX',
    'Newsletter',
    'Your email',
    '->',
    '[{"src":"assets/images/misc/meezan-bank-logo.png","alt":"Meezan Logo"},{"src":"assets/images/misc/new-Jazzcash-logo.png","alt":"Jazzcash Logo"},{"src":"assets/images/misc/Easypaisa-logo.png","alt":"EasyPaisa Logo"}]',
    '',
    '[{"icon":"bi-facebook","url":"https://www.facebook.com/woodexfurniturepk/","label":"Facebook"},{"icon":"bi-twitter-x","url":"https://twitter.com/woodexfurniture","label":"X (Twitter)"},{"icon":"bi-instagram","url":"https://www.instagram.com/woodexfurniturepk/","label":"Instagram"},{"icon":"bi-linkedin","url":"https://www.linkedin.com/company/woodexfurniture/","label":"LinkedIn"},{"icon":"bi-youtube","url":"https://www.youtube.com/","label":"YouTube"}]',
    'WoodEx Furniture. All rights reserved.',
    'Privacy Policy',
    'privacy-policy',
    'Terms of Service',
    'terms-and-conditions',
    'Sitemap',
    'sitemap.xml'
);

INSERT INTO footer_links (section_key, link_title, link_url, sort_order, is_active)
SELECT seed.section_key, seed.link_title, seed.link_url, seed.sort_order, 1
FROM (
    SELECT 'quick_links' section_key, 'About Us' link_title, 'about-us' link_url, 1 sort_order UNION ALL
    SELECT 'quick_links', 'Portfolio', 'portfolio', 2 UNION ALL
    SELECT 'quick_links', 'Blog', 'blog', 3 UNION ALL
    SELECT 'quick_links', 'Careers', 'careers', 4 UNION ALL
    SELECT 'quick_links', 'Contact', 'contact-us', 5 UNION ALL
    SELECT 'quick_links', 'Showrooms', 'showrooms.php', 6 UNION ALL
    SELECT 'quick_links', 'Series', 'categories', 7 UNION ALL
    SELECT 'quick_links', 'B2B / Markets', 'b2b-markets', 8 UNION ALL
    SELECT 'shop', 'Ergonomic Chair', 'categories/ergonomic-chair', 1 UNION ALL
    SELECT 'shop', 'Executive Desks', 'categories/executive-desks', 2 UNION ALL
    SELECT 'shop', 'Manager Tables', 'categories/manager-tables', 3 UNION ALL
    SELECT 'shop', 'Staff Tables', 'categories/staff-table', 4 UNION ALL
    SELECT 'shop', 'Meeting Tables', 'categories/meeting-table', 5 UNION ALL
    SELECT 'shop', 'Office Chairs', 'categories/office-chair', 6 UNION ALL
    SELECT 'shop', 'Workstations', 'categories/workstations', 7 UNION ALL
    SELECT 'shop', 'Cubicle Systems', 'categories/cubicle-system', 8 UNION ALL
    SELECT 'shop', 'Office Sofas', 'categories/office-sofa', 9 UNION ALL
    SELECT 'shop', 'Office Storage', 'categories/office-storage', 10 UNION ALL
    SELECT 'learn_more', 'Awards', 'awards', 1 UNION ALL
    SELECT 'learn_more', 'Ideas & Inspiration', 'ideas-and-inspiration', 2 UNION ALL
    SELECT 'learn_more', 'Terms of Use', 'terms-and-conditions', 3 UNION ALL
    SELECT 'learn_more', 'Resources', 'resources', 4 UNION ALL
    SELECT 'learn_more', 'Support', 'support', 5 UNION ALL
    SELECT 'learn_more', 'Warranty', 'warranty', 6 UNION ALL
    SELECT 'learn_more', 'Distributors', 'distributors', 7 UNION ALL
    SELECT 'learn_more', 'FAQ', 'faqs', 8
) seed
WHERE NOT EXISTS (SELECT 1 FROM footer_links LIMIT 1);

-- Top bar and sale page settings.
CREATE TABLE IF NOT EXISTS topbar_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    heading_text VARCHAR(255) NOT NULL,
    heading_url VARCHAR(2048) NOT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CALL wdx_add_column('topbar_settings', 'heading_text', 'VARCHAR(255) NOT NULL DEFAULT ''Up to 60% Off Sale Collection''');
CALL wdx_add_column('topbar_settings', 'heading_url', 'VARCHAR(2048) NOT NULL DEFAULT ''shop''');
CALL wdx_add_column('topbar_settings', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
CALL wdx_add_column('topbar_settings', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

INSERT IGNORE INTO topbar_settings (id, heading_text, heading_url, is_active)
VALUES (1, 'Up to 60% Off Sale Collection', 'shop', 1);

CREATE TABLE IF NOT EXISTS sale_page_settings (
    id TINYINT UNSIGNED PRIMARY KEY,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    hero_background VARCHAR(2048) NOT NULL DEFAULT 'assets/images/herobg.webp',
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CALL wdx_add_column('sale_page_settings', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
CALL wdx_add_column('sale_page_settings', 'hero_background', 'VARCHAR(2048) NOT NULL DEFAULT ''assets/images/herobg.webp''');
CALL wdx_add_column('sale_page_settings', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

INSERT IGNORE INTO sale_page_settings (id, is_active, hero_background)
VALUES (1, 1, 'assets/images/herobg.webp');

-- Customer reviews.
CREATE TABLE IF NOT EXISTS customer_reviews (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_name VARCHAR(150) NOT NULL,
    review_text TEXT NOT NULL,
    rating TINYINT UNSIGNED NOT NULL DEFAULT 5,
    status ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    review_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    profile_image VARCHAR(2048) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_customer_reviews_active_date (is_active, status, review_date, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CALL wdx_add_column('customer_reviews', 'customer_name', 'VARCHAR(150) NOT NULL DEFAULT ''''');
CALL wdx_add_column('customer_reviews', 'review_text', 'TEXT NULL');
CALL wdx_add_column('customer_reviews', 'rating', 'TINYINT UNSIGNED NOT NULL DEFAULT 5');
CALL wdx_add_column('customer_reviews', 'status', 'ENUM(''pending'',''approved'',''rejected'') NOT NULL DEFAULT ''pending''');
CALL wdx_add_column('customer_reviews', 'review_date', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL wdx_add_column('customer_reviews', 'profile_image', 'VARCHAR(2048) NULL');
CALL wdx_add_column('customer_reviews', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
CALL wdx_add_column('customer_reviews', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL wdx_add_column('customer_reviews', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- Projects.
CREATE TABLE IF NOT EXISTS projects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    category VARCHAR(120) NOT NULL,
    client_name VARCHAR(180) NOT NULL,
    location_city VARCHAR(120) NOT NULL,
    area_sqft VARCHAR(80) NOT NULL,
    short_description TEXT NOT NULL,
    detail_content LONGTEXT NOT NULL,
    cover_image VARCHAR(2048) NOT NULL,
    gallery_json LONGTEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_projects_slug (slug),
    KEY idx_projects_active_sort (is_active, sort_order, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CALL wdx_add_column('projects', 'title', 'VARCHAR(255) NOT NULL DEFAULT ''''');
CALL wdx_add_column('projects', 'slug', 'VARCHAR(255) NOT NULL DEFAULT ''''');
CALL wdx_add_column('projects', 'category', 'VARCHAR(120) NOT NULL DEFAULT ''''');
CALL wdx_add_column('projects', 'client_name', 'VARCHAR(180) NOT NULL DEFAULT ''''');
CALL wdx_add_column('projects', 'location_city', 'VARCHAR(120) NOT NULL DEFAULT ''''');
CALL wdx_add_column('projects', 'area_sqft', 'VARCHAR(80) NOT NULL DEFAULT ''''');
CALL wdx_add_column('projects', 'short_description', 'TEXT NULL');
CALL wdx_add_column('projects', 'detail_content', 'LONGTEXT NULL');
CALL wdx_add_column('projects', 'cover_image', 'VARCHAR(2048) NOT NULL DEFAULT ''''');
CALL wdx_add_column('projects', 'gallery_json', 'LONGTEXT NULL');
CALL wdx_add_column('projects', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
CALL wdx_add_column('projects', 'sort_order', 'INT NOT NULL DEFAULT 0');
CALL wdx_add_column('projects', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL wdx_add_column('projects', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- Quotation requests.
CREATE TABLE IF NOT EXISTS quotation_requests (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    quote_no VARCHAR(40) NOT NULL,
    customer_name VARCHAR(120) NOT NULL,
    customer_email VARCHAR(160) NOT NULL,
    customer_phone VARCHAR(60) NOT NULL,
    customer_address TEXT NULL,
    subtotal DECIMAL(12,2) NOT NULL DEFAULT 0,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    status VARCHAR(30) NOT NULL DEFAULT 'new',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_quote_no (quote_no)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CALL wdx_add_column('quotation_requests', 'quote_no', 'VARCHAR(40) NOT NULL DEFAULT ''''');
CALL wdx_add_column('quotation_requests', 'customer_name', 'VARCHAR(120) NOT NULL DEFAULT ''''');
CALL wdx_add_column('quotation_requests', 'customer_email', 'VARCHAR(160) NOT NULL DEFAULT ''''');
CALL wdx_add_column('quotation_requests', 'customer_phone', 'VARCHAR(60) NOT NULL DEFAULT ''''');
CALL wdx_add_column('quotation_requests', 'customer_address', 'TEXT NULL');
CALL wdx_add_column('quotation_requests', 'subtotal', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
CALL wdx_add_column('quotation_requests', 'total', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
CALL wdx_add_column('quotation_requests', 'status', 'VARCHAR(30) NOT NULL DEFAULT ''new''');
CALL wdx_add_column('quotation_requests', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

CREATE TABLE IF NOT EXISTS quotation_request_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    request_id INT UNSIGNED NOT NULL,
    product_id INT NOT NULL,
    variation_id INT NULL,
    product_name VARCHAR(255) NOT NULL,
    sku VARCHAR(120) NULL,
    unit_price DECIMAL(12,2) NOT NULL DEFAULT 0,
    quantity INT NOT NULL DEFAULT 1,
    line_total DECIMAL(12,2) NOT NULL DEFAULT 0,
    attributes_json TEXT NULL,
    image VARCHAR(255) NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_request_id (request_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CALL wdx_add_column('quotation_request_items', 'request_id', 'INT UNSIGNED NOT NULL DEFAULT 0');
CALL wdx_add_column('quotation_request_items', 'product_id', 'INT NOT NULL DEFAULT 0');
CALL wdx_add_column('quotation_request_items', 'variation_id', 'INT NULL');
CALL wdx_add_column('quotation_request_items', 'product_name', 'VARCHAR(255) NOT NULL DEFAULT ''''');
CALL wdx_add_column('quotation_request_items', 'sku', 'VARCHAR(120) NULL');
CALL wdx_add_column('quotation_request_items', 'unit_price', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
CALL wdx_add_column('quotation_request_items', 'quantity', 'INT NOT NULL DEFAULT 1');
CALL wdx_add_column('quotation_request_items', 'line_total', 'DECIMAL(12,2) NOT NULL DEFAULT 0');
CALL wdx_add_column('quotation_request_items', 'attributes_json', 'TEXT NULL');
CALL wdx_add_column('quotation_request_items', 'image', 'VARCHAR(255) NULL');
CALL wdx_add_column('quotation_request_items', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');

-- SEO redirects.
CREATE TABLE IF NOT EXISTS url_redirects (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    old_url VARCHAR(2048) NOT NULL,
    old_url_normalized VARCHAR(2048) NOT NULL,
    old_url_hash CHAR(32) NOT NULL,
    new_url VARCHAR(2048) NOT NULL,
    http_code SMALLINT UNSIGNED NOT NULL DEFAULT 301,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    hit_count INT UNSIGNED NOT NULL DEFAULT 0,
    last_hit_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_old_url_hash (old_url_hash),
    KEY idx_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CALL wdx_add_column('url_redirects', 'old_url', 'VARCHAR(2048) NOT NULL DEFAULT ''''');
CALL wdx_add_column('url_redirects', 'old_url_normalized', 'VARCHAR(2048) NOT NULL DEFAULT ''''');
CALL wdx_add_column('url_redirects', 'old_url_hash', 'CHAR(32) NOT NULL DEFAULT ''''');
CALL wdx_add_column('url_redirects', 'new_url', 'VARCHAR(2048) NOT NULL DEFAULT ''''');
CALL wdx_add_column('url_redirects', 'http_code', 'SMALLINT UNSIGNED NOT NULL DEFAULT 301');
CALL wdx_add_column('url_redirects', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1');
CALL wdx_add_column('url_redirects', 'hit_count', 'INT UNSIGNED NOT NULL DEFAULT 0');
CALL wdx_add_column('url_redirects', 'last_hit_at', 'DATETIME NULL');
CALL wdx_add_column('url_redirects', 'created_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP');
CALL wdx_add_column('url_redirects', 'updated_at', 'DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP');

-- SMTP/email settings.
CREATE TABLE IF NOT EXISTS email_settings (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    enabled TINYINT(1) NOT NULL DEFAULT 0,
    smtp_host VARCHAR(255) NOT NULL DEFAULT '',
    smtp_port INT UNSIGNED NOT NULL DEFAULT 465,
    smtp_secure VARCHAR(10) NOT NULL DEFAULT 'ssl',
    smtp_username VARCHAR(255) NOT NULL DEFAULT '',
    smtp_password VARCHAR(255) NOT NULL DEFAULT '',
    from_email VARCHAR(255) NOT NULL DEFAULT '',
    from_name VARCHAR(120) NOT NULL DEFAULT 'Woodex',
    reply_to_email VARCHAR(255) NOT NULL DEFAULT '',
    reply_to_name VARCHAR(120) NOT NULL DEFAULT 'Woodex',
    order_notify_email VARCHAR(255) NOT NULL DEFAULT '',
    contact_team_email VARCHAR(255) NOT NULL DEFAULT '',
    support_email VARCHAR(255) NOT NULL DEFAULT '',
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CALL wdx_add_column('email_settings', 'enabled', 'TINYINT(1) NOT NULL DEFAULT 0');
CALL wdx_add_column('email_settings', 'smtp_host', 'VARCHAR(255) NOT NULL DEFAULT ''''');
CALL wdx_add_column('email_settings', 'smtp_port', 'INT UNSIGNED NOT NULL DEFAULT 465');
CALL wdx_add_column('email_settings', 'smtp_secure', 'VARCHAR(10) NOT NULL DEFAULT ''ssl''');
CALL wdx_add_column('email_settings', 'smtp_username', 'VARCHAR(255) NOT NULL DEFAULT ''''');
CALL wdx_add_column('email_settings', 'smtp_password', 'VARCHAR(255) NOT NULL DEFAULT ''''');
CALL wdx_add_column('email_settings', 'from_email', 'VARCHAR(255) NOT NULL DEFAULT ''''');
CALL wdx_add_column('email_settings', 'from_name', 'VARCHAR(120) NOT NULL DEFAULT ''Woodex''');
CALL wdx_add_column('email_settings', 'reply_to_email', 'VARCHAR(255) NOT NULL DEFAULT ''''');
CALL wdx_add_column('email_settings', 'reply_to_name', 'VARCHAR(120) NOT NULL DEFAULT ''Woodex''');
CALL wdx_add_column('email_settings', 'order_notify_email', 'VARCHAR(255) NOT NULL DEFAULT ''''');
CALL wdx_add_column('email_settings', 'contact_team_email', 'VARCHAR(255) NOT NULL DEFAULT ''''');
CALL wdx_add_column('email_settings', 'support_email', 'VARCHAR(255) NOT NULL DEFAULT ''''');
CALL wdx_add_column('email_settings', 'updated_at', 'TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP');

INSERT IGNORE INTO email_settings (id) VALUES (1);

DROP PROCEDURE IF EXISTS wdx_backfill_product_categories;
DROP PROCEDURE IF EXISTS wdx_add_column;
