<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use PDO;
use RuntimeException;

final class SlaService
{
    public function __construct(private PDO $pdo, private NotificationService $notifications)
    {
    }

    public function initializeTicket(int $ticketId, int $priorityId): void
    {
        $this->applyPolicy($ticketId, $priorityId, false);
    }

    public function recalculateTicket(int $ticketId, int $priorityId): void
    {
        $this->applyPolicy($ticketId, $priorityId, true);
    }

    public function markAssigned(int $ticketId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE tickets SET assigned_at = COALESCE(assigned_at, NOW()) WHERE id = :id'
        );
        $stmt->execute(['id' => $ticketId]);
    }

    public function checkBreaches(): array
    {
        $checked = 0;
        $responseBreaches = 0;
        $resolutionBreaches = 0;

        $stmt = $this->pdo->query(
            "SELECT t.id, t.ticket_number, t.title, t.assigned_it_id, t.assigned_at,
                    t.sla_response_due_at, t.sla_resolution_due_at,
                    t.sla_response_alerted_at, t.sla_resolution_alerted_at,
                    t.resolved_at, ts.code AS status_code
             FROM tickets t
             INNER JOIN ticket_statuses ts ON ts.id = t.status_id
             WHERE t.deleted_at IS NULL
               AND ts.code NOT IN ('closed','cancelled')
               AND (t.sla_response_due_at IS NOT NULL OR t.sla_resolution_due_at IS NOT NULL)"
        );

        $adminIds = $this->activeUserIdsByRole('Administrateur');

        foreach ($stmt->fetchAll() as $ticket) {
            $checked++;
            $ticketId = (int) $ticket['id'];
            $link = 'ticket.php?number=' . rawurlencode((string) $ticket['ticket_number']);

            $responseBreached = $ticket['sla_response_due_at'] !== null
                && $ticket['sla_response_alerted_at'] === null
                && (
                    ($ticket['assigned_at'] === null && strtotime((string) $ticket['sla_response_due_at']) < time())
                    || ($ticket['assigned_at'] !== null && strtotime((string) $ticket['assigned_at']) > strtotime((string) $ticket['sla_response_due_at']))
                );

            if ($responseBreached) {
                $this->notifications->notifyMany(
                    $adminIds,
                    'sla_response_breach',
                    'SLA de prise en charge dépassé',
                    $ticket['ticket_number'] . ' n’a pas été pris en charge dans le délai prévu.',
                    $ticketId,
                    $link
                );

                $mark = $this->pdo->prepare('UPDATE tickets SET sla_response_alerted_at = NOW() WHERE id = :id AND sla_response_alerted_at IS NULL');
                $mark->execute(['id' => $ticketId]);
                $responseBreaches++;
            }

            $resolutionBreached = $ticket['sla_resolution_due_at'] !== null
                && $ticket['sla_resolution_alerted_at'] === null
                && (
                    ($ticket['resolved_at'] !== null && strtotime((string) $ticket['resolved_at']) > strtotime((string) $ticket['sla_resolution_due_at']))
                    || (
                        $ticket['resolved_at'] === null
                        && !in_array((string) $ticket['status_code'], ['closed', 'cancelled'], true)
                        && strtotime((string) $ticket['sla_resolution_due_at']) < time()
                    )
                );

            if ($resolutionBreached) {
                $this->notifications->notifyMany(
                    $adminIds,
                    'sla_resolution_breach',
                    'SLA de résolution dépassé',
                    $ticket['ticket_number'] . ' a dépassé son délai de résolution.',
                    $ticketId,
                    $link
                );

                $mark = $this->pdo->prepare('UPDATE tickets SET sla_resolution_alerted_at = NOW() WHERE id = :id AND sla_resolution_alerted_at IS NULL');
                $mark->execute(['id' => $ticketId]);
                $resolutionBreaches++;
            }
        }

        return [
            'checked' => $checked,
            'response_breaches' => $responseBreaches,
            'resolution_breaches' => $resolutionBreaches,
        ];
    }

    public function policyForPriority(int $priorityId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT sp.response_minutes, sp.resolution_minutes, p.name AS priority_name
             FROM sla_policies sp
             INNER JOIN priorities p ON p.id = sp.priority_id
             WHERE sp.priority_id = :priority_id AND sp.active = 1
             LIMIT 1'
        );
        $stmt->execute(['priority_id' => $priorityId]);
        $policy = $stmt->fetch();
        if (!$policy) {
            throw new RuntimeException('Aucune politique SLA active n’est configurée pour cette importance.');
        }
        return $policy;
    }

    private function applyPolicy(int $ticketId, int $priorityId, bool $resetAlerts): void
    {
        $policy = $this->policyForPriority($priorityId);
        $stmt = $this->pdo->prepare('SELECT created_at FROM tickets WHERE id = :id LIMIT 1');
        $stmt->execute(['id' => $ticketId]);
        $createdAt = $stmt->fetchColumn();
        if (!$createdAt) {
            throw new RuntimeException('Ticket introuvable pour le calcul SLA.');
        }

        $created = new DateTimeImmutable((string) $createdAt);
        $responseDue = $created->modify('+' . (int) $policy['response_minutes'] . ' minutes');
        $resolutionDue = $created->modify('+' . (int) $policy['resolution_minutes'] . ' minutes');

        $sql = 'UPDATE tickets
                SET sla_response_due_at = :response_due,
                    sla_resolution_due_at = :resolution_due';
        if ($resetAlerts) {
            $sql .= ', sla_response_alerted_at = NULL, sla_resolution_alerted_at = NULL';
        }
        $sql .= ' WHERE id = :id';

        $update = $this->pdo->prepare($sql);
        $update->execute([
            'response_due' => $responseDue->format('Y-m-d H:i:s'),
            'resolution_due' => $resolutionDue->format('Y-m-d H:i:s'),
            'id' => $ticketId,
        ]);
    }

    private function activeUserIdsByRole(string $roleName): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT u.id FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.active = 1 AND r.name = :role_name'
        );
        $stmt->execute(['role_name' => $roleName]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }
}
