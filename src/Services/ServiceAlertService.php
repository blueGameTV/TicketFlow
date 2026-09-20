<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class ServiceAlertService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function active(): array
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            "SELECT sa.*, u.firstname creator_firstname, u.lastname creator_lastname
             FROM service_alerts sa
             INNER JOIN users u ON u.id = sa.created_by
             WHERE sa.status IN ('active','monitoring')
               AND sa.starts_at <= :now_start
               AND (sa.ends_at IS NULL OR sa.ends_at > :now_end)
             ORDER BY FIELD(sa.severity,'critical','major','degraded','maintenance','info'), sa.starts_at DESC"
        );
        $stmt->execute(['now_start' => $now, 'now_end' => $now]);
        return $stmt->fetchAll();
    }

    public function all(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = $this->pdo->query(
            "SELECT sa.*, u.firstname creator_firstname, u.lastname creator_lastname,
                    ru.firstname resolver_firstname, ru.lastname resolver_lastname
             FROM service_alerts sa
             INNER JOIN users u ON u.id = sa.created_by
             LEFT JOIN users ru ON ru.id = sa.resolved_by
             ORDER BY sa.created_at DESC
             LIMIT {$limit}"
        );
        return $stmt->fetchAll();
    }

    public function create(int $userId, array $data): int
    {
        $service = trim((string)($data['service_name'] ?? ''));
        $title = trim((string)($data['title'] ?? ''));
        $message = trim((string)($data['message'] ?? ''));
        $severity = (string)($data['severity'] ?? 'info');
        $status = (string)($data['status'] ?? 'active');
        $startsAt = trim((string)($data['starts_at'] ?? ''));
        $endsAt = trim((string)($data['ends_at'] ?? ''));

        if ($service === '' || mb_strlen($service) > 120) {
            throw new RuntimeException('Le service concerné est obligatoire (120 caractères maximum).');
        }
        if ($title === '' || mb_strlen($title) > 80) {
            throw new RuntimeException('Le titre est obligatoire (80 caractères maximum).');
        }
        if ($message === '' || mb_strlen($message) > 2000) {
            throw new RuntimeException('Le message est obligatoire (2000 caractères maximum).');
        }
        if (!in_array($severity, ['info','degraded','major','critical','maintenance'], true)) {
            throw new RuntimeException('Niveau d’alerte invalide.');
        }
        if (!in_array($status, ['active','monitoring'], true)) {
            $status = 'active';
        }

        $startTs = $startsAt !== '' ? strtotime($startsAt) : time();
        if ($startTs === false) {
            throw new RuntimeException('La date de début est invalide.');
        }

        $endValue = null;
        if ($endsAt !== '') {
            $endTs = strtotime($endsAt);
            if ($endTs === false || $endTs <= $startTs) {
                throw new RuntimeException('La date de fin prévue doit être postérieure au début.');
            }
            $endValue = date('Y-m-d H:i:s', $endTs);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO service_alerts
             (created_by, service_name, title, message, severity, status, starts_at, ends_at)
             VALUES (:created_by,:service_name,:title,:message,:severity,:status,:starts_at,:ends_at)'
        );
        $stmt->execute([
            'created_by' => $userId,
            'service_name' => $service,
            'title' => $title,
            'message' => $message,
            'severity' => $severity,
            'status' => $status,
            'starts_at' => date('Y-m-d H:i:s', $startTs),
            'ends_at' => $endValue,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    public function resolve(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            "UPDATE service_alerts
             SET status='resolved', resolved_by=:uid, resolved_at=:resolved_at, ends_at=COALESCE(ends_at,:ended_at)
             WHERE id=:id"
        );
        $now = date('Y-m-d H:i:s');
        $stmt->execute(['uid' => $userId, 'resolved_at' => $now, 'ended_at' => $now, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM service_alerts WHERE id=:id');
        $stmt->execute(['id' => $id]);
    }

    public function severityLabel(string $severity): string
    {
        return [
            'info' => 'Information',
            'degraded' => 'Dégradation',
            'major' => 'Incident majeur',
            'critical' => 'Incident critique',
            'maintenance' => 'Maintenance',
        ][$severity] ?? 'Information';
    }
}
