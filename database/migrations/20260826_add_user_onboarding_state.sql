-- Run once after importing database/quetta_agrilink.sql on the local XAMPP MySQL instance.
-- Tracks voluntary completion of the non-blocking, role-specific first-use guide.
ALTER TABLE users ADD COLUMN onboarding_completed_at DATETIME NULL AFTER last_login_at;
