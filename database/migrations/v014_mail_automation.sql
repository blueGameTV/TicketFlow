USE ticketflow;

CREATE TABLE IF NOT EXISTS app_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS notification_preferences (
    user_id INT UNSIGNED PRIMARY KEY,
    email_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    email_messages BOOLEAN NOT NULL DEFAULT TRUE,
    email_ticket_updates BOOLEAN NOT NULL DEFAULT TRUE,
    email_validations BOOLEAN NOT NULL DEFAULT TRUE,
    email_resolution BOOLEAN NOT NULL DEFAULT TRUE,
    email_sla BOOLEAN NOT NULL DEFAULT TRUE,
    email_daily_digest BOOLEAN NOT NULL DEFAULT FALSE,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_notification_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS email_queue (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    recipient_email VARCHAR(190) NOT NULL,
    recipient_name VARCHAR(160) NULL,
    notification_type VARCHAR(60) NOT NULL DEFAULT 'info',
    subject VARCHAR(190) NOT NULL,
    body_html MEDIUMTEXT NOT NULL,
    status ENUM('queued','sending','sent','failed') NOT NULL DEFAULT 'queued',
    attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
    available_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    sent_at DATETIME NULL,
    last_error VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_email_queue_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_email_queue_status_available (status, available_at),
    INDEX idx_email_queue_user_created (user_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS automation_events (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_key VARCHAR(190) NOT NULL UNIQUE,
    event_type VARCHAR(60) NOT NULL,
    entity_id VARCHAR(80) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_automation_event_type_created (event_type, created_at)
) ENGINE=InnoDB;

INSERT INTO app_settings (setting_key, setting_value) VALUES
('company_name', 'Mon entreprise'),
('support_email', ''),
('email_notifications_enabled', '0'),
('manager_reminder_hours', '24'),
('resolution_reminder_hours', '24'),
('auto_close_enabled', '0'),
('auto_close_hours', '72'),
('daily_digest_enabled', '0'),
('daily_digest_hour', '8')
ON DUPLICATE KEY UPDATE setting_key = VALUES(setting_key);
