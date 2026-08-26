-- Quetta AgriLink: saved, account-scoped date windows for dashboard activity summaries.
CREATE TABLE IF NOT EXISTS dashboard_activity_presets (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    preset_name VARCHAR(60) NOT NULL,
    activity_from DATE NULL,
    activity_to DATE NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_dashboard_activity_preset_user_name (user_id, preset_name),
    CONSTRAINT fk_dashboard_activity_presets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
