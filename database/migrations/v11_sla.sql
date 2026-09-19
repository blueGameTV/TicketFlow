USE ticketflow;

CREATE TABLE IF NOT EXISTS sla_policies (
    priority_id TINYINT UNSIGNED PRIMARY KEY,
    response_minutes INT UNSIGNED NOT NULL,
    resolution_minutes INT UNSIGNED NOT NULL,
    active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_sla_policy_priority FOREIGN KEY (priority_id) REFERENCES priorities(id) ON DELETE CASCADE
) ENGINE=InnoDB;

INSERT INTO sla_policies (priority_id, response_minutes, resolution_minutes, active)
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
       END,
       1
FROM priorities
ON DUPLICATE KEY UPDATE
    response_minutes = VALUES(response_minutes),
    resolution_minutes = VALUES(resolution_minutes),
    active = VALUES(active);

ALTER TABLE tickets
    ADD COLUMN IF NOT EXISTS assigned_at DATETIME NULL AFTER description,
    ADD COLUMN IF NOT EXISTS sla_response_due_at DATETIME NULL AFTER assigned_at,
    ADD COLUMN IF NOT EXISTS sla_resolution_due_at DATETIME NULL AFTER sla_response_due_at,
    ADD COLUMN IF NOT EXISTS sla_response_alerted_at DATETIME NULL AFTER sla_resolution_due_at,
    ADD COLUMN IF NOT EXISTS sla_resolution_alerted_at DATETIME NULL AFTER sla_response_alerted_at;

UPDATE tickets t
INNER JOIN sla_policies sp ON sp.priority_id = t.priority_id
SET t.sla_response_due_at = COALESCE(t.sla_response_due_at, DATE_ADD(t.created_at, INTERVAL sp.response_minutes MINUTE)),
    t.sla_resolution_due_at = COALESCE(t.sla_resolution_due_at, DATE_ADD(t.created_at, INTERVAL sp.resolution_minutes MINUTE)),
    t.assigned_at = CASE
        WHEN t.assigned_it_id IS NOT NULL AND t.assigned_at IS NULL THEN t.created_at
        ELSE t.assigned_at
    END
WHERE sp.active = 1;
