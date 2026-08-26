-- Quetta AgriLink: administrator-recorded account contact verification.
-- This migration deliberately records an offline verification decision; it does not send email/SMS or claim automated verification.
CREATE TABLE IF NOT EXISTS account_contact_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    verified_email_at DATETIME NULL,
    verified_phone_at DATETIME NULL,
    verification_notes VARCHAR(800) NOT NULL,
    verified_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_account_contact_verifications_user (user_id),
    KEY idx_account_contact_verifications_admin (verified_by_user_id),
    CONSTRAINT fk_account_contact_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_account_contact_verifications_admin FOREIGN KEY (verified_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
