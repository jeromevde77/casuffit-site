-- migrate_admin_users.sql
-- Table des comptes administrateurs avec support 2FA TOTP

CREATE TABLE IF NOT EXISTS admin_users (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    username           VARCHAR(50)  NOT NULL UNIQUE,
    email              VARCHAR(150) NOT NULL,
    password_hash      VARCHAR(255) NOT NULL,
    role               ENUM('superadmin','admin') NOT NULL DEFAULT 'admin',
    -- 2FA TOTP
    totp_secret        VARCHAR(32)  NULL COMMENT 'Secret base32 TOTP',
    totp_enabled       TINYINT(1)   NOT NULL DEFAULT 0,
    totp_backup_codes  TEXT         NULL     COMMENT 'JSON array de hashes bcrypt des codes de secours',
    totp_setup_at      DATETIME     NULL,
    -- Sécurité / rate limiting
    failed_attempts    TINYINT      NOT NULL DEFAULT 0,
    locked_until       DATETIME     NULL,
    -- Traçabilité
    last_login         DATETIME     NULL,
    last_login_ip      VARCHAR(45)  NULL,
    created_at         DATETIME     NOT NULL DEFAULT NOW(),
    is_active          TINYINT(1)   NOT NULL DEFAULT 1,
    INDEX idx_username (username),
    INDEX idx_active   (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
