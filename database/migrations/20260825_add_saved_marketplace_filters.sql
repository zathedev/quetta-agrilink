-- Run once after importing database/quetta_agrilink.sql on the local XAMPP MySQL instance.
-- Run after 20260825_add_record_attachments.sql when both enhancements are installed.
CREATE TABLE IF NOT EXISTS saved_marketplace_filters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    category_id BIGINT UNSIGNED NULL,
    district VARCHAR(100) NULL,
    grade VARCHAR(10) NULL,
    min_price DECIMAL(12,2) NULL,
    max_price DECIMAL(12,2) NULL,
    min_quantity DECIMAL(12,2) NULL,
    sort_key VARCHAR(30) NOT NULL DEFAULT 'recent',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_saved_marketplace_filter_name (user_id, name),
    INDEX idx_saved_marketplace_filter_user (user_id, created_at),
    CONSTRAINT fk_saved_marketplace_filter_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_saved_marketplace_filter_category FOREIGN KEY (category_id) REFERENCES produce_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;
