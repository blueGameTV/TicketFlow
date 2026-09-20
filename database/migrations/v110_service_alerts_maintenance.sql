-- TicketFlow v1.1.0 — alertes de service, maintenance et journal des nouveautés

CREATE TABLE IF NOT EXISTS service_alerts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_by INT UNSIGNED NOT NULL,
    resolved_by INT UNSIGNED NULL,
    service_name VARCHAR(120) NOT NULL,
    title VARCHAR(80) NOT NULL,
    message VARCHAR(2000) NOT NULL,
    severity ENUM('info','degraded','major','critical','maintenance') NOT NULL DEFAULT 'info',
    status ENUM('active','monitoring','resolved') NOT NULL DEFAULT 'active',
    starts_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ends_at DATETIME NULL,
    resolved_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_service_alert_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_service_alert_resolved_by FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_service_alert_active (status, starts_at, ends_at),
    INDEX idx_service_alert_severity (severity, status)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS maintenance_windows (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    created_by INT UNSIGNED NOT NULL,
    title VARCHAR(160) NOT NULL,
    message VARCHAR(1200) NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    block_access BOOLEAN NOT NULL DEFAULT FALSE,
    cancelled_at DATETIME NULL,
    cancelled_by INT UNSIGNED NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_maintenance_created_by FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
    CONSTRAINT fk_maintenance_cancelled_by FOREIGN KEY (cancelled_by) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_maintenance_window (starts_at, ends_at, cancelled_at)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS user_release_views (
    user_id INT UNSIGNED NOT NULL,
    version VARCHAR(30) NOT NULL,
    seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id, version),
    CONSTRAINT fk_release_view_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO app_settings (setting_key, setting_value) VALUES
('maintenance_mode_enabled', '0'),
('maintenance_message', 'TicketFlow est actuellement en maintenance.'),
('maintenance_allow_it', '1'),
('maintenance_expected_end', '')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
