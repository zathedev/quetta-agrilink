-- Quetta AgriLink: current structured reason code for administrator-recorded local contact reviews.
-- Codes are validated by the PHP catalog; the free-text note records only non-sensitive local context.
ALTER TABLE account_contact_verifications
    ADD COLUMN IF NOT EXISTS review_reason_code VARCHAR(48) NULL AFTER verification_notes;
