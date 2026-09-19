<?php

declare(strict_types=1);

namespace App\Services;

use PDO;
use Throwable;

final class MailQueueService
{
    public function __construct(
        private PDO $pdo,
        private AppSettingService $settings,
        private NotificationPreferenceService $preferences,
        private array $mailConfig = []
    ) {
    }

    public function queueNotification(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $linkUrl = null
    ): void {
        if (!$this->settings->bool('email_notifications_enabled', false)) {
            return;
        }
        if (!$this->preferences->allowsEmail($userId, $type)) {
            return;
        }

        try {
            $stmt = $this->pdo->prepare('SELECT firstname, lastname, email, active FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch();
            if (!$user || empty($user['active']) || empty($user['email'])) {
                return;
            }

            $baseUrl = rtrim((string) ($this->mailConfig['base_url'] ?? ''), '/');
            $fullLink = $linkUrl;
            if ($linkUrl !== null && $linkUrl !== '' && $baseUrl !== '' && !preg_match('#^https?://#i', $linkUrl)) {
                $fullLink = $baseUrl . '/' . ltrim($linkUrl, '/');
            }
            $body = $this->renderNotificationBody(
                trim((string) $user['firstname'] . ' ' . (string) $user['lastname']),
                $title,
                $message,
                $fullLink
            );
            $this->queueRaw(
                $userId,
                (string) $user['email'],
                trim((string) $user['firstname'] . ' ' . (string) $user['lastname']),
                $type,
                '[TicketFlow] ' . $title,
                $body
            );
        } catch (Throwable) {
            // Une notification interne ne doit jamais échouer parce que l'e-mail est indisponible.
        }
    }

    public function queueDigest(int $userId, string $subject, string $html): void
    {
        if (!$this->settings->bool('email_notifications_enabled', false) || !$this->preferences->allowsDigest($userId)) {
            return;
        }
        try {
            $stmt = $this->pdo->prepare('SELECT firstname, lastname, email, active FROM users WHERE id = :id LIMIT 1');
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch();
            if (!$user || empty($user['active']) || empty($user['email'])) {
                return;
            }
            $this->queueRaw(
                $userId,
                (string) $user['email'],
                trim((string) $user['firstname'] . ' ' . (string) $user['lastname']),
                'daily_digest',
                $subject,
                $html
            );
        } catch (Throwable) {
        }
    }

    public function queueRaw(
        ?int $userId,
        string $email,
        string $name,
        string $type,
        string $subject,
        string $html,
        ?string $availableAt = null
    ): void {
        $stmt = $this->pdo->prepare(
            'INSERT INTO email_queue
             (user_id, recipient_email, recipient_name, notification_type, subject, body_html, available_at)
             VALUES (:user_id, :recipient_email, :recipient_name, :notification_type, :subject, :body_html, :available_at)'
        );
        $stmt->execute([
            'user_id' => $userId,
            'recipient_email' => mb_substr(trim($email), 0, 190),
            'recipient_name' => mb_substr(trim($name), 0, 160),
            'notification_type' => mb_substr(trim($type) ?: 'info', 0, 60),
            'subject' => mb_substr(trim($subject), 0, 190),
            'body_html' => $html,
            'available_at' => $availableAt ?? date('Y-m-d H:i:s'),
        ]);
    }

    public function process(int $limit = 30): array
    {
        $limit = max(1, min(100, $limit));
        $transport = strtolower((string) ($this->mailConfig['transport'] ?? 'disabled'));
        if (!in_array($transport, ['smtp', 'log'], true)) {
            return ['processed' => 0, 'sent' => 0, 'failed' => 0, 'disabled' => true];
        }

        $stmt = $this->pdo->query(
            "SELECT * FROM email_queue
             WHERE status IN ('queued','failed')
               AND attempts < 5
               AND available_at <= NOW()
             ORDER BY id ASC
             LIMIT {$limit}"
        );
        $rows = $stmt->fetchAll();
        $sent = 0;
        $failed = 0;
        $mailer = $transport === 'smtp' ? new SmtpMailer($this->mailConfig) : null;

        foreach ($rows as $row) {
            $claim = $this->pdo->prepare("UPDATE email_queue SET status='sending', attempts=attempts+1 WHERE id=:id AND status IN ('queued','failed')");
            $claim->execute(['id' => $row['id']]);
            if ($claim->rowCount() !== 1) {
                continue;
            }

            try {
                if ($transport === 'smtp') {
                    $mailer?->send(
                        (string) $row['recipient_email'],
                        (string) ($row['recipient_name'] ?? ''),
                        (string) $row['subject'],
                        (string) $row['body_html']
                    );
                } else {
                    $logDir = __DIR__ . '/../../storage/logs';
                    if (!is_dir($logDir)) {
                        @mkdir($logDir, 0770, true);
                    }
                    $entry = '[' . date('c') . '] TO=' . $row['recipient_email'] . ' SUBJECT=' . $row['subject'] . PHP_EOL;
                    @file_put_contents($logDir . '/mail.log', $entry, FILE_APPEND);
                }
                $done = $this->pdo->prepare("UPDATE email_queue SET status='sent', sent_at=NOW(), last_error=NULL WHERE id=:id");
                $done->execute(['id' => $row['id']]);
                $sent++;
            } catch (Throwable $e) {
                $retryDelay = min(3600, 60 * max(1, (int) $row['attempts'] + 1));
                $retry = $this->pdo->prepare(
                    "UPDATE email_queue
                     SET status='failed', last_error=:error,
                         available_at=DATE_ADD(NOW(), INTERVAL {$retryDelay} SECOND)
                     WHERE id=:id"
                );
                $retry->bindValue(':error', mb_substr($e->getMessage(), 0, 500));
                $retry->bindValue(':id', (int) $row['id'], PDO::PARAM_INT);
                $retry->execute();
                $failed++;
            }
        }

        return ['processed' => count($rows), 'sent' => $sent, 'failed' => $failed, 'disabled' => false];
    }

    public function stats(): array
    {
        try {
            $row = $this->pdo->query(
                "SELECT
                    SUM(status='queued') queued,
                    SUM(status='sent') sent,
                    SUM(status='failed') failed
                 FROM email_queue"
            )->fetch();
            return [
                'queued' => (int) ($row['queued'] ?? 0),
                'sent' => (int) ($row['sent'] ?? 0),
                'failed' => (int) ($row['failed'] ?? 0),
            ];
        } catch (Throwable) {
            return ['queued' => 0, 'sent' => 0, 'failed' => 0];
        }
    }

    private function renderNotificationBody(string $name, string $title, string $message, ?string $link): string
    {
        $safeName = htmlspecialchars($name !== '' ? $name : 'Utilisateur', ENT_QUOTES, 'UTF-8');
        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeMessage = nl2br(htmlspecialchars($message, ENT_QUOTES, 'UTF-8'));
        $button = '';
        if ($link !== null && $link !== '') {
            $safeLink = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');
            $button = '<p style="margin:24px 0 0"><a href="' . $safeLink . '" style="display:inline-block;background:#3157d5;color:#fff;text-decoration:none;padding:12px 18px;border-radius:9px;font-weight:700">Ouvrir TicketFlow</a></p>';
        }
        return '<!doctype html><html><body style="margin:0;background:#f3f6fb;font-family:Arial,sans-serif;color:#14213d">'
            . '<div style="max-width:640px;margin:28px auto;background:white;border:1px solid #e0e6ef;border-radius:14px;padding:28px">'
            . '<div style="font-weight:800;color:#3157d5;margin-bottom:22px">TicketFlow</div>'
            . '<p>Bonjour ' . $safeName . ',</p>'
            . '<h2 style="margin:18px 0 10px">' . $safeTitle . '</h2>'
            . '<p style="line-height:1.6;color:#52627a">' . $safeMessage . '</p>'
            . $button
            . '<p style="margin-top:28px;color:#8a97aa;font-size:12px">Notification automatique TicketFlow.</p>'
            . '</div></body></html>';
    }
}
