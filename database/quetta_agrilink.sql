-- Quetta AgriLink
-- Import this file through phpMyAdmin to create the local development database.
-- Demo data is fictional and is only for local demonstration. No reviews, ratings, or testimonials are seeded.

CREATE DATABASE IF NOT EXISTS quetta_agrilink CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE quetta_agrilink;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS saved_storage_searches;
DROP TABLE IF EXISTS password_reset_tokens;
DROP TABLE IF EXISTS audit_logs;
DROP TABLE IF EXISTS payments;
DROP TABLE IF EXISTS reviews;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS messages;
DROP TABLE IF EXISTS support_request_events;
DROP TABLE IF EXISTS support_request_messages;
DROP TABLE IF EXISTS support_requests;
DROP TABLE IF EXISTS market_price_import_batches;
DROP TABLE IF EXISTS market_prices;
DROP TABLE IF EXISTS transport_status_history;
DROP TABLE IF EXISTS transport_requests;
DROP TABLE IF EXISTS transport_service_areas;
DROP TABLE IF EXISTS vehicles;
DROP TABLE IF EXISTS storage_booking_status_history;
DROP TABLE IF EXISTS storage_bookings;
DROP TABLE IF EXISTS facility_supported_products;
DROP TABLE IF EXISTS storage_facilities;
DROP TABLE IF EXISTS order_status_history;
DROP TABLE IF EXISTS order_items;
DROP TABLE IF EXISTS orders;
DROP TABLE IF EXISTS offer_events;
DROP TABLE IF EXISTS offers;
DROP TABLE IF EXISTS favorites;
DROP TABLE IF EXISTS produce_images;
DROP TABLE IF EXISTS produce_listings;
DROP TABLE IF EXISTS produce_categories;
DROP TABLE IF EXISTS transport_providers;
DROP TABLE IF EXISTS storage_providers;
DROP TABLE IF EXISTS buyer_profiles;
DROP TABLE IF EXISTS farmer_profiles;
DROP TABLE IF EXISTS locations;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS roles;

CREATE TABLE roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    slug VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(80) NOT NULL,
    description VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id TINYINT UNSIGNED NOT NULL,
    full_name VARCHAR(120) NOT NULL,
    email VARCHAR(190) NOT NULL UNIQUE,
    phone VARCHAR(30) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255) NULL,
    status ENUM('pending','active','suspended','archived') NOT NULL DEFAULT 'pending',
    last_login_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
    INDEX idx_users_role_status (role_id, status)
) ENGINE=InnoDB;

CREATE TABLE locations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    province VARCHAR(100) NOT NULL DEFAULT 'Balochistan',
    district VARCHAR(100) NOT NULL,
    tehsil VARCHAR(100) NULL,
    area VARCHAR(150) NULL,
    address_line VARCHAR(255) NULL,
    latitude DECIMAL(10,7) NULL,
    longitude DECIMAL(10,7) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_locations_district (district),
    INDEX idx_locations_coordinates (latitude, longitude)
) ENGINE=InnoDB;

