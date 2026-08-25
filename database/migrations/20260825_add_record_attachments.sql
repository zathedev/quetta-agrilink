-- Run once after importing database/quetta_agrilink.sql on the local XAMPP MySQL instance.
CREATE TABLE IF NOT EXISTS record_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    entity_type VARCHAR(40) NOT NULL,
    entity_id BIGINT UNSIGNED NOT NULL,
    uploader_user_id BIGINT UNSIGNED NOT NULL,
    original_name VARCHAR(180) NOT NULL,
    stored_name VARCHAR(80) NOT NULL UNIQUE,
    relative_path VARCHAR(255) NOT NULL UNIQUE,
    mime_type VARCHAR(100) NOT NULL,
    file_size INT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_record_attachment_entity (entity_type, entity_id),
    INDEX idx_record_attachment_uploader (uploader_user_id),
    CONSTRAINT fk_record_attachment_uploader FOREIGN KEY (uploader_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;
