<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class AuditService
{
    public function __construct(private PDO $pdo)
    {
    }

    public function log(?int $userId, string $action, ?string $entityType = null, int|string|null $entityId = null, array|string|null $details = null): void
    {
        if (is_array($details)) {
            $details = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO audit_logs (user_id, action, entity_type, entity_id, details, ip_address, user_agent)
             VALUES (:user_id, :action, :entity_type, :entity_id, :details, :ip, :user_agent)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'action' => mb_substr($action, 0, 100),
            'entity_type' => $entityType !== null ? mb_substr($entityType, 0, 60) : null,
            'entity_id' => $entityId !== null ? mb_substr((string) $entityId, 0, 80) : null,
            'details' => $details,
            'ip' => $this->clientIp(),
            'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
        ]);
    }

    public function clientIp(): string
    {
        return mb_substr((string) ($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45);
    }
}
