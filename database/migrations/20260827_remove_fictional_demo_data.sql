-- Removes only the known fictional baseline data from existing local installs.
-- Retains demo user credentials, role definitions, district/location references, and produce categories.
-- Apply after all earlier migrations. Do not use this one-time cleanup on a database where demo accounts were used for records you intend to keep.

START TRANSACTION;

CREATE TEMPORARY TABLE qli_demo_cleanup_users (id BIGINT UNSIGNED PRIMARY KEY);
INSERT INTO qli_demo_cleanup_users (id)
SELECT id FROM users
WHERE (email, full_name) IN (
    ('farmer.demo@quettaagrilink.test', 'Demo Farmer'),
    ('buyer.demo@quettaagrilink.test', 'Demo Buyer'),
    ('storage.demo@quettaagrilink.test', 'Demo Storage Operator'),
    ('transport.demo@quettaagrilink.test', 'Demo Transport Operator'),
    ('admin.demo@quettaagrilink.test', 'Demo Administrator')
);

CREATE TEMPORARY TABLE qli_demo_cleanup_listings (id BIGINT UNSIGNED PRIMARY KEY);
INSERT INTO qli_demo_cleanup_listings (id)
SELECT id FROM produce_listings WHERE farmer_id IN (SELECT id FROM qli_demo_cleanup_users);

CREATE TEMPORARY TABLE qli_demo_cleanup_offers (id BIGINT UNSIGNED PRIMARY KEY);
INSERT INTO qli_demo_cleanup_offers (id)
SELECT id FROM offers
WHERE listing_id IN (SELECT id FROM qli_demo_cleanup_listings)
   OR buyer_id IN (SELECT id FROM qli_demo_cleanup_users)
   OR farmer_id IN (SELECT id FROM qli_demo_cleanup_users);

CREATE TEMPORARY TABLE qli_demo_cleanup_orders (id BIGINT UNSIGNED PRIMARY KEY);
INSERT INTO qli_demo_cleanup_orders (id)
SELECT id FROM orders
WHERE offer_id IN (SELECT id FROM qli_demo_cleanup_offers)
   OR buyer_id IN (SELECT id FROM qli_demo_cleanup_users)
   OR farmer_id IN (SELECT id FROM qli_demo_cleanup_users);

CREATE TEMPORARY TABLE qli_demo_cleanup_facilities (id BIGINT UNSIGNED PRIMARY KEY);
INSERT INTO qli_demo_cleanup_facilities (id)
SELECT sf.id FROM storage_facilities sf JOIN storage_providers sp ON sp.id = sf.provider_id
WHERE sp.user_id IN (SELECT id FROM qli_demo_cleanup_users);

CREATE TEMPORARY TABLE qli_demo_cleanup_transport (id BIGINT UNSIGNED PRIMARY KEY);
INSERT INTO qli_demo_cleanup_transport (id)
SELECT tr.id FROM transport_requests tr LEFT JOIN transport_providers tp ON tp.id = tr.provider_id
WHERE tr.farmer_id IN (SELECT id FROM qli_demo_cleanup_users)
   OR tr.listing_id IN (SELECT id FROM qli_demo_cleanup_listings)
   OR tr.order_id IN (SELECT id FROM qli_demo_cleanup_orders)
   OR tp.user_id IN (SELECT id FROM qli_demo_cleanup_users);

DELETE FROM reviews WHERE order_id IN (SELECT id FROM qli_demo_cleanup_orders)
   OR reviewer_id IN (SELECT id FROM qli_demo_cleanup_users)
   OR reviewed_user_id IN (SELECT id FROM qli_demo_cleanup_users);
DELETE FROM payments WHERE order_id IN (SELECT id FROM qli_demo_cleanup_orders)
   OR payer_id IN (SELECT id FROM qli_demo_cleanup_users)
   OR payee_id IN (SELECT id FROM qli_demo_cleanup_users);
DELETE FROM order_status_history WHERE order_id IN (SELECT id FROM qli_demo_cleanup_orders);
DELETE FROM order_items WHERE order_id IN (SELECT id FROM qli_demo_cleanup_orders)
   OR listing_id IN (SELECT id FROM qli_demo_cleanup_listings);
DELETE FROM transport_status_history WHERE transport_request_id IN (SELECT id FROM qli_demo_cleanup_transport);
DELETE FROM transport_requests WHERE id IN (SELECT id FROM qli_demo_cleanup_transport);
DELETE FROM storage_booking_status_history WHERE booking_id IN (
    SELECT id FROM storage_bookings WHERE farmer_id IN (SELECT id FROM qli_demo_cleanup_users)
       OR facility_id IN (SELECT id FROM qli_demo_cleanup_facilities)
       OR listing_id IN (SELECT id FROM qli_demo_cleanup_listings)
);
DELETE FROM storage_bookings WHERE farmer_id IN (SELECT id FROM qli_demo_cleanup_users)
   OR facility_id IN (SELECT id FROM qli_demo_cleanup_facilities)
   OR listing_id IN (SELECT id FROM qli_demo_cleanup_listings);
