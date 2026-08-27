-- Local XAMPP/MariaDB migration: account-scoped cold-storage search shortcuts.
CREATE TABLE IF NOT EXISTS saved_storage_searches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    category_id BIGINT UNSIGNED NULL,
    district VARCHAR(100) NULL,
    storage_type VARCHAR(40) NULL,
    min_capacity DECIMAL(14,2) NULL,
    max_price DECIMAL(12,2) NULL,
    sort_key VARCHAR(30) NOT NULL DEFAULT 'capacity_high',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_saved_storage_search_name (user_id, name),
    INDEX idx_saved_storage_search_user (user_id, updated_at),
    CONSTRAINT fk_saved_storage_search_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_saved_storage_search_category FOREIGN KEY (category_id) REFERENCES produce_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;
