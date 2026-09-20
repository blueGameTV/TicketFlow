<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class MaintenanceService
{
    public function __construct(private PDO $pdo, private AppSettingService $settings)
    {
    }

    public function manualEnabled(): bool
    {
        if (!$this->settings->bool('maintenance_mode_enabled', false)) {
            return false;
        }

        // Les valeurs datetime-local sont stockées comme heure locale TicketFlow.
        // La comparaison utilise le fuseau configuré dans bootstrap.php, et non
        // le fuseau système implicite de PHP/MariaDB.
        $expectedEnd = trim($this->settings->get('maintenance_expected_end', ''));
        if ($expectedEnd !== '') {
            $endTs = strtotime($expectedEnd);
            if ($endTs === false || $endTs <= time()) {
                $this->settings->setMany([
                    'maintenance_mode_enabled' => '0',
                    'maintenance_expected_end' => '',
                ]);
                return false;
            }
        }

        return true;
    }

    public function allowIt(): bool
    {
        return $this->settings->bool('maintenance_allow_it', true);
    }

    public function activeBlockingWindow(): ?array
    {
        $now = date('Y-m-d H:i:s');
        $stmt = $this->pdo->prepare(
            "SELECT mw.*, u.firstname creator_firstname, u.lastname creator_lastname
             FROM maintenance_windows mw
             INNER JOIN users u ON u.id=mw.created_by
             WHERE mw.cancelled_at IS NULL
               AND mw.block_access=1
               AND mw.starts_at <= :now_start
               AND mw.ends_at > :now_end
             ORDER BY mw.starts_at ASC
             LIMIT 1"
        );
        $stmt->execute(['now_start' => $now, 'now_end' => $now]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function upcoming(int $hours = 24): array
    {
        $hours = max(1, min(168, $hours));
        $now = date('Y-m-d H:i:s');
        $future = date('Y-m-d H:i:s', time() + ($hours * 3600));
        $stmt = $this->pdo->prepare(
            "SELECT mw.*, u.firstname creator_firstname, u.lastname creator_lastname
             FROM maintenance_windows mw
             INNER JOIN users u ON u.id=mw.created_by
             WHERE mw.cancelled_at IS NULL
               AND mw.ends_at > :now
               AND mw.starts_at <= :future
             ORDER BY mw.starts_at ASC"
        );
        $stmt->execute(['now' => $now, 'future' => $future]);
        return $stmt->fetchAll();
    }

    public function all(int $limit = 100): array
    {
        $limit = max(1, min(500, $limit));
        $stmt = $this->pdo->query(
            "SELECT mw.*, u.firstname creator_firstname, u.lastname creator_lastname,
                    cu.firstname canceller_firstname, cu.lastname canceller_lastname
             FROM maintenance_windows mw
             INNER JOIN users u ON u.id=mw.created_by
             LEFT JOIN users cu ON cu.id=mw.cancelled_by
             ORDER BY mw.starts_at DESC
             LIMIT {$limit}"
        );
        return $stmt->fetchAll();
    }

    public function accessBlocked(): bool
    {
        return $this->manualEnabled() || $this->activeBlockingWindow() !== null;
    }

    public function roleAllowed(?string $role): bool
    {
        if ($role === 'Administrateur') {
            return true;
        }
        return $role === 'IT' && $this->allowIt();
    }

    public function publicMessage(): array
    {
        if ($this->manualEnabled()) {
            return [
                'title' => 'TicketFlow est en maintenance',
                'message' => $this->settings->get('maintenance_message', 'TicketFlow est actuellement en maintenance.'),
                'ends_at' => $this->settings->get('maintenance_expected_end', ''),
                'source' => 'manual',
            ];
        }
        $window = $this->activeBlockingWindow();
        if ($window) {
            return [
                'title' => (string)$window['title'],
                'message' => (string)$window['message'],
                'ends_at' => (string)$window['ends_at'],
                'source' => 'scheduled',
            ];
        }
        return ['title' => 'TicketFlow', 'message' => '', 'ends_at' => '', 'source' => 'none'];
    }

    public function setManual(bool $enabled, string $message, string $expectedEnd, bool $allowIt): void
    {
        $message = trim($message);
        if ($message === '') {
            $message = 'TicketFlow est actuellement en maintenance.';
        }
        if (mb_strlen($message) > 1200) {
            throw new RuntimeException('Le message de maintenance est trop long.');
        }

        $expectedEnd = trim($expectedEnd);
        if ($expectedEnd !== '') {
            $endTs = strtotime($expectedEnd);
            if ($endTs === false) {
                throw new RuntimeException('La fin estimée de maintenance est invalide.');
            }
            if ($enabled && $endTs <= time()) {
                throw new RuntimeException('La fin estimée doit être située dans le futur.');
            }
            $expectedEnd = date('Y-m-d H:i:s', $endTs);
        }

        $this->settings->setMany([
            'maintenance_mode_enabled' => $enabled ? '1' : '0',
            'maintenance_message' => $message,
            'maintenance_expected_end' => $enabled ? $expectedEnd : '',
            'maintenance_allow_it' => $allowIt ? '1' : '0',
        ]);
    }

    public function schedule(int $userId, array $data): int
    {
        $title = trim((string)($data['title'] ?? ''));
        $message = trim((string)($data['message'] ?? ''));
        $startsAt = trim((string)($data['starts_at'] ?? ''));
        $endsAt = trim((string)($data['ends_at'] ?? ''));
        $block = !empty($data['block_access']);

        if ($title === '' || mb_strlen($title) > 160) {
            throw new RuntimeException('Le titre de la maintenance est obligatoire.');
        }
        if ($message === '' || mb_strlen($message) > 1200) {
            throw new RuntimeException('Le message de maintenance est obligatoire.');
        }
        $startTs = strtotime($startsAt);
        $endTs = strtotime($endsAt);
        if (!$startTs || !$endTs || $endTs <= $startTs) {
            throw new RuntimeException('La période de maintenance est invalide.');
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO maintenance_windows (created_by,title,message,starts_at,ends_at,block_access)
             VALUES (:uid,:title,:message,:starts_at,:ends_at,:block_access)'
        );
        $stmt->execute([
            'uid' => $userId,
            'title' => $title,
            'message' => $message,
            'starts_at' => date('Y-m-d H:i:s', $startTs),
            'ends_at' => date('Y-m-d H:i:s', $endTs),
            'block_access' => $block ? 1 : 0,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function cancel(int $id, int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE maintenance_windows SET cancelled_at=:cancelled_at, cancelled_by=:uid WHERE id=:id AND cancelled_at IS NULL');
        $stmt->execute(['cancelled_at' => date('Y-m-d H:i:s'), 'uid' => $userId, 'id' => $id]);
    }
}
