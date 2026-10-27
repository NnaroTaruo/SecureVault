-- Existing installations: select your SecureVault database in phpMyAdmin and import once.
-- Safe to repeat. Adds counters only; never changes users, salts, or encrypted vault entries.
CREATE TABLE IF NOT EXISTS login_throttles (
    bucket_hash CHAR(64) CHARACTER SET ascii COLLATE ascii_bin PRIMARY KEY,
    failures INT UNSIGNED NOT NULL DEFAULT 0,
    reset_at BIGINT UNSIGNED NOT NULL,
    INDEX idx_login_throttle_expiry (reset_at)
) ENGINE=InnoDB;
