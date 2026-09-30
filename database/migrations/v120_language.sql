-- TicketFlow v1.2.0 — préférences de langue FR/EN

ALTER TABLE user_preferences
    ADD COLUMN language ENUM('fr','en') NOT NULL DEFAULT 'fr' AFTER sidebar_mode;

INSERT INTO app_settings (setting_key, setting_value)
VALUES ('default_language','fr')
ON DUPLICATE KEY UPDATE setting_value = setting_value;
