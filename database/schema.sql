CREATE DATABASE IF NOT EXISTS ticketflow
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ticketflow;

CREATE TABLE roles (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL UNIQUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE groups_company (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    manager_id INT UNSIGNED NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    firstname VARCHAR(80) NOT NULL,
    lastname VARCHAR(80) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(190) NOT NULL UNIQUE,
    profile_photo VARCHAR(255) NULL,
    password_hash VARCHAR(255) NOT NULL,
    role_id TINYINT UNSIGNED NOT NULL,
    group_id INT UNSIGNED NULL,
    arrival_date DATE NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    last_login_at DATETIME NULL,
    must_change_password BOOLEAN NOT NULL DEFAULT FALSE,
    password_changed_at DATETIME NULL,
    locked_until DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_users_role FOREIGN KEY (role_id) REFERENCES roles(id),
    CONSTRAINT fk_users_group FOREIGN KEY (group_id) REFERENCES groups_company(id) ON DELETE SET NULL
) ENGINE=InnoDB;

ALTER TABLE groups_company
    ADD CONSTRAINT fk_groups_manager
    FOREIGN KEY (manager_id) REFERENCES users(id) ON DELETE SET NULL;


CREATE TABLE audit_logs (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(60) NULL,
    entity_id VARCHAR(80) NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_audit_created (created_at),
    INDEX idx_audit_user_created (user_id, created_at),
    INDEX idx_audit_action (action)
) ENGINE=InnoDB;

CREATE TABLE login_attempts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(190) NOT NULL,
    user_id INT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    success BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_login_attempt_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_login_attempt_email_created (email, created_at),
    INDEX idx_login_attempt_ip_created (ip_address, created_at)
) ENGINE=InnoDB;

CREATE TABLE user_preferences (
    user_id INT UNSIGNED PRIMARY KEY,
    theme ENUM('light','dark','system') NOT NULL DEFAULT 'light',
    density ENUM('comfortable','compact') NOT NULL DEFAULT 'comfortable',
    sidebar_mode ENUM('expanded','compact') NOT NULL DEFAULT 'expanded',
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_preferences_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE password_reset_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_password_reset_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_password_reset_user (user_id, expires_at, used_at)
) ENGINE=InnoDB;

CREATE TABLE user_sessions (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    session_id VARCHAR(128) NOT NULL UNIQUE,
    user_id INT UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    last_seen_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_user_session_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_session_user (user_id, revoked_at, expires_at)
) ENGINE=InnoDB;

