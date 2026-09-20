<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class ReleaseNoteService
{
    public function __construct(private PDO $pdo, private string $root)
    {
    }

    public function currentVersion(): string
    {
        $file = $this->root . '/VERSION';
        return is_file($file) ? trim((string)file_get_contents($file)) : 'inconnue';
    }

    public function hasSeen(int $userId, ?string $version = null): bool
    {
        $version ??= $this->currentVersion();
        $stmt = $this->pdo->prepare('SELECT 1 FROM user_release_views WHERE user_id=:uid AND version=:version LIMIT 1');
        $stmt->execute(['uid' => $userId, 'version' => $version]);
        return (bool)$stmt->fetchColumn();
    }

    public function markSeen(int $userId, ?string $version = null): void
    {
        $version ??= $this->currentVersion();
        $stmt = $this->pdo->prepare(
            'INSERT INTO user_release_views (user_id,version,seen_at) VALUES (:uid,:version,NOW())
             ON DUPLICATE KEY UPDATE seen_at=VALUES(seen_at)'
        );
        $stmt->execute(['uid' => $userId, 'version' => $version]);
    }

    public function releases(): array
    {
        return [
            [
                'version' => '1.1.0',
                'date' => 'Stable',
                'title' => 'Alertes, maintenance et nouveautés',
                'items' => [
                    'Alertes de service globales en temps réel avec détail au clic.',
                    'Page À propos et journal des nouveautés par utilisateur.',
                    'Mode maintenance TicketFlow avec fin automatique et contrôle des rôles.',
                    'Maintenances planifiées avec bannière d’information.',
                    'Nouvelle extraction Excel des alertes et maintenances.',
                    'Composants de formulaires et menus déroulants harmonisés.'
                ],
            ],
            [
                'version' => '1.0.0',
                'date' => 'Stable',
                'title' => 'Première version stable',
                'items' => [
                    'Workflows Administrateur, IT, Manager et Collaborateur.',
                    'Recherche globale, filtres avancés, vues enregistrées et actions multiples.',
                    'SLA, notifications, exports, statistiques, audit, e-mails et automatisations.',
                    'Installation automatique Debian/Ubuntu.'
                ],
            ],
        ];
    }
}
