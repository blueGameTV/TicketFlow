CREATE TABLE IF NOT EXISTS password_recovery_requests (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 user_id INT UNSIGNED NOT NULL,
 method ENUM('support_code','email') NOT NULL DEFAULT 'support_code',
 status ENUM('pending','approved','rejected','completed','expired') NOT NULL DEFAULT 'pending',
 request_ip VARCHAR(45) NULL,
 requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reviewed_by INT UNSIGNED NULL,
 reviewed_at DATETIME NULL,
 code_hash VARCHAR(255) NULL,
 code_expires_at DATETIME NULL,
 code_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
 code_used_at DATETIME NULL,
 completed_at DATETIME NULL,
 CONSTRAINT fk_password_recovery_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
 CONSTRAINT fk_password_recovery_reviewer FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL,
 INDEX idx_password_recovery_status (method,status,requested_at),
 INDEX idx_password_recovery_user (user_id,status,requested_at)
) ENGINE=InnoDB;
