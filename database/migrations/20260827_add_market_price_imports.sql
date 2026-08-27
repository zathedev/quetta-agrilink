-- Local-only administrator market-price import metadata and source context.
ALTER TABLE market_prices
    ADD COLUMN IF NOT EXISTS source_name VARCHAR(160) NOT NULL DEFAULT 'Administrator-provided local record' AFTER notes,
    ADD COLUMN IF NOT EXISTS source_reference VARCHAR(255) NULL AFTER source_name;

CREATE TABLE IF NOT EXISTS market_price_import_batches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    imported_by_user_id BIGINT UNSIGNED NOT NULL,
    source_name VARCHAR(160) NOT NULL,
    source_reference VARCHAR(255) NULL,
    original_filename VARCHAR(190) NOT NULL,
    total_rows INT UNSIGNED NOT NULL,
    inserted_rows INT UNSIGNED NOT NULL DEFAULT 0,
    updated_rows INT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_market_price_batch_importer FOREIGN KEY (imported_by_user_id) REFERENCES users(id),
    INDEX idx_market_price_batches_created (created_at),
    INDEX idx_market_price_batches_importer (imported_by_user_id, created_at)
) ENGINE=InnoDB;
