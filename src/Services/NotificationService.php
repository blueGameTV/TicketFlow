<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use RuntimeException;

final class NotificationService
{
    public function __construct(private PDO $pdo, private ?MailQueueService $mailQueue = null)
    {
    }

    public function notify(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?int $ticketId = null,
        ?string $linkUrl = null
    ): void {
        if ($userId <= 0) {
            return;
        }

        $title = trim($title);
        $message = trim($message);
        $type = trim($type) !== '' ? trim($type) : 'info';

        if ($title === '' || mb_strlen($title) > 150) {
            throw new RuntimeException('Titre de notification invalide.');
        }
        if (mb_strlen($message) > 500) {
            $message = mb_substr($message, 0, 497) . '...';
        }
        if ($linkUrl !== null && mb_strlen($linkUrl) > 255) {
            $linkUrl = mb_substr($linkUrl, 0, 255);
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO notifications (user_id, ticket_id, type, title, message, link_url)
             VALUES (:user_id, :ticket_id, :type, :title, :message, :link_url)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'ticket_id' => $ticketId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link_url' => $linkUrl,
        ]);

        if ($this->mailQueue !== null) {
            $this->mailQueue->queueNotification($userId, $type, $title, $message, $linkUrl);
        }
    }

    public function notifyMany(
        array $userIds,
        string $type,
        string $title,
        string $message,
        ?int $ticketId = null,
        ?string $linkUrl = null,
        ?int $excludeUserId = null
    ): void {
        $unique = array_values(array_unique(array_map('intval', $userIds)));
        foreach ($unique as $userId) {
            if ($userId <= 0 || ($excludeUserId !== null && $userId === $excludeUserId)) {
                continue;
            }
            $this->notify($userId, $type, $title, $message, $ticketId, $linkUrl);
        }
    }

    public function unreadCount(int $userId): int
    {
        $stmt = $this->pdo->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_at IS NULL');
        $stmt->execute(['user_id' => $userId]);
        return (int) $stmt->fetchColumn();
    }

    public function listForUser(int $userId, bool $unreadOnly = false, int $limit = 100): array
    {
        $limit = max(1, min(200, $limit));
        $sql = 'SELECT n.id, n.ticket_id, n.type, n.title, n.message, n.link_url, n.read_at, n.created_at,
                       t.ticket_number
                FROM notifications n
                LEFT JOIN tickets t ON t.id = n.ticket_id
                WHERE n.user_id = :user_id';
        if ($unreadOnly) {
            $sql .= ' AND n.read_at IS NULL';
        }
        $sql .= ' ORDER BY n.created_at DESC, n.id DESC LIMIT ' . $limit;

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll();
    }

    public function markRead(int $notificationId, int $userId): void
    {
        $stmt = $this->pdo->prepare(
            'UPDATE notifications SET read_at = COALESCE(read_at, NOW())
             WHERE id = :id AND user_id = :user_id'
        );
        $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);
    }

    public function markAllRead(int $userId): void
    {
        $stmt = $this->pdo->prepare('UPDATE notifications SET read_at = NOW() WHERE user_id = :user_id AND read_at IS NULL');
        $stmt->execute(['user_id' => $userId]);
    }
    public function getForUser(int $notificationId, int $userId): ?array
    {
        $stmt = $this->pdo->prepare(
            'SELECT id, user_id, ticket_id, type, title, message, link_url, read_at, created_at
             FROM notifications
             WHERE id = :id AND user_id = :user_id
             LIMIT 1'
        );
        $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function deleteForUser(int $notificationId, int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM notifications WHERE id = :id AND user_id = :user_id');
        $stmt->execute(['id' => $notificationId, 'user_id' => $userId]);
    }

    public function markTicketRead(int $ticketId, int $userId): void
    {
        if ($ticketId <= 0 || $userId <= 0) {
            return;
        }

        $stmt = $this->pdo->prepare(
            'UPDATE notifications SET read_at = COALESCE(read_at, NOW())
             WHERE ticket_id = :ticket_id AND user_id = :user_id AND read_at IS NULL'
        );
        $stmt->execute(['ticket_id' => $ticketId, 'user_id' => $userId]);
    }

    public function deleteAllForUser(int $userId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM notifications WHERE user_id = :user_id');
        $stmt->execute(['user_id' => $userId]);
    }

    public function purgeReadOlderThanDays(int $days = 1): int
    {
        $days = max(1, min(365, $days));
        $stmt = $this->pdo->prepare(
            'DELETE FROM notifications
             WHERE read_at IS NOT NULL
               AND read_at < DATE_SUB(NOW(), INTERVAL ' . $days . ' DAY)'
        );
        $stmt->execute();
        return $stmt->rowCount();
    }

}