CREATE TABLE farmer_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    farm_name VARCHAR(160) NULL,
    farm_location_id BIGINT UNSIGNED NULL,
    farm_size_acres DECIMAL(10,2) NULL,
    bio TEXT NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_farmer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_farmer_location FOREIGN KEY (farm_location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE buyer_profiles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    business_name VARCHAR(160) NULL,
    business_type VARCHAR(100) NULL,
    location_id BIGINT UNSIGNED NULL,
    tax_reference VARCHAR(80) NULL,
    bio TEXT NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_buyer_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_buyer_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE storage_providers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    business_name VARCHAR(160) NULL,
    registration_number VARCHAR(100) NULL,
    location_id BIGINT UNSIGNED NULL,
    bio TEXT NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_storage_provider_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_storage_provider_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE transport_providers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL UNIQUE,
    company_name VARCHAR(160) NULL,
    registration_number VARCHAR(100) NULL,
    location_id BIGINT UNSIGNED NULL,
    bio TEXT NULL,
    verified_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_transport_provider_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_transport_provider_location FOREIGN KEY (location_id) REFERENCES locations(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE produce_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(120) NOT NULL UNIQUE,
    description TEXT NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE produce_listings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    farmer_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    description TEXT NULL,
    grade ENUM('A','B','C','Mixed') NOT NULL DEFAULT 'A',
    quantity_available DECIMAL(14,2) NOT NULL,
    unit VARCHAR(30) NOT NULL DEFAULT 'kg',
    expected_price DECIMAL(14,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'PKR',
    harvest_date DATE NULL,
    available_from DATE NULL,
    minimum_order_quantity DECIMAL(14,2) NOT NULL DEFAULT 1,
    status ENUM('draft','active','paused','sold_out','archived') NOT NULL DEFAULT 'draft',
    published_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_listing_farmer FOREIGN KEY (farmer_id) REFERENCES users(id),
    CONSTRAINT fk_listing_category FOREIGN KEY (category_id) REFERENCES produce_categories(id),
    CONSTRAINT fk_listing_location FOREIGN KEY (location_id) REFERENCES locations(id),
    INDEX idx_listing_browse (status, category_id, location_id, expected_price),
    INDEX idx_listing_farmer_status (farmer_id, status),
    INDEX idx_listing_harvest_date (harvest_date)
) ENGINE=InnoDB;

CREATE TABLE produce_images (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id BIGINT UNSIGNED NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    alt_text VARCHAR(180) NULL,
    is_primary TINYINT(1) NOT NULL DEFAULT 0,
    sort_order SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_produce_image_listing FOREIGN KEY (listing_id) REFERENCES produce_listings(id) ON DELETE CASCADE,
    INDEX idx_produce_images_listing (listing_id, is_primary, sort_order)
) ENGINE=InnoDB;

CREATE TABLE favorites (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    buyer_id BIGINT UNSIGNED NOT NULL,
    listing_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_favorite_buyer FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_favorite_listing FOREIGN KEY (listing_id) REFERENCES produce_listings(id) ON DELETE CASCADE,
    UNIQUE KEY uq_favorite (buyer_id, listing_id)
) ENGINE=InnoDB;

CREATE TABLE offers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    listing_id BIGINT UNSIGNED NOT NULL,
    buyer_id BIGINT UNSIGNED NOT NULL,
    farmer_id BIGINT UNSIGNED NOT NULL,
    quantity DECIMAL(14,2) NOT NULL,
    offered_price DECIMAL(14,2) NOT NULL,
    total_amount DECIMAL(16,2) NOT NULL,
    message TEXT NULL,
    status ENUM('pending','accepted','rejected','countered','withdrawn','expired') NOT NULL DEFAULT 'pending',
    responded_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_offer_listing FOREIGN KEY (listing_id) REFERENCES produce_listings(id),
    CONSTRAINT fk_offer_buyer FOREIGN KEY (buyer_id) REFERENCES users(id),
    CONSTRAINT fk_offer_farmer FOREIGN KEY (farmer_id) REFERENCES users(id),
    INDEX idx_offers_farmer_status (farmer_id, status, created_at),
    INDEX idx_offers_buyer_status (buyer_id, status, created_at)
) ENGINE=InnoDB;

CREATE TABLE offer_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    offer_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    event_type ENUM('created','countered','accepted','rejected','withdrawn','expired') NOT NULL,
    quantity DECIMAL(14,2) NULL,
    price DECIMAL(14,2) NULL,
    note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_offer_event_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE CASCADE,
    CONSTRAINT fk_offer_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
    INDEX idx_offer_events_offer (offer_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(32) NOT NULL UNIQUE,
    offer_id BIGINT UNSIGNED NULL UNIQUE,
    buyer_id BIGINT UNSIGNED NOT NULL,
    farmer_id BIGINT UNSIGNED NOT NULL,
    status ENUM('pending','confirmed','storage_required','transport_required','ready_for_pickup','picked_up','in_transit','delivered','completed','cancelled') NOT NULL DEFAULT 'pending',
    subtotal DECIMAL(16,2) NOT NULL,
    storage_cost DECIMAL(16,2) NOT NULL DEFAULT 0,
    transport_cost DECIMAL(16,2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(16,2) NOT NULL,
    notes TEXT NULL,
    confirmed_at DATETIME NULL,
    completed_at DATETIME NULL,
    cancelled_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_offer FOREIGN KEY (offer_id) REFERENCES offers(id) ON DELETE SET NULL,
    CONSTRAINT fk_order_buyer FOREIGN KEY (buyer_id) REFERENCES users(id),
    CONSTRAINT fk_order_farmer FOREIGN KEY (farmer_id) REFERENCES users(id),
    INDEX idx_orders_buyer_status (buyer_id, status, created_at),
    INDEX idx_orders_farmer_status (farmer_id, status, created_at)
) ENGINE=InnoDB;

CREATE TABLE order_items (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    listing_id BIGINT UNSIGNED NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    produce_name VARCHAR(160) NOT NULL,
    grade ENUM('A','B','C','Mixed') NOT NULL,
    quantity DECIMAL(14,2) NOT NULL,
    unit VARCHAR(30) NOT NULL,
    unit_price DECIMAL(14,2) NOT NULL,
    line_total DECIMAL(16,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_item_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_item_listing FOREIGN KEY (listing_id) REFERENCES produce_listings(id) ON DELETE SET NULL,
    CONSTRAINT fk_order_item_category FOREIGN KEY (category_id) REFERENCES produce_categories(id)
) ENGINE=InnoDB;

CREATE TABLE order_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    status ENUM('pending','confirmed','storage_required','transport_required','ready_for_pickup','picked_up','in_transit','delivered','completed','cancelled') NOT NULL,
    changed_by_user_id BIGINT UNSIGNED NULL,
    notes VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_history_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    CONSTRAINT fk_order_history_user FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_order_history_order (order_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE storage_facilities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(160) NOT NULL,
    description TEXT NULL,
    storage_type ENUM('cold_storage','controlled_atmosphere','warehouse','hybrid') NOT NULL DEFAULT 'cold_storage',
    total_capacity_kg DECIMAL(14,2) NOT NULL,
    available_capacity_kg DECIMAL(14,2) NOT NULL,
    price_per_kg_day DECIMAL(12,4) NOT NULL,
    status ENUM('draft','active','paused','archived') NOT NULL DEFAULT 'draft',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_facility_provider FOREIGN KEY (provider_id) REFERENCES storage_providers(id),
    CONSTRAINT fk_facility_location FOREIGN KEY (location_id) REFERENCES locations(id),
    INDEX idx_facilities_browse (status, location_id, storage_type, available_capacity_kg)
) ENGINE=InnoDB;

CREATE TABLE facility_supported_products (
    facility_id BIGINT UNSIGNED NOT NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (facility_id, category_id),
    CONSTRAINT fk_facility_product_facility FOREIGN KEY (facility_id) REFERENCES storage_facilities(id) ON DELETE CASCADE,
    CONSTRAINT fk_facility_product_category FOREIGN KEY (category_id) REFERENCES produce_categories(id)
) ENGINE=InnoDB;

CREATE TABLE storage_facility_images (
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

CREATE TABLE storage_bookings (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(32) NOT NULL UNIQUE,
    farmer_id BIGINT UNSIGNED NOT NULL,
    facility_id BIGINT UNSIGNED NOT NULL,
    listing_id BIGINT UNSIGNED NULL,
    category_id BIGINT UNSIGNED NOT NULL,
    quantity_kg DECIMAL(14,2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    price_per_kg_day DECIMAL(12,4) NOT NULL,
    estimated_cost DECIMAL(16,2) NOT NULL,
    status ENUM('requested','approved','rejected','active','completed','cancelled') NOT NULL DEFAULT 'requested',
    provider_note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_storage_booking_farmer FOREIGN KEY (farmer_id) REFERENCES users(id),
    CONSTRAINT fk_storage_booking_facility FOREIGN KEY (facility_id) REFERENCES storage_facilities(id),
    CONSTRAINT fk_storage_booking_listing FOREIGN KEY (listing_id) REFERENCES produce_listings(id) ON DELETE SET NULL,
    CONSTRAINT fk_storage_booking_category FOREIGN KEY (category_id) REFERENCES produce_categories(id),
    INDEX idx_storage_booking_facility_status (facility_id, status, start_date),
    INDEX idx_storage_booking_farmer_status (farmer_id, status, created_at)
) ENGINE=InnoDB;

CREATE TABLE storage_booking_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    booking_id BIGINT UNSIGNED NOT NULL,
    status ENUM('requested','approved','rejected','active','completed','cancelled') NOT NULL,
    changed_by_user_id BIGINT UNSIGNED NULL,
    note VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_storage_history_booking FOREIGN KEY (booking_id) REFERENCES storage_bookings(id) ON DELETE CASCADE,
    CONSTRAINT fk_storage_history_user FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_storage_history_booking (booking_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE vehicles (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id BIGINT UNSIGNED NOT NULL,
    vehicle_type VARCHAR(100) NOT NULL,
    registration_number VARCHAR(50) NOT NULL,
    capacity_kg DECIMAL(14,2) NOT NULL,
    is_refrigerated TINYINT(1) NOT NULL DEFAULT 0,
    price_per_km DECIMAL(12,2) NULL,
    status ENUM('available','busy','maintenance','inactive') NOT NULL DEFAULT 'available',
    image_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_vehicle_provider FOREIGN KEY (provider_id) REFERENCES transport_providers(id) ON DELETE CASCADE,
    UNIQUE KEY uq_vehicle_registration (registration_number),
    INDEX idx_vehicle_search (provider_id, status, is_refrigerated, capacity_kg)
) ENGINE=InnoDB;

CREATE TABLE transport_service_areas (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    provider_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_service_area_provider FOREIGN KEY (provider_id) REFERENCES transport_providers(id) ON DELETE CASCADE,
    CONSTRAINT fk_service_area_location FOREIGN KEY (location_id) REFERENCES locations(id),
    UNIQUE KEY uq_service_area (provider_id, location_id)
) ENGINE=InnoDB;

CREATE TABLE transport_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(32) NOT NULL UNIQUE,
    farmer_id BIGINT UNSIGNED NOT NULL,
    provider_id BIGINT UNSIGNED NULL,
    vehicle_id BIGINT UNSIGNED NULL,
    order_id BIGINT UNSIGNED NULL,
    listing_id BIGINT UNSIGNED NULL,
    pickup_location_id BIGINT UNSIGNED NOT NULL,
    delivery_location_id BIGINT UNSIGNED NOT NULL,
    produce_description VARCHAR(160) NOT NULL,
    quantity_kg DECIMAL(14,2) NOT NULL,
    required_vehicle_type VARCHAR(100) NULL,
    requires_refrigeration TINYINT(1) NOT NULL DEFAULT 0,
    pickup_date DATE NOT NULL,
    estimated_price DECIMAL(16,2) NULL,
    final_price DECIMAL(16,2) NULL,
    driver_name VARCHAR(120) NULL,
    driver_phone VARCHAR(30) NULL,
    status ENUM('requested','accepted','driver_assigned','pickup_scheduled','picked_up','in_transit','delivered','cancelled') NOT NULL DEFAULT 'requested',
    provider_note TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_transport_request_farmer FOREIGN KEY (farmer_id) REFERENCES users(id),
    CONSTRAINT fk_transport_request_provider FOREIGN KEY (provider_id) REFERENCES transport_providers(id) ON DELETE SET NULL,
    CONSTRAINT fk_transport_request_vehicle FOREIGN KEY (vehicle_id) REFERENCES vehicles(id) ON DELETE SET NULL,
    CONSTRAINT fk_transport_request_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    CONSTRAINT fk_transport_request_listing FOREIGN KEY (listing_id) REFERENCES produce_listings(id) ON DELETE SET NULL,
    CONSTRAINT fk_transport_pickup FOREIGN KEY (pickup_location_id) REFERENCES locations(id),
    CONSTRAINT fk_transport_delivery FOREIGN KEY (delivery_location_id) REFERENCES locations(id),
    INDEX idx_transport_provider_status (provider_id, status, pickup_date),
    INDEX idx_transport_farmer_status (farmer_id, status, pickup_date)
) ENGINE=InnoDB;

CREATE TABLE transport_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    transport_request_id BIGINT UNSIGNED NOT NULL,
    status ENUM('requested','accepted','driver_assigned','pickup_scheduled','picked_up','in_transit','delivered','cancelled') NOT NULL,
    changed_by_user_id BIGINT UNSIGNED NULL,
    note VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_transport_history_request FOREIGN KEY (transport_request_id) REFERENCES transport_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_transport_history_user FOREIGN KEY (changed_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_transport_history_request (transport_request_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE market_prices (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    category_id BIGINT UNSIGNED NOT NULL,
    location_id BIGINT UNSIGNED NOT NULL,
    recorded_by_user_id BIGINT UNSIGNED NULL,
    price_date DATE NOT NULL,
    min_price DECIMAL(14,2) NOT NULL,
    max_price DECIMAL(14,2) NOT NULL,
    average_price DECIMAL(14,2) NOT NULL,
    unit VARCHAR(30) NOT NULL DEFAULT 'kg',
    notes VARCHAR(500) NULL,
    source_name VARCHAR(160) NOT NULL DEFAULT 'Administrator-provided local record',
    source_reference VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_market_price_category FOREIGN KEY (category_id) REFERENCES produce_categories(id),
    CONSTRAINT fk_market_price_location FOREIGN KEY (location_id) REFERENCES locations(id),
    CONSTRAINT fk_market_price_recorder FOREIGN KEY (recorded_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    UNIQUE KEY uq_market_price_daily (category_id, location_id, price_date, unit),
    INDEX idx_market_prices_date (price_date, category_id)
) ENGINE=InnoDB;

CREATE TABLE market_price_import_batches (
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

CREATE TABLE support_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference_code VARCHAR(32) NOT NULL UNIQUE,
    requester_user_id BIGINT UNSIGNED NOT NULL,
    assigned_to_user_id BIGINT UNSIGNED NULL,
    category VARCHAR(60) NOT NULL,
    routed_role VARCHAR(40) NOT NULL,
    subject VARCHAR(160) NOT NULL,
    status ENUM('open','in_progress','waiting_on_requester','resolved','closed') NOT NULL DEFAULT 'open',
    closed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_support_request_requester FOREIGN KEY (requester_user_id) REFERENCES users(id),
    CONSTRAINT fk_support_request_assignee FOREIGN KEY (assigned_to_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_support_request_requester (requester_user_id, status, updated_at),
    INDEX idx_support_request_queue (routed_role, assigned_to_user_id, status, updated_at),
    INDEX idx_support_request_status (status, updated_at)
) ENGINE=InnoDB;

CREATE TABLE support_request_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    support_request_id BIGINT UNSIGNED NOT NULL,
    author_user_id BIGINT UNSIGNED NOT NULL,
    body VARCHAR(2000) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_support_message_request FOREIGN KEY (support_request_id) REFERENCES support_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_support_message_author FOREIGN KEY (author_user_id) REFERENCES users(id),
    INDEX idx_support_messages_request (support_request_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE support_request_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    support_request_id BIGINT UNSIGNED NOT NULL,
    actor_user_id BIGINT UNSIGNED NOT NULL,
    event_type VARCHAR(40) NOT NULL,
    from_status VARCHAR(40) NULL,
    to_status VARCHAR(40) NULL,
    note VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_support_event_request FOREIGN KEY (support_request_id) REFERENCES support_requests(id) ON DELETE CASCADE,
    CONSTRAINT fk_support_event_actor FOREIGN KEY (actor_user_id) REFERENCES users(id),
    INDEX idx_support_events_request (support_request_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sender_id BIGINT UNSIGNED NOT NULL,
    recipient_id BIGINT UNSIGNED NOT NULL,
    listing_id BIGINT UNSIGNED NULL,
    order_id BIGINT UNSIGNED NULL,
    body TEXT NOT NULL,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_message_sender FOREIGN KEY (sender_id) REFERENCES users(id),
    CONSTRAINT fk_message_recipient FOREIGN KEY (recipient_id) REFERENCES users(id),
    CONSTRAINT fk_message_listing FOREIGN KEY (listing_id) REFERENCES produce_listings(id) ON DELETE SET NULL,
    CONSTRAINT fk_message_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    INDEX idx_messages_inbox (recipient_id, read_at, created_at),
    INDEX idx_messages_context (listing_id, order_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    type VARCHAR(80) NOT NULL,
    title VARCHAR(180) NOT NULL,
    body VARCHAR(500) NOT NULL,
    action_url VARCHAR(255) NULL,
    entity_type VARCHAR(80) NULL,
    entity_id BIGINT UNSIGNED NULL,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_notifications_unread (user_id, read_at, created_at)
) ENGINE=InnoDB;

CREATE TABLE announcements (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    author_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(180) NOT NULL,
    body TEXT NOT NULL,
    audience_role VARCHAR(40) NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_announcement_author FOREIGN KEY (author_id) REFERENCES users(id),
    INDEX idx_announcements_active (is_active, starts_at, ends_at)
) ENGINE=InnoDB;

CREATE TABLE reviews (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reviewer_id BIGINT UNSIGNED NOT NULL,
    reviewed_user_id BIGINT UNSIGNED NOT NULL,
    order_id BIGINT UNSIGNED NULL,
    rating TINYINT UNSIGNED NOT NULL,
    comment TEXT NULL,
    status ENUM('pending','published','hidden') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_review_reviewer FOREIGN KEY (reviewer_id) REFERENCES users(id),
    CONSTRAINT fk_review_reviewed FOREIGN KEY (reviewed_user_id) REFERENCES users(id),
    CONSTRAINT fk_review_order FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL,
    CONSTRAINT chk_review_rating CHECK (rating BETWEEN 1 AND 5),
    UNIQUE KEY uq_review_order_reviewer (order_id, reviewer_id)
) ENGINE=InnoDB;

CREATE TABLE payments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    payer_id BIGINT UNSIGNED NOT NULL,
    payee_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(16,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'PKR',
    payment_method VARCHAR(80) NULL,
    external_reference VARCHAR(160) NULL,
    status ENUM('pending','authorized','paid','failed','refunded','cancelled') NOT NULL DEFAULT 'pending',
    paid_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_payment_order FOREIGN KEY (order_id) REFERENCES orders(id),
    CONSTRAINT fk_payment_payer FOREIGN KEY (payer_id) REFERENCES users(id),
    CONSTRAINT fk_payment_payee FOREIGN KEY (payee_id) REFERENCES users(id),
    INDEX idx_payments_order_status (order_id, status)
) ENGINE=InnoDB;

CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    actor_user_id BIGINT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(100) NOT NULL,
    entity_id BIGINT UNSIGNED NULL,
    metadata JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_actor FOREIGN KEY (actor_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_entity (entity_type, entity_id, created_at),
    INDEX idx_audit_actor (actor_user_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_reset_token_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_reset_token_expiry (expires_at)
) ENGINE=InnoDB;

-- Required reference rows and clearly labelled fictional demonstration data.
-- All five accounts use the password: AgriLinkDemo2026!
INSERT INTO roles (id, slug, name, description) VALUES
(1, 'farmer', 'Farmer', 'Grower and produce supplier'),
(2, 'buyer', 'Buyer', 'Produce buyer or business purchaser'),
(3, 'storage_provider', 'Cold Storage Provider', 'Cold storage facility operator'),
(4, 'transport_provider', 'Transport Provider', 'Agricultural transport operator'),
(5, 'admin', 'Administrator', 'Platform administrator');

INSERT INTO locations (id, province, district, tehsil, area, latitude, longitude) VALUES
(1, 'Balochistan', 'Quetta', 'Quetta City', 'Sariab Road', 30.1798000, 66.9750000),
(2, 'Balochistan', 'Pishin', 'Pishin', 'Surkhab', 30.5811000, 67.0115000),
(3, 'Balochistan', 'Chaman', 'Chaman', 'City Area', 30.9200000, 66.4525000),
(4, 'Balochistan', 'Ziarat', 'Ziarat', 'Kharwari Baba', 30.3825000, 67.7257000),
(5, 'Balochistan', 'Mastung', 'Mastung', 'Kardigap', 29.7997000, 66.8462000);

INSERT INTO users (id, role_id, full_name, email, phone, password_hash, status) VALUES
(1, 1, 'Demo Farmer', 'farmer.demo@quettaagrilink.test', '03000000001', '$2y$12$qJm7GaKM2DY7An3gwVcYcusm3n16fpAOM8UU/ZdQHZraOUtXG1dsa', 'active'),
(2, 2, 'Demo Buyer', 'buyer.demo@quettaagrilink.test', '03000000002', '$2y$12$qJm7GaKM2DY7An3gwVcYcusm3n16fpAOM8UU/ZdQHZraOUtXG1dsa', 'active'),
(3, 3, 'Demo Storage Operator', 'storage.demo@quettaagrilink.test', '03000000003', '$2y$12$qJm7GaKM2DY7An3gwVcYcusm3n16fpAOM8UU/ZdQHZraOUtXG1dsa', 'active'),
(4, 4, 'Demo Transport Operator', 'transport.demo@quettaagrilink.test', '03000000004', '$2y$12$qJm7GaKM2DY7An3gwVcYcusm3n16fpAOM8UU/ZdQHZraOUtXG1dsa', 'active'),
(5, 5, 'Demo Administrator', 'admin.demo@quettaagrilink.test', '03000000005', '$2y$12$qJm7GaKM2DY7An3gwVcYcusm3n16fpAOM8UU/ZdQHZraOUtXG1dsa', 'active');

INSERT INTO produce_categories (id, name, slug, description) VALUES
(1, 'Apples', 'apples', 'Apples grown in Balochistan.'),
(2, 'Grapes', 'grapes', 'Table and processing grapes.'),
(3, 'Apricots', 'apricots', 'Fresh apricots.'),
(4, 'Cherries', 'cherries', 'Fresh cherries.'),
(5, 'Almonds', 'almonds', 'Shelled and unshelled almonds.'),
(6, 'Peaches', 'peaches', 'Fresh peaches.'),
(7, 'Plums', 'plums', 'Fresh plums.'),
(8, 'Pomegranates', 'pomegranates', 'Fresh pomegranates.'),
(9, 'Vegetables', 'vegetables', 'Seasonal vegetables.');

-- Fictional demo profiles and operational records for local evaluation only.
INSERT INTO farmer_profiles (id,user_id,farm_name,farm_location_id,farm_size_acres,bio) VALUES
(1,1,'Quetta Valley Demo Orchard',2,18.50,'Fictional demonstration orchard record for local product evaluation.');
INSERT INTO buyer_profiles (id,user_id,business_name,business_type,location_id,bio) VALUES
(1,2,'Balochistan Fresh Trade Demo','Wholesale buyer',1,'Fictional demonstration buyer record.');
INSERT INTO storage_providers (id,user_id,business_name,registration_number,location_id,bio) VALUES
(1,3,'Quetta Cold Chain Demo','DEMO-ST-001',1,'Fictional demonstration cold-storage operator.');
INSERT INTO transport_providers (id,user_id,company_name,registration_number,location_id,bio) VALUES
(1,4,'Balochistan Produce Logistics Demo','DEMO-TR-001',1,'Fictional demonstration transport operator.');

INSERT INTO produce_listings (id,farmer_id,category_id,location_id,title,description,grade,quantity_available,unit,expected_price,harvest_date,available_from,minimum_order_quantity,status,published_at) VALUES
(1,1,1,2,'Pishin Grade A Apples','Demo listing: packed orchard apples available for wholesale collection.','A',2500,'kg',180,'2026-08-20','2026-08-22',250,'active','2026-08-22 09:00:00'),
(2,1,2,1,'Quetta Table Grapes','Demo listing: table grapes in ventilated crates.','A',1200,'kg',220,'2026-08-24','2026-08-25',100,'active','2026-08-25 10:30:00'),
(3,1,3,4,'Ziarat Fresh Apricots','Demo listing retained as a completed seasonal supply record.','B',0,'kg',145,'2026-06-12','2026-06-13',150,'sold_out','2026-06-13 08:00:00');

INSERT INTO storage_facilities (id,provider_id,location_id,name,description,storage_type,total_capacity_kg,available_capacity_kg,price_per_kg_day,status) VALUES
(1,1,1,'Quetta Produce Cold Store — Demo','Demo multi-crop cold room near the Quetta trade corridor.','cold_storage',50000,42000,0.85,'active'),
(2,1,2,'Pishin Orchard Store — Demo','Demo seasonal controlled-atmosphere capacity.','controlled_atmosphere',30000,30000,1.10,'active');
INSERT INTO facility_supported_products (facility_id,category_id) VALUES (1,1),(1,2),(1,3),(1,4),(2,1),(2,2);

INSERT INTO vehicles (id,provider_id,vehicle_type,registration_number,capacity_kg,is_refrigerated,price_per_km,status) VALUES
(1,1,'Medium reefer truck','DEMO-QTA-001',8000,1,185,'busy'),
(2,1,'Open-body pickup','DEMO-QTA-002',2500,0,95,'available');
INSERT INTO transport_service_areas (provider_id,location_id) VALUES (1,1),(1,2),(1,3),(1,4),(1,5);

INSERT INTO offers (id,listing_id,buyer_id,farmer_id,quantity,offered_price,total_amount,message,status,responded_at,created_at,updated_at) VALUES
(1,3,2,1,1000,140,140000,'Demo wholesale offer for the completed apricot trade.','accepted','2026-06-13 12:00:00','2026-06-13 10:00:00','2026-06-13 12:00:00'),
(2,1,2,1,500,170,85000,'Demo pending apple offer.','pending',NULL,'2026-08-27 11:00:00','2026-08-27 11:00:00');
INSERT INTO offer_events (offer_id,actor_user_id,event_type,quantity,price,note,created_at) VALUES
(1,2,'created',1000,140,'Demo buyer offer.','2026-06-13 10:00:00'),(1,1,'accepted',1000,140,'Demo accepted terms.','2026-06-13 12:00:00'),(2,2,'created',500,170,'Demo offer awaiting farmer response.','2026-08-27 11:00:00');
INSERT INTO orders (id,reference_code,offer_id,buyer_id,farmer_id,status,subtotal,total_amount,confirmed_at,completed_at,created_at,updated_at) VALUES
(1,'QAL-DEMO-0001',1,2,1,'completed',140000,140000,'2026-06-13 12:00:00','2026-06-18 16:00:00','2026-06-13 12:00:00','2026-06-18 16:00:00');
INSERT INTO order_items (order_id,listing_id,category_id,produce_name,grade,quantity,unit,unit_price,line_total) VALUES
(1,3,3,'Ziarat Fresh Apricots','B',1000,'kg',140,140000);
INSERT INTO order_status_history (order_id,status,changed_by_user_id,notes,created_at) VALUES
(1,'confirmed',1,'Demo order created from accepted offer.','2026-06-13 12:00:00'),(1,'ready_for_pickup',1,'Demo produce prepared for collection.','2026-06-15 09:00:00'),(1,'picked_up',1,'Demo pickup recorded.','2026-06-16 08:00:00'),(1,'in_transit',1,'Demo load in transit.','2026-06-16 09:00:00'),(1,'delivered',2,'Demo buyer recorded delivery.','2026-06-18 15:30:00'),(1,'completed',2,'Demo trade completed.','2026-06-18 16:00:00');

INSERT INTO storage_bookings (id,reference_code,farmer_id,facility_id,listing_id,category_id,quantity_kg,start_date,end_date,price_per_kg_day,estimated_cost,status) VALUES
(1,'QAS-DEMO-0001',1,1,1,1,8000,'2026-09-01','2026-09-10',0.85,68000,'approved');
INSERT INTO storage_booking_status_history (booking_id,status,changed_by_user_id,note,created_at) VALUES
(1,'requested',1,'Demo farmer request.','2026-08-26 09:00:00'),(1,'approved',3,'Demo capacity approved.','2026-08-26 12:00:00');
INSERT INTO transport_requests (id,reference_code,farmer_id,provider_id,vehicle_id,listing_id,pickup_location_id,delivery_location_id,produce_description,quantity_kg,required_vehicle_type,requires_refrigeration,pickup_date,estimated_price,driver_name,driver_phone,status,provider_note) VALUES
(1,'QAT-DEMO-0001',1,1,1,1,2,1,'Grade A apples',2000,'Reefer truck',1,'2026-09-02',32000,'Demo Driver','03000000006','driver_assigned','Demo assignment for local evaluation.');
INSERT INTO transport_status_history (transport_request_id,status,changed_by_user_id,note,created_at) VALUES
(1,'requested',1,'Demo request submitted.','2026-08-27 08:00:00'),(1,'accepted',4,'Demo quote accepted by provider.','2026-08-27 10:00:00'),(1,'driver_assigned',4,'Demo vehicle and driver assigned.','2026-08-28 09:00:00');

INSERT INTO market_prices (category_id,location_id,recorded_by_user_id,price_date,min_price,max_price,average_price,unit,notes,source_name) VALUES
(1,1,5,'2026-08-27',160,195,178,'kg','Fictional demo price range.','DEMO — Quetta reference record'),
(2,1,5,'2026-08-27',190,245,218,'kg','Fictional demo price range.','DEMO — Quetta reference record'),
(3,1,5,'2026-06-12',125,160,143,'kg','Fictional demo price range.','DEMO — Quetta reference record'),
(9,1,5,'2026-08-27',65,110,88,'kg','Fictional demo mixed-vegetable range.','DEMO — Quetta reference record');

CREATE TABLE saved_storage_searches (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL,
    category_id BIGINT UNSIGNED NULL,
    district VARCHAR(100) NULL,
    storage_type VARCHAR(40) NULL,
    min_capacity DECIMAL(14,2) NULL,
    max_price DECIMAL(12,2) NULL,
    sort_key VARCHAR(30) NOT NULL DEFAULT 'capacity_high',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_saved_storage_search_name (user_id, name),
    INDEX idx_saved_storage_search_user (user_id, updated_at),
    CONSTRAINT fk_saved_storage_search_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_saved_storage_search_category FOREIGN KEY (category_id) REFERENCES produce_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Consolidated feature tables: included here so phpMyAdmin needs only this file.
ALTER TABLE users ADD COLUMN onboarding_completed_at DATETIME NULL AFTER last_login_at;

CREATE TABLE saved_marketplace_filters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(80) NOT NULL, category_id BIGINT UNSIGNED NULL, district VARCHAR(100) NULL,
    grade VARCHAR(10) NULL, min_price DECIMAL(12,2) NULL, max_price DECIMAL(12,2) NULL,
    min_quantity DECIMAL(12,2) NULL, sort_key VARCHAR(30) NOT NULL DEFAULT 'recent',
    is_default TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_saved_marketplace_filter_name (user_id,name),
    INDEX idx_saved_marketplace_filter_user (user_id,created_at), INDEX idx_saved_marketplace_filter_default (user_id,is_default),
    CONSTRAINT fk_saved_marketplace_filter_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_saved_marketplace_filter_category FOREIGN KEY (category_id) REFERENCES produce_categories(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE record_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, entity_type VARCHAR(40) NOT NULL, entity_id BIGINT UNSIGNED NOT NULL,
    uploader_user_id BIGINT UNSIGNED NOT NULL, original_name VARCHAR(180) NOT NULL, stored_name VARCHAR(80) NOT NULL UNIQUE,
    relative_path VARCHAR(255) NOT NULL UNIQUE, mime_type VARCHAR(100) NOT NULL, file_size INT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_record_attachment_entity (entity_type,entity_id), INDEX idx_record_attachment_uploader (uploader_user_id),
    CONSTRAINT fk_record_attachment_uploader FOREIGN KEY (uploader_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE account_contact_verifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
    verified_email_at DATETIME NULL, verified_phone_at DATETIME NULL, verification_notes VARCHAR(800) NOT NULL,
    review_reason_code VARCHAR(48) NULL, verified_by_user_id BIGINT UNSIGNED NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_account_contact_verifications_user (user_id), KEY idx_account_contact_verifications_admin (verified_by_user_id),
    CONSTRAINT fk_account_contact_verifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_account_contact_verifications_admin FOREIGN KEY (verified_by_user_id) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE dashboard_activity_presets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL, preset_name VARCHAR(60) NOT NULL,
    activity_from DATE NULL, activity_to DATE NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_dashboard_activity_preset_user_name (user_id,preset_name),
    CONSTRAINT fk_dashboard_activity_presets_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE local_password_recovery_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, user_id BIGINT UNSIGNED NOT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, requested_ip VARCHAR(45) NULL, verification_notes TEXT NULL,
    verified_by_user_id BIGINT UNSIGNED NULL, verified_at DATETIME NULL, issued_by_user_id BIGINT UNSIGNED NULL,
    issued_at DATETIME NULL, selector CHAR(24) NULL UNIQUE, token_hash CHAR(64) NULL, expires_at DATETIME NULL,
    used_at DATETIME NULL, revoked_at DATETIME NULL, revoked_by_user_id BIGINT UNSIGNED NULL,
    INDEX idx_local_recovery_user_requested (user_id,requested_at), INDEX idx_local_recovery_active (expires_at,used_at,revoked_at),
    CONSTRAINT fk_local_recovery_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_local_recovery_verifier FOREIGN KEY (verified_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_local_recovery_issuer FOREIGN KEY (issued_by_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_local_recovery_revoker FOREIGN KEY (revoked_by_user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE user_notification_preferences (
    user_id BIGINT UNSIGNED PRIMARY KEY, marketplace_match_alerts_enabled TINYINT(1) NOT NULL DEFAULT 1,
    browser_chime_enabled TINYINT(1) NOT NULL DEFAULT 0, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_notification_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE operator_account_transitions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, administrator_id BIGINT UNSIGNED NOT NULL,
    created_user_id BIGINT UNSIGNED NULL, archived_user_id BIGINT UNSIGNED NULL,
    action ENUM('operator_created','development_account_archived') NOT NULL, details JSON NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_operator_transition_administrator FOREIGN KEY (administrator_id) REFERENCES users(id),
    CONSTRAINT fk_operator_transition_created_user FOREIGN KEY (created_user_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_operator_transition_archived_user FOREIGN KEY (archived_user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_operator_transition_created (created_at), INDEX idx_operator_transition_action (action)
) ENGINE=InnoDB;
SET FOREIGN_KEY_CHECKS = 1;
