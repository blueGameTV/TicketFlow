-- TicketFlow v1.2.0 — validation Manager à deux niveaux
-- Validation Manager à deux niveaux : N+1 puis Manager sélectionné.

ALTER TABLE manager_approvals
    ADD COLUMN stage ENUM('n1','target') NOT NULL DEFAULT 'n1' AFTER requested_by,
    ADD COLUMN target_manager_id INT UNSIGNED NULL AFTER stage,
    ADD COLUMN parent_approval_id BIGINT UNSIGNED NULL AFTER target_manager_id;
