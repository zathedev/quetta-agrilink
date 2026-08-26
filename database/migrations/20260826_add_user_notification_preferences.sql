-- Quetta AgriLink: account-scoped in-app notification preferences for local operation.
CREATE TABLE IF NOT EXISTS user_notification_preferences (
    user_id BIGINT UNSIGNED NOT NULL,
    marketplace_match_alerts_enabled TINYINT(1) NOT NULL DEFAULT 1,
    browser_chime_enabled TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    CONSTRAINT fk_user_notification_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
