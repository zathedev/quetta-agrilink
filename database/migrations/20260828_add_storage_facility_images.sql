-- Run once on an existing Quetta AgriLink database created before 2026-08-28.
CREATE TABLE IF NOT EXISTS storage_facility_images (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    facility_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    alt_text VARCHAR(180) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_facility_image_facility FOREIGN KEY (facility_id) REFERENCES storage_facilities(id) ON DELETE CASCADE,
    INDEX idx_facility_images (facility_id, is_primary, sort_order)
) ENGINE=InnoDB;