DELETE FROM messages WHERE sender_id IN (SELECT id FROM qli_demo_cleanup_users)
   OR recipient_id IN (SELECT id FROM qli_demo_cleanup_users)
   OR listing_id IN (SELECT id FROM qli_demo_cleanup_listings)
   OR order_id IN (SELECT id FROM qli_demo_cleanup_orders);
DELETE FROM offer_events WHERE offer_id IN (SELECT id FROM qli_demo_cleanup_offers)
   OR actor_user_id IN (SELECT id FROM qli_demo_cleanup_users);
DELETE FROM orders WHERE id IN (SELECT id FROM qli_demo_cleanup_orders);
DELETE FROM offers WHERE id IN (SELECT id FROM qli_demo_cleanup_offers);
DELETE FROM produce_images WHERE listing_id IN (SELECT id FROM qli_demo_cleanup_listings);
DELETE FROM favorites WHERE listing_id IN (SELECT id FROM qli_demo_cleanup_listings)
   OR buyer_id IN (SELECT id FROM qli_demo_cleanup_users);
DELETE FROM produce_listings WHERE id IN (SELECT id FROM qli_demo_cleanup_listings);
DELETE FROM record_attachments WHERE uploader_user_id IN (SELECT id FROM qli_demo_cleanup_users);
DELETE FROM notifications WHERE user_id IN (SELECT id FROM qli_demo_cleanup_users);
DELETE FROM announcements WHERE author_id IN (SELECT id FROM qli_demo_cleanup_users);
DELETE FROM audit_logs WHERE actor_user_id IN (SELECT id FROM qli_demo_cleanup_users);
DELETE FROM market_prices WHERE recorded_by_user_id IN (SELECT id FROM qli_demo_cleanup_users);
DELETE FROM saved_marketplace_filters WHERE user_id IN (SELECT id FROM qli_demo_cleanup_users);
DELETE FROM dashboard_activity_presets WHERE user_id IN (SELECT id FROM qli_demo_cleanup_users);
DELETE FROM user_notification_preferences WHERE user_id IN (SELECT id FROM qli_demo_cleanup_users);
DELETE FROM account_contact_verifications WHERE user_id IN (SELECT id FROM qli_demo_cleanup_users)
   OR verified_by_user_id IN (SELECT id FROM qli_demo_cleanup_users);
DELETE FROM local_password_recovery_requests WHERE user_id IN (SELECT id FROM qli_demo_cleanup_users)
   OR issued_by_user_id IN (SELECT id FROM qli_demo_cleanup_users)
   OR revoked_by_user_id IN (SELECT id FROM qli_demo_cleanup_users)
   OR verified_by_user_id IN (SELECT id FROM qli_demo_cleanup_users);
DELETE FROM password_reset_tokens WHERE user_id IN (SELECT id FROM qli_demo_cleanup_users);
DELETE FROM facility_supported_products WHERE facility_id IN (SELECT id FROM qli_demo_cleanup_facilities);
DELETE FROM vehicles WHERE provider_id IN (SELECT id FROM transport_providers WHERE user_id IN (SELECT id FROM qli_demo_cleanup_users));
DELETE FROM transport_service_areas WHERE provider_id IN (SELECT id FROM transport_providers WHERE user_id IN (SELECT id FROM qli_demo_cleanup_users));
DELETE FROM storage_facilities WHERE id IN (SELECT id FROM qli_demo_cleanup_facilities);
DELETE FROM storage_providers WHERE user_id IN (SELECT id FROM qli_demo_cleanup_users);
DELETE FROM transport_providers WHERE user_id IN (SELECT id FROM qli_demo_cleanup_users);
DELETE FROM farmer_profiles WHERE user_id IN (SELECT id FROM qli_demo_cleanup_users);
DELETE FROM buyer_profiles WHERE user_id IN (SELECT id FROM qli_demo_cleanup_users);
UPDATE users SET last_login_at = NULL, onboarding_completed_at = NULL WHERE id IN (SELECT id FROM qli_demo_cleanup_users);

DROP TEMPORARY TABLE qli_demo_cleanup_transport;
DROP TEMPORARY TABLE qli_demo_cleanup_facilities;
DROP TEMPORARY TABLE qli_demo_cleanup_orders;
DROP TEMPORARY TABLE qli_demo_cleanup_offers;
DROP TEMPORARY TABLE qli_demo_cleanup_listings;
DROP TEMPORARY TABLE qli_demo_cleanup_users;

COMMIT;
