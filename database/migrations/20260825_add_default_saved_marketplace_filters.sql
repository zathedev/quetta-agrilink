-- Run once after database/migrations/20260825_add_saved_marketplace_filters.sql.
-- One user may choose one saved marketplace filter as the criteria for in-app listing-match alerts.
ALTER TABLE saved_marketplace_filters
    ADD COLUMN is_default TINYINT(1) NOT NULL DEFAULT 0 AFTER sort_key,
    ADD INDEX idx_saved_marketplace_filter_default (user_id, is_default);
