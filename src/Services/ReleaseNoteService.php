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
        // Le journal public affiche uniquement les versions stables.
        return [
            [
                'version' => '1.3.0',
                'date' => 'Stable',
                'title' => 'TicketFlow v1.3.0 — Interface, sécurité et assistance',
                'items' => [
                    'Nouvelle interface de connexion, adaptée aux thèmes clair et sombre.',
                    'Harmonisation des styles et amélioration des pages, formulaires et menus.',
                    'Récupération de mot de passe par e-mail ou avec un code validé par le Support IT.',
                    'Catalogue des codes d’erreur techniques accessible aux Administrateurs et au Support IT.',
                    'Amélioration de la gestion et de l’affichage des maintenances.',
                    'Améliorations des traductions françaises et anglaises.',
                ],
            ],
            [
                'version' => '1.2.0',
                'date' => 'Stable',
                'title' => 'Thèmes, import, validation Manager et langues',
                'items' => [
                    'Thème clair, sombre ou système mémorisé par utilisateur.',
                    'Interface Français / English avec langue enregistrée par compte.',
                    'Validation Manager à deux niveaux : N+1 puis Manager sélectionné.',
                    'Import utilisateurs CSV / Excel avec prévisualisation et contrôles.',
                    'Changement forcé du mot de passe et assistant d’installation modernisés.',
                    'Calendriers, conversations, pièces jointes et alertes de service améliorés.',
                ],
            ],
            [
                'version' => '1.1.0',
                'date' => 'Stable',
                'title' => 'Alertes, maintenance et nouveautés',
                'items' => [
                    'Alertes de service et suivi des incidents.',
                    'Mode maintenance immédiat et maintenances planifiées.',
                    'Historique des nouveautés et suivi de lecture par utilisateur.',
                    'Export des alertes et maintenances.',
                    'Améliorations de la navigation et des formulaires.',
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
                    'Installation automatique Debian/Ubuntu.',
                ],
            ],
        ];
    }
}
