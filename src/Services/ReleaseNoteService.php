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
                'version' => '1.3.0-dev.6',
                'date' => 'Développement',
                'title' => 'Récupération de mot de passe assistée',
                'items' => [
                    'Codes de récupération temporaires générés par Administrateur ou Support IT.',
                    'Code personnel à usage unique avec expiration automatique après 30 minutes.',
                    'Réinitialisation sécurisée depuis la page Mot de passe oublié.',
                    'Révocation des sessions existantes après changement du mot de passe.',
                    'Limitation des tentatives et journalisation dans l’audit TicketFlow.',
                    'Le Support IT ne peut pas générer de code pour un compte Administrateur.'
                ],
            ],
            [
                'version' => '1.3.0-dev.5',
                'date' => 'Développement',
                'title' => 'Amélioration de la langue anglaise',
                'items' => [
                    'Refonte des traductions de la page de connexion.',
                    'Traduction complète de la page de changement de mot de passe.',
                    'Traduction de la catégorie Help et des Codes d’erreur.',
                    'Ajout des nouvelles clés FR / EN dans le système de langue.',
                    'Suppression d’un ancien chargement CSS de login.php.',
                    'Préparation de la poursuite de l’audit anglais sur les pages Ticket et Administration.'
                ],
            ],
[
                'version' => '1.3.0-dev.4',
                'date' => 'Développement',
                'title' => 'Refonte noir & blanc de l’interface',
                'items' => [
                    'Thème clair recentré sur des surfaces blanches et gris neutres.',
                    'Thème sombre recentré sur le noir et l’anthracite.',
                    'Bleu TicketFlow réservé aux actions principales, états actifs et focus.',
                    'Contrastes harmonisés dans les formulaires, tableaux, tickets et notifications.',
                    'Amélioration des cartes, boutons, badges et composants en thème sombre.',
                    'Conservation de l’unique feuille public/assets/css/app.css.'
                ],
            ],
            [
                'version' => '1.3.0-dev.3',
                'date' => 'Développement',
                'title' => 'Codes d’erreur techniques TicketFlow',
                'items' => [
                    'Nouveau format officiel TE-001 à TE-999 (Technical Error).',
                    'Catalogue centralisé des erreurs techniques.',
                    'Page de recherche pour Administrateur et Support IT.',
                    'Journal technical-errors.log avec référence unique.',
                    'Premières intégrations : base de données et création de ticket.'
                ],
            ],
            [
                'version' => '1.3.0-dev.2',
                'date' => 'Développement',
                'title' => 'Dashboard et header — nouvelle navigation',
                'items' => [
                    'Refonte du Dashboard avec une hiérarchie plus claire et adaptée au rôle.',
                    'Ajout d’une barre d’état rapide : notifications, alertes et version installée.',
                    'Nouveau menu Accès rapides dans le header.',
                    'Nouveau menu de compte avec Paramètres, À propos et déconnexion.',
                    'Organisation des accès par catégories dans le header.',
                    'Responsive du nouveau Dashboard et des menus du header.'
                ],
            ],
            [
                'version' => '1.1.0-dev.5',
                'date' => 'Développement',
                'title' => 'Maintenance et composants de formulaire — finition',
                'items' => [
                    'Ajustements supplémentaires du mode maintenance immédiat.',
                    'Menus déroulants TicketFlow entièrement personnalisés au lieu du menu natif du navigateur.',
                    'Navigation clavier et synchronisation des menus avec les formulaires existants.',
                    'Finition visuelle des champs, cases et actions de maintenance.'
                ],
            ],
            [
                'version' => '1.1.0-dev.3',
                'date' => 'Développement',
                'title' => 'Alertes et maintenance — corrections fonctionnelles',
                'items' => [
                    'Correction de la fin automatique du mode maintenance avec le fuseau horaire TicketFlow.',
                    'Les alertes globales n’affichent plus que leur titre dans la bannière ; un clic ouvre le message complet.',
                    'Titre des alertes limité à 80 caractères et message étendu à 2000 caractères.',
                    'Nouvelle mise en page de la page À propos & Nouveautés.',
                    'Ajustements visuels supplémentaires de la page de maintenance.'
                ],
            ],
            [
                'version' => '1.1.0-dev.2',
                'date' => 'Développement',
                'title' => 'Maintenance et interface — correctifs',
                'items' => [
                    'Redirection automatique des Collaborateurs et Managers lors du démarrage d’une maintenance.',
                    'Fin automatique du mode maintenance lorsque la date de fin estimée est atteinte.',
                    'Correction de la déconnexion depuis la page de maintenance.',
                    'Nouvelle présentation de la page de maintenance.',
                    'Nouvelle présentation de la page À propos & Nouveautés.',
                    'Accès aux Alertes de service déplacé dans la barre supérieure pour Administrateur et IT.'
                ],
            ],
            [
                'version' => '1.1.0-dev.1',
                'date' => 'Développement',
                'title' => 'Alertes, maintenance et nouveautés',
                'items' => [
                    'Alertes de service globales en temps réel.',
                    'Page À propos et historique des versions.',
                    'Journal des nouveautés par utilisateur.',
                    'Mode maintenance TicketFlow.',
                    'Maintenances planifiées.',
                    'Extraction Excel des alertes et maintenances.'
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
