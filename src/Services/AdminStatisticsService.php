<?php

declare(strict_types=1);

namespace App\Services;

use DateTimeImmutable;
use PDO;

final class AdminStatisticsService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function dashboard(int $days = 30): array
    {
        $allowed = [7, 30, 90, 180];
        if (!in_array($days, $allowed, true)) {
            $days = 30;
        }

        $today = new DateTimeImmutable('today');
        $start = $today->modify('-' . ($days - 1) . ' days');
        $endExclusive = $today->modify('+1 day');

        $params = [
            'start' => $start->format('Y-m-d H:i:s'),
            'end' => $endExclusive->format('Y-m-d H:i:s'),
        ];

        return [
            'days' => $days,
            'start' => $start->format('Y-m-d'),
            'end' => $today->format('Y-m-d'),
            'summary' => $this->summary($params),
            'timeline' => $this->timeline($start, $today, $params),
            'statuses' => $this->distribution(
                "SELECT s.name label, COUNT(*) total
                 FROM tickets t
                 INNER JOIN ticket_statuses s ON s.id = t.status_id
                 WHERE t.deleted_at IS NULL AND t.created_at >= :start AND t.created_at < :end
                 GROUP BY s.id, s.name
                 ORDER BY total DESC, s.name",
                $params
            ),
            'priorities' => $this->distribution(
                "SELECT p.name label, COUNT(*) total
                 FROM tickets t
                 INNER JOIN priorities p ON p.id = t.priority_id
                 WHERE t.deleted_at IS NULL AND t.created_at >= :start AND t.created_at < :end
                 GROUP BY p.id, p.name, p.level
                 ORDER BY p.level DESC",
                $params
            ),
            'types' => $this->distribution(
                "SELECT CONCAT(tt.code, ' — ', tt.name) label, COUNT(*) total
                 FROM tickets t
                 INNER JOIN ticket_types tt ON tt.id = t.type_id
                 WHERE t.deleted_at IS NULL AND t.created_at >= :start AND t.created_at < :end
                 GROUP BY tt.id, tt.code, tt.name
                 ORDER BY total DESC, tt.code",
                $params
            ),
            'groups' => $this->distribution(
                "SELECT COALESCE(g.name, 'Sans groupe') label, COUNT(*) total
                 FROM tickets t
                 INNER JOIN users u ON u.id = t.requester_id
                 LEFT JOIN groups_company g ON g.id = u.group_id
                 WHERE t.deleted_at IS NULL AND t.created_at >= :start AND t.created_at < :end
                 GROUP BY g.id, g.name
                 ORDER BY total DESC, label
                 LIMIT 10",
                $params
            ),
        ];
    }

    private function summary(array $params): array
    {
        $created = $this->scalar(
            'SELECT COUNT(*) FROM tickets WHERE deleted_at IS NULL AND created_at >= :start AND created_at < :end',
            $params
        );

        $resolved = $this->scalar(
            'SELECT COUNT(*) FROM tickets WHERE deleted_at IS NULL AND resolved_at IS NOT NULL AND resolved_at >= :start AND resolved_at < :end',
            $params
        );

        $stmt = $this->pdo->prepare(
            'SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, resolved_at))
             FROM tickets
             WHERE deleted_at IS NULL
               AND resolved_at IS NOT NULL
               AND resolved_at >= :start AND resolved_at < :end'
        );
        $stmt->execute($params);
        $avgMinutes = $stmt->fetchColumn();

        $open = (int) $this->pdo->query(
            "SELECT COUNT(*)
             FROM tickets t
             INNER JOIN ticket_statuses s ON s.id = t.status_id
             WHERE t.deleted_at IS NULL AND s.code NOT IN ('resolved','closed','cancelled')"
        )->fetchColumn();

        $criticalOpen = (int) $this->pdo->query(
            "SELECT COUNT(*)
             FROM tickets t
             INNER JOIN ticket_statuses s ON s.id = t.status_id
             INNER JOIN priorities p ON p.id = t.priority_id
             WHERE t.deleted_at IS NULL
               AND s.code NOT IN ('resolved','closed','cancelled')
               AND p.level = 4"
        )->fetchColumn();

        $unassigned = (int) $this->pdo->query(
            "SELECT COUNT(*)
             FROM tickets t
             INNER JOIN ticket_statuses s ON s.id = t.status_id
             WHERE t.deleted_at IS NULL
               AND t.assigned_it_id IS NULL
               AND s.code NOT IN ('resolved','closed','cancelled')"
        )->fetchColumn();

        $pendingManager = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM manager_approvals WHERE status = 'pending'"
        )->fetchColumn();

        $slaResponseBreached = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM tickets t
             INNER JOIN ticket_statuses s ON s.id = t.status_id
             WHERE t.deleted_at IS NULL
               AND s.code NOT IN ('resolved','closed','cancelled')
               AND t.sla_response_due_at IS NOT NULL
               AND t.assigned_at IS NULL
               AND t.sla_response_due_at < NOW()"
        )->fetchColumn();

        $slaResolutionBreached = (int) $this->pdo->query(
            "SELECT COUNT(*) FROM tickets t
             INNER JOIN ticket_statuses s ON s.id = t.status_id
             WHERE t.deleted_at IS NULL
               AND s.code NOT IN ('resolved','closed','cancelled')
               AND t.sla_resolution_due_at IS NOT NULL
               AND t.sla_resolution_due_at < NOW()"
        )->fetchColumn();

        $activeUsers = (int) $this->pdo->query('SELECT COUNT(*) FROM users WHERE active = 1')->fetchColumn();
        $activeGroups = (int) $this->pdo->query('SELECT COUNT(*) FROM groups_company WHERE active = 1')->fetchColumn();

        return [
            'created' => $created,
            'resolved' => $resolved,
            'open' => $open,
            'critical_open' => $criticalOpen,
            'unassigned' => $unassigned,
            'pending_manager' => $pendingManager,
            'sla_response_breached' => $slaResponseBreached,
            'sla_resolution_breached' => $slaResolutionBreached,
            'active_users' => $activeUsers,
            'active_groups' => $activeGroups,
            'avg_resolution_minutes' => $avgMinutes !== false && $avgMinutes !== null ? (int) round((float) $avgMinutes) : null,
        ];
    }

    private function timeline(DateTimeImmutable $start, DateTimeImmutable $end, array $params): array
    {
        $createdStmt = $this->pdo->prepare(
            "SELECT DATE(created_at) day, COUNT(*) total
             FROM tickets
             WHERE deleted_at IS NULL AND created_at >= :start AND created_at < :end
             GROUP BY DATE(created_at)"
        );
        $createdStmt->execute($params);
        $createdMap = [];
        foreach ($createdStmt->fetchAll() as $row) {
            $createdMap[(string) $row['day']] = (int) $row['total'];
        }

        $resolvedStmt = $this->pdo->prepare(
            "SELECT DATE(resolved_at) day, COUNT(*) total
             FROM tickets
             WHERE deleted_at IS NULL AND resolved_at IS NOT NULL
               AND resolved_at >= :start AND resolved_at < :end
             GROUP BY DATE(resolved_at)"
        );
        $resolvedStmt->execute($params);
        $resolvedMap = [];
        foreach ($resolvedStmt->fetchAll() as $row) {
            $resolvedMap[(string) $row['day']] = (int) $row['total'];
        }

        $labels = [];
        $created = [];
        $resolved = [];
        for ($date = $start; $date <= $end; $date = $date->modify('+1 day')) {
            $key = $date->format('Y-m-d');
            $labels[] = $key;
            $created[] = $createdMap[$key] ?? 0;
            $resolved[] = $resolvedMap[$key] ?? 0;
        }

        return [
            'labels' => $labels,
            'created' => $created,
            'resolved' => $resolved,
        ];
    }

    private function distribution(string $sql, array $params): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return array_map(
            static fn(array $row): array => [
                'label' => (string) $row['label'],
                'total' => (int) $row['total'],
            ],
            $stmt->fetchAll()
        );
    }

    private function scalar(string $sql, array $params): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}
