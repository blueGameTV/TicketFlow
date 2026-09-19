<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class AutomationService
{
    public function __construct(
        private PDO $pdo,
        private AppSettingService $settings,
        private NotificationService $notifications,
        private MailQueueService $mailQueue
    ) {
    }

    public function run(): array
    {
        return [
            'manager_reminders' => $this->managerReminders(),
            'resolution_reminders' => $this->resolutionReminders(),
            'auto_closed' => $this->autoCloseTickets(),
            'digests' => $this->dailyDigests(),
        ];
    }

    public function managerReminders(): int
    {
        $hours = max(1, min(720, $this->settings->int('manager_reminder_hours', 24)));
        $stmt = $this->pdo->query(
            "SELECT ma.id, ma.ticket_id, ma.manager_id, t.ticket_number, t.title
             FROM manager_approvals ma
             INNER JOIN tickets t ON t.id = ma.ticket_id
             WHERE ma.status='pending'
               AND ma.requested_at <= DATE_SUB(NOW(), INTERVAL {$hours} HOUR)
               AND t.deleted_at IS NULL"
        );
        $count = 0;
        foreach ($stmt->fetchAll() as $row) {
            $eventKey = 'manager_reminder:' . $row['id'];
            if (!$this->markEvent($eventKey, 'manager_reminder', (string) $row['id'])) {
                continue;
            }
            $this->notifications->notify(
                (int) $row['manager_id'],
                'manager_reminder',
                'Rappel : validation en attente',
                $row['ticket_number'] . ' attend toujours votre décision.',
                (int) $row['ticket_id'],
                'ticket.php?number=' . rawurlencode((string) $row['ticket_number'])
            );
            $count++;
        }
        return $count;
    }

    public function resolutionReminders(): int
    {
        $hours = max(1, min(720, $this->settings->int('resolution_reminder_hours', 24)));
        $stmt = $this->pdo->query(
            "SELECT t.id, t.ticket_number, t.title, t.requester_id
             FROM tickets t
             INNER JOIN ticket_statuses ts ON ts.id=t.status_id
             WHERE ts.code='waiting_confirmation'
               AND t.updated_at <= DATE_SUB(NOW(), INTERVAL {$hours} HOUR)
               AND t.deleted_at IS NULL"
        );
        $count = 0;
        foreach ($stmt->fetchAll() as $row) {
            $eventKey = 'resolution_reminder:' . $row['id'];
            if (!$this->markEvent($eventKey, 'resolution_reminder', (string) $row['id'])) {
                continue;
            }
            $this->notifications->notify(
                (int) $row['requester_id'],
                'resolution_reminder',
                'Votre confirmation est toujours attendue',
                'Merci de confirmer si ' . $row['ticket_number'] . ' est bien résolu.',
                (int) $row['id'],
                'ticket.php?number=' . rawurlencode((string) $row['ticket_number'])
            );
            $count++;
        }
        return $count;
    }

    public function autoCloseTickets(): int
    {
        if (!$this->settings->bool('auto_close_enabled', false)) {
            return 0;
        }
        $hours = max(24, min(2160, $this->settings->int('auto_close_hours', 72)));
        $closedIdStmt = $this->pdo->query("SELECT id FROM ticket_statuses WHERE code='closed' LIMIT 1");
        $closedId = (int) ($closedIdStmt->fetchColumn() ?: 0);
        if ($closedId <= 0) {
            return 0;
        }

        $stmt = $this->pdo->query(
            "SELECT t.id, t.ticket_number, t.requester_id, t.assigned_it_id
             FROM tickets t
             INNER JOIN ticket_statuses ts ON ts.id=t.status_id
             WHERE ts.code='waiting_confirmation'
               AND t.updated_at <= DATE_SUB(NOW(), INTERVAL {$hours} HOUR)
               AND t.deleted_at IS NULL"
        );
        $count = 0;
        foreach ($stmt->fetchAll() as $row) {
            $eventKey = 'auto_close:' . $row['id'];
            if (!$this->markEvent($eventKey, 'auto_close', (string) $row['id'])) {
                continue;
            }
            $this->pdo->beginTransaction();
            try {
                $update = $this->pdo->prepare('UPDATE tickets SET status_id=:status_id, closed_at=NOW() WHERE id=:id');
                $update->execute(['status_id' => $closedId, 'id' => $row['id']]);
                $history = $this->pdo->prepare("INSERT INTO ticket_history(ticket_id,user_id,action,old_value,new_value) VALUES(:ticket_id,NULL,'Fermeture automatique','Attente validation utilisateur','Fermé')");
                $history->execute(['ticket_id' => $row['id']]);
                $this->pdo->commit();
            } catch (Throwable $e) {
                if ($this->pdo->inTransaction()) {
                    $this->pdo->rollBack();
                }
                throw $e;
            }
            $recipients = [(int) $row['requester_id']];
            if (!empty($row['assigned_it_id'])) {
                $recipients[] = (int) $row['assigned_it_id'];
            }
            $this->notifications->notifyMany(
                $recipients,
                'auto_close',
                'Ticket fermé automatiquement',
                $row['ticket_number'] . ' a été fermé après absence de confirmation.',
                (int) $row['id'],
                'ticket.php?number=' . rawurlencode((string) $row['ticket_number'])
            );
            $count++;
        }
        return $count;
    }

    public function dailyDigests(): int
    {
        if (!$this->settings->bool('daily_digest_enabled', false)) {
            return 0;
        }
        $hour = max(0, min(23, $this->settings->int('daily_digest_hour', 8)));
        if ((int) date('G') < $hour) {
            return 0;
        }

        $users = $this->pdo->query(
            "SELECT u.id, u.firstname, u.lastname, r.name role_name
             FROM users u INNER JOIN roles r ON r.id=u.role_id
             WHERE u.active=1 AND r.name IN ('Administrateur','IT')"
        )->fetchAll();
        $count = 0;
        foreach ($users as $user) {
            $eventKey = 'daily_digest:' . date('Y-m-d') . ':' . $user['id'];
            if (!$this->markEvent($eventKey, 'daily_digest', (string) $user['id'])) {
                continue;
            }
            $stats = $this->digestStats((int) $user['id'], (string) $user['role_name']);
            $html = $this->renderDigestHtml(trim($user['firstname'] . ' ' . $user['lastname']), $stats);
            $this->mailQueue->queueDigest((int) $user['id'], '[TicketFlow] Résumé quotidien du ' . date('d/m/Y'), $html);
            $count++;
        }
        return $count;
    }

    private function digestStats(int $userId, string $role): array
    {
        $openSql = "SELECT COUNT(*) FROM tickets t INNER JOIN ticket_statuses ts ON ts.id=t.status_id WHERE t.deleted_at IS NULL AND ts.code NOT IN ('resolved','closed','cancelled')";
        $params = [];
        if ($role === 'IT') {
            $openSql .= ' AND (t.assigned_it_id=:uid OR t.assigned_it_id IS NULL)';
            $params['uid'] = $userId;
        }
        $stmt = $this->pdo->prepare($openSql);
        $stmt->execute($params);
        $open = (int) $stmt->fetchColumn();
        $unassigned = (int) $this->pdo->query("SELECT COUNT(*) FROM tickets t INNER JOIN ticket_statuses ts ON ts.id=t.status_id WHERE t.deleted_at IS NULL AND t.assigned_it_id IS NULL AND ts.code NOT IN ('resolved','closed','cancelled')")->fetchColumn();
        $critical = (int) $this->pdo->query("SELECT COUNT(*) FROM tickets t INNER JOIN ticket_statuses ts ON ts.id=t.status_id INNER JOIN priorities p ON p.id=t.priority_id WHERE t.deleted_at IS NULL AND p.level=4 AND ts.code NOT IN ('resolved','closed','cancelled')")->fetchColumn();
        $validations = (int) $this->pdo->query("SELECT COUNT(*) FROM manager_approvals WHERE status='pending'")->fetchColumn();
        return compact('open', 'unassigned', 'critical', 'validations');
    }

    private function renderDigestHtml(string $name, array $stats): string
    {
        return '<!doctype html><html><body style="font-family:Arial,sans-serif;background:#f3f6fb;padding:24px">'
            . '<div style="max-width:620px;margin:auto;background:#fff;border-radius:14px;padding:28px;border:1px solid #e0e6ef">'
            . '<h2 style="margin-top:0">Bonjour ' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . ',</h2>'
            . '<p>Voici votre résumé TicketFlow du ' . date('d/m/Y') . '.</p>'
            . '<ul style="line-height:1.9">'
            . '<li><strong>' . $stats['open'] . '</strong> tickets ouverts</li>'
            . '<li><strong>' . $stats['unassigned'] . '</strong> tickets non attribués</li>'
            . '<li><strong>' . $stats['critical'] . '</strong> tickets critiques ouverts</li>'
            . '<li><strong>' . $stats['validations'] . '</strong> validations Manager en attente</li>'
            . '</ul></div></body></html>';
    }

    private function markEvent(string $key, string $type, ?string $entityId): bool
    {
        try {
            $stmt = $this->pdo->prepare('INSERT INTO automation_events(event_key,event_type,entity_id) VALUES(:event_key,:event_type,:entity_id)');
            $stmt->execute(['event_key' => $key, 'event_type' => $type, 'entity_id' => $entityId]);
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
