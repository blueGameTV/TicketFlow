# Architecture de TicketFlow

TicketFlow est une application PHP classique conçue pour fonctionner avec Apache et MariaDB/MySQL sans dépendre d'un framework lourd.

## Organisation du projet

```text
config/         configuration locale et modèle public
database/       schéma SQL et migrations
deploy/         exemples de configuration de déploiement
docs/           documentation utilisateur et administrateur
lang/           traductions Français / English
public/         DocumentRoot Apache et points d'entrée HTTP
scripts/        installation, migrations, cron et diagnostics
src/            logique applicative
storage/        logs, cache, sessions et uploads hors dossier public
templates/      composants et vues partagés
```

## Couche applicative

- `src/Database/` : connexion PDO et accès bas niveau à MariaDB/MySQL.
- `src/Security/` : authentification, sessions, CSRF et autorisations.
- `src/Services/` : logique métier : tickets, notifications, SLA, alertes, maintenance, imports et exports.
- `src/Support/` : fonctions transverses, traduction et utilitaires.

Les fichiers de `public/` restent les points d'entrée HTTP. Les contrôles de droits sont effectués côté serveur avant toute opération sensible.

## Interface dynamique

Certaines pages, notamment les tickets et les notifications, se mettent à jour sans rechargement complet grâce à JavaScript et à des requêtes HTTP légères.

Le serveur reste la source de vérité : les contrôles de permission, de rôle, de validation et de sécurité ne reposent jamais uniquement sur JavaScript.

## Données et stockage

La base MariaDB/MySQL contient les comptes, tickets, validations, paramètres, notifications, SLA, alertes de service et maintenances.

Les fichiers runtime sont conservés dans `storage/` :

- `storage/uploads/` : pièces jointes ;
- `storage/uploads/avatars/` : photos de profil ;
- `storage/logs/` : logs applicatifs ;
- `storage/cache/` : données temporaires ;
- `storage/sessions/` : données runtime lorsque nécessaire.

Le dossier `storage/` ne doit pas être exposé directement par Apache.

## Migrations

Le schéma d'une nouvelle installation se trouve dans `database/schema.sql`.

Les installations existantes sont mises à niveau par `update.sh`, qui applique automatiquement les migrations nécessaires dans l'ordre approprié.

## Principe de compatibilité

Les valeurs techniques historiques peuvent être conservées pour éviter de casser les installations existantes. Par exemple, le rôle affiché **Support IT** conserve la clé interne `IT`.
