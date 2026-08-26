-- Run once after importing database/quetta_agrilink.sql on the local XAMPP MySQL instance.
-- Supports administrator-issued, one-time password resets without external email delivery.
CREATE TABLE IF NOT EXISTS local_password_recovery_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    requested_ip VARCHAR(45) NULL,
    issued_by_user_id BIGINT UNSIGNED NULL,
    issued_at DATETIME NULL,
    selector CHAR(24) NULL UNIQUE,
    token_hash CHAR(64) NULL,
    expires_at DATETIME NULL,
    used_at DATETIME NULL,
    revoked_at DATETIME NULL,
    revoked_by_user_id BIGINT UNSIGNED NULL,
    INDEX idx_local_recovery_user_requested (user_id, requested_at),
    INDEX idx_local_recovery_active (expires_at, used_at, revoked_at),
    CONSTRAINT fk_local_recovery_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_local_recovery_issuer FOREIGN KEY (issued_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_local_recovery_revoker FOREIGN KEY (revoked_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;
