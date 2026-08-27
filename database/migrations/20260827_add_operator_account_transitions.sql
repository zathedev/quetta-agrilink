-- Administrator audit register for replacing or retiring the documented local development accounts.
CREATE TABLE IF NOT EXISTS operator_account_transitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    administrator_id BIGINT UNSIGNED NOT NULL,
    created_user_id BIGINT UNSIGNED NULL,
    archived_user_id BIGINT UNSIGNED NULL,
    action ENUM('operator_created','development_account_archived') NOT NULL,
    details JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_operator_transition_administrator FOREIGN KEY (administrator_id) REFERENCES users(id),
    CONSTRAINT fk_operator_transition_created_user FOREIGN KEY (created_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_operator_transition_archived_user FOREIGN KEY (archived_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_operator_transition_created (created_at),
    INDEX idx_operator_transition_action (action)
) ENGINE=InnoDB;
