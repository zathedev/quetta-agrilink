-- Run once after 20260826_add_local_password_recovery.sql on the local XAMPP MySQL instance.
-- Keeps administrator verification evidence separate from the one-time reset token; never store passwords or reset links here.
ALTER TABLE local_password_recovery_requests
    ADD COLUMN verification_notes TEXT NULL AFTER requested_ip,
    ADD COLUMN verified_by_user_id BIGINT UNSIGNED NULL AFTER verification_notes,
    ADD COLUMN verified_at DATETIME NULL AFTER verified_by_user_id,
    ADD CONSTRAINT fk_local_recovery_verifier FOREIGN KEY (verified_by_user_id) REFERENCES users(id) ON DELETE SET NULL;
