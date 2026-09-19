USE ticketflow;

ALTER TABLE users
    ADD COLUMN username VARCHAR(50) NULL AFTER lastname,
    ADD COLUMN profile_photo VARCHAR(255) NULL AFTER email;

UPDATE users
SET username = CONCAT('user', id)
WHERE username IS NULL OR username = '';

ALTER TABLE users
    MODIFY username VARCHAR(50) NOT NULL,
    ADD UNIQUE KEY uniq_users_username (username);

CREATE TABLE IF NOT EXISTS user_preferences (
    user_id INT UNSIGNED PRIMARY KEY,
    theme ENUM('light','dark','system') NOT NULL DEFAULT 'light',
    density ENUM('comfortable','compact') NOT NULL DEFAULT 'comfortable',
    sidebar_mode ENUM('expanded','compact') NOT NULL DEFAULT 'expanded',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_password_reset_user (user_id, expires_at, used_at)
) ENGINE=InnoDB;