CREATE TABLE applications (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE user_applications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    application_id INT UNSIGNED NOT NULL,
    login VARCHAR(190) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_user_application (user_id, application_id),
    CONSTRAINT fk_user_apps_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_user_apps_application FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ticket_types (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(10) NOT NULL UNIQUE,
    name VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE ticket_categories (
    id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE,
    active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB;

CREATE TABLE priorities (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(30) NOT NULL UNIQUE,
    level TINYINT UNSIGNED NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE sla_policies (
    priority_id TINYINT UNSIGNED PRIMARY KEY,
    response_minutes INT UNSIGNED NOT NULL,
    resolution_minutes INT UNSIGNED NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sla_policy_priority FOREIGN KEY (priority_id) REFERENCES priorities(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ticket_statuses (
    id TINYINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(40) NOT NULL UNIQUE,
    name VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE tickets (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(30) NOT NULL UNIQUE,
    requester_id INT UNSIGNED NOT NULL,
    assigned_it_id INT UNSIGNED NULL,
    type_id TINYINT UNSIGNED NOT NULL,
    category_id SMALLINT UNSIGNED NULL,
    priority_id TINYINT UNSIGNED NOT NULL,
    status_id TINYINT UNSIGNED NOT NULL,
    title VARCHAR(80) NOT NULL,
    description VARCHAR(2000) NOT NULL,
    assigned_at DATETIME NULL,
    sla_response_due_at DATETIME NULL,
    sla_resolution_due_at DATETIME NULL,
    sla_response_alerted_at DATETIME NULL,
    sla_resolution_alerted_at DATETIME NULL,
    resolution TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    closed_at DATETIME NULL,
    deleted_at DATETIME NULL,
    CONSTRAINT fk_tickets_requester FOREIGN KEY (requester_id) REFERENCES users(id),
    CONSTRAINT fk_tickets_assigned_it FOREIGN KEY (assigned_it_id) REFERENCES users(id) ON DELETE SET NULL,
    CONSTRAINT fk_tickets_type FOREIGN KEY (type_id) REFERENCES ticket_types(id),
    CONSTRAINT fk_tickets_category FOREIGN KEY (category_id) REFERENCES ticket_categories(id) ON DELETE SET NULL,
    CONSTRAINT fk_tickets_priority FOREIGN KEY (priority_id) REFERENCES priorities(id),
    CONSTRAINT fk_tickets_status FOREIGN KEY (status_id) REFERENCES ticket_statuses(id),
    INDEX idx_tickets_status (status_id),
    INDEX idx_tickets_requester (requester_id),
    INDEX idx_tickets_assigned_it (assigned_it_id),
    INDEX idx_tickets_created_at (created_at)
) ENGINE=InnoDB;

CREATE TABLE ticket_messages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    author_id INT UNSIGNED NOT NULL,
    message TEXT NOT NULL,
    internal BOOLEAN NOT NULL DEFAULT FALSE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ticket_messages_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_messages_author FOREIGN KEY (author_id) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE ticket_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    user_id INT UNSIGNED NULL,
    action VARCHAR(100) NOT NULL,
    old_value TEXT NULL,
    new_value TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_ticket_history_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_ticket_history_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_history_ticket_created (ticket_id, created_at)
) ENGINE=InnoDB;

CREATE TABLE manager_approvals (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    manager_id INT UNSIGNED NOT NULL,
    requested_by INT UNSIGNED NOT NULL,
    status ENUM('pending','approved','rejected','more_info') NOT NULL DEFAULT 'pending',
    comment TEXT NULL,
    requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    responded_at DATETIME NULL,
    CONSTRAINT fk_approvals_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_approvals_manager FOREIGN KEY (manager_id) REFERENCES users(id),
    CONSTRAINT fk_approvals_requested_by FOREIGN KEY (requested_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE notifications (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    ticket_id BIGINT UNSIGNED NULL,
    type VARCHAR(40) NOT NULL DEFAULT 'info',
    title VARCHAR(150) NOT NULL,
    message VARCHAR(500) NOT NULL DEFAULT '',
    link_url VARCHAR(255) NULL,
    read_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notifications_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    CONSTRAINT fk_notifications_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE SET NULL,
    INDEX idx_notifications_user_read_created (user_id, read_at, created_at),
    INDEX idx_notifications_ticket (ticket_id)
) ENGINE=InnoDB;

CREATE TABLE ticket_attachments (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ticket_id BIGINT UNSIGNED NOT NULL,
    uploaded_by INT UNSIGNED NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    stored_name VARCHAR(255) NOT NULL,
    mime_type VARCHAR(120) NOT NULL,
    size_bytes INT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_attachments_ticket FOREIGN KEY (ticket_id) REFERENCES tickets(id) ON DELETE CASCADE,
    CONSTRAINT fk_attachments_user FOREIGN KEY (uploaded_by) REFERENCES users(id)
) ENGINE=InnoDB;

INSERT INTO roles (name) VALUES
('Administrateur'),
('IT'),
('Manager'),
('Collaborateur');

INSERT INTO ticket_types (code, name) VALUES
('INC', 'Incident'),
('REQ', 'Requête'),
('ACC', 'Demande d\'accès'),
('CHG', 'Changement');

INSERT INTO priorities (name, level) VALUES
('Faible', 1),
('Normale', 2),
('Haute', 3),
('Critique', 4);

INSERT INTO sla_policies (priority_id, response_minutes, resolution_minutes)
SELECT id,
       CASE name
           WHEN 'Faible' THEN 480
           WHEN 'Normale' THEN 240
           WHEN 'Haute' THEN 60
           WHEN 'Critique' THEN 15
           ELSE 480
       END,
       CASE name
           WHEN 'Faible' THEN 7200
           WHEN 'Normale' THEN 2880
           WHEN 'Haute' THEN 480
           WHEN 'Critique' THEN 240
           ELSE 7200
       END
FROM priorities;

INSERT INTO ticket_statuses (code, name) VALUES
('new', 'Nouveau'),
('assigned', 'Attribué'),
('in_progress', 'En cours'),
('waiting_user', 'En attente utilisateur'),
('waiting_manager', 'En attente Manager'),
('resolved', 'Résolu'),
('waiting_confirmation', 'Attente validation utilisateur'),
('closed', 'Fermé'),
('cancelled', 'Annulé');

INSERT INTO ticket_categories (name) VALUES
('Poste de travail'),
('Logiciel'),
('Compte / Accès'),
('Réseau'),
('Impression'),
('Téléphonie'),
('Sécurité'),
('Autre');

-- TicketFlow v0.14.0 — e-mails, préférences et automatisations
CREATE TABLE app_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT NULL,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE notification_preferences (
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

CREATE TABLE email_queue (
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

CREATE TABLE automation_events (
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
('daily_digest_hour', '8');

-- TicketFlow v0.15.1 — vues enregistrées
CREATE TABLE saved_ticket_views (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(60) NOT NULL,
    filters_json TEXT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_saved_ticket_views_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uq_saved_ticket_views_user_name (user_id, name),
    INDEX idx_saved_ticket_views_user (user_id)
) ENGINE=InnoDB;
