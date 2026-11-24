-- Formulario Dorado schema for MySQL 8.4 LTS.
-- Personal data columns hold XChaCha20-Poly1305 envelopes, never plaintext.

CREATE TABLE IF NOT EXISTS contact_submissions (
    id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    name_ciphertext VARCHAR(1024) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    phone_ciphertext VARCHAR(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    email_ciphertext VARCHAR(1024) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    message_ciphertext MEDIUMTEXT CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    created_at DATETIME(6) NOT NULL,
    notified_at DATETIME(6) NULL,
    PRIMARY KEY (id),
    KEY idx_contact_submissions_created_at (created_at, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS admin_users (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    username VARCHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    password_hash VARCHAR(255) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    totp_secret_ciphertext VARCHAR(512) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    last_totp_step BIGINT UNSIGNED NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    updated_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6) ON UPDATE CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    UNIQUE KEY uq_admin_users_username (username),
    CONSTRAINT chk_admin_users_username CHECK (REGEXP_LIKE(username, '^[a-z0-9._-]{3,64}$', 'c'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS admin_audit_log (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    admin_id BIGINT UNSIGNED NOT NULL,
    action VARCHAR(32) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    submission_id CHAR(32) CHARACTER SET ascii COLLATE ascii_bin NULL,
    created_at DATETIME(6) NOT NULL DEFAULT CURRENT_TIMESTAMP(6),
    PRIMARY KEY (id),
    KEY idx_admin_audit_log_admin_created (admin_id, created_at),
    CONSTRAINT fk_admin_audit_log_admin FOREIGN KEY (admin_id) REFERENCES admin_users (id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

CREATE TABLE IF NOT EXISTS rate_limit_buckets (
    bucket_hash BINARY(32) NOT NULL,
    attempts INT UNSIGNED NOT NULL,
    window_started_at BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY (bucket_hash),
    KEY idx_rate_limit_buckets_window (window_started_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;
