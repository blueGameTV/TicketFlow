<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class NotificationPreferenceService
{
    private const DEFAULTS = [
        'email_enabled' => 1,
        'email_messages' => 1,
        'email_ticket_updates' => 1,
        'email_validations' => 1,
        'email_resolution' => 1,
        'email_sla' => 1,
        'email_daily_digest' => 0,
    ];

    public function __construct(private PDO $pdo)
    {
    }

    public function get(int $userId): array
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM notification_preferences WHERE user_id = :user_id LIMIT 1');
            $stmt->execute(['user_id' => $userId]);
            $row = $stmt->fetch();
            if ($row) {
                return array_merge(self::DEFAULTS, $row);
            }
        } catch (Throwable) {
        }
        return array_merge(self::DEFAULTS, ['user_id' => $userId]);
    }

    public function save(int $userId, array $values): void
    {
        $normalized = [];
        foreach (array_keys(self::DEFAULTS) as $key) {
            $normalized[$key] = !empty($values[$key]) ? 1 : 0;
        }
        $stmt = $this->pdo->prepare(
            'INSERT INTO notification_preferences
             (user_id, email_enabled, email_messages, email_ticket_updates, email_validations, email_resolution, email_sla, email_daily_digest)
             VALUES (:user_id, :email_enabled, :email_messages, :email_ticket_updates, :email_validations, :email_resolution, :email_sla, :email_daily_digest)
             ON DUPLICATE KEY UPDATE
                email_enabled = VALUES(email_enabled),
                email_messages = VALUES(email_messages),
                email_ticket_updates = VALUES(email_ticket_updates),
                email_validations = VALUES(email_validations),
                email_resolution = VALUES(email_resolution),
                email_sla = VALUES(email_sla),
                email_daily_digest = VALUES(email_daily_digest)'
        );
        $stmt->execute(['user_id' => $userId] + $normalized);
    }

    public function allowsEmail(int $userId, string $notificationType): bool
    {
        $prefs = $this->get($userId);
        if (empty($prefs['email_enabled'])) {
            return false;
        }

        $preferenceKey = $this->preferenceKeyForType($notificationType);
        return !empty($prefs[$preferenceKey]);
    }

    public function allowsDigest(int $userId): bool
    {
        $prefs = $this->get($userId);
        return !empty($prefs['email_enabled']) && !empty($prefs['email_daily_digest']);
    }

    private function preferenceKeyForType(string $type): string
    {
        $type = strtolower($type);
        if (str_contains($type, 'message')) {
            return 'email_messages';
        }
        if (str_contains($type, 'manager') || str_contains($type, 'validation')) {
            return 'email_validations';
        }
        if (str_contains($type, 'resolution') || str_contains($type, 'confirm')) {
            return 'email_resolution';
        }
        if (str_contains($type, 'sla')) {
            return 'email_sla';
        }
        return 'email_ticket_updates';
    }
}
