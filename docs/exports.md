# Extractions Excel

Les exports sont réservés au rôle **Administrateur**.

## Limite temporelle

Chaque export doit avoir une date de début et une date de fin. La période ne peut pas dépasser 6 mois.

- Utilisateurs : filtre sur `users.created_at`.
- Tickets : filtre sur `tickets.created_at`.

## Sécurité

- Contrôle RBAC Administrateur.
- Protection CSRF.
- Requêtes SQL préparées.
- Aucun mot de passe n'est exporté.
- Journalisation dans `storage/logs/exports.log`.

## Dépendance

Le générateur XLSX est intégré au projet et utilise `ZipArchive` (`php-zip`).
