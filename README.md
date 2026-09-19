<p align="center">
  <img src="docs/images/ticketflow-banner.svg" alt="TicketFlow v1.0.0" width="100%">
</p>

<p align="center">
  <strong>TicketFlow v1.0.0 — Stable</strong><br>
  Plateforme web interne de gestion des tickets IT en PHP, Apache et MariaDB/MySQL.
</p>

<p align="center">
  <img alt="Version" src="https://img.shields.io/badge/version-1.0.0-3156d9">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.1%2B-777BB4">
  <img alt="Apache" src="https://img.shields.io/badge/Apache-2.4%2B-D22128">
  <img alt="MariaDB" src="https://img.shields.io/badge/MariaDB-compatible-003545">
  <img alt="Status" src="https://img.shields.io/badge/status-stable-16a34a">
</p>

---

## À propos

**TicketFlow** est une application web interne conçue pour centraliser les demandes et incidents IT d'une entreprise.  
Elle propose quatre rôles distincts : **Administrateur**, **IT**, **Manager** et **Collaborateur**, avec des droits et workflows adaptés à chacun.

La version **v1.0.0 Stable** a été validée après installation sur Debian, tests fonctionnels, contrôles de sécurité et vérification du workflow complet.

## Fonctionnalités principales

- gestion complète des tickets : incidents, requêtes, changements et accès ;
- rôles **Administrateur / IT / Manager / Collaborateur** ;
- assignation et suivi des tickets par les équipes IT ;
- validation Manager lorsqu'une demande le nécessite ;
- conversation dans le ticket sans rechargement complet ;
- pièces jointes sécurisées ;
- notifications internes ;
- SLA et alertes ;
- recherche globale et filtres avancés ;
- vues enregistrées ;
- actions multiples sur les tickets ;
- exports utilisateurs et tickets ;
- statistiques et journal d'audit ;
- gestion des groupes et responsables ;
- paramètres utilisateur et avatars ;
- file e-mail, rappels et automatisations ;
- scripts de diagnostic, sécurité et maintenance ;
- installation automatisée sur Debian/Ubuntu.

## Installation sur Debian

### 1. Préparer le serveur

Sur une Debian propre :

```bash
apt update
apt install apache2 mariadb-server php php-mysql php-mbstring php-zip curl sudo
```

### 2. Installer TicketFlow automatiquement

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/install.sh | sudo bash
```

L'installateur prend automatiquement en charge :

- les dépendances nécessaires ;
- Apache et son VirtualHost ;
- MariaDB et la base `ticketflow` ;
- l'utilisateur SQL applicatif ;
- la génération de `config/config.php` ;
- les permissions de `storage/` ;
- les limites PHP pour les pièces jointes ;
- le cron TicketFlow ;
- la désactivation du site Apache Debian par défaut.

À la fin, une URL sécurisée est affichée :

```text
http://IP_DU_SERVEUR/setup.php?token=...
```

Ouvrez-la dans votre navigateur pour créer le **premier compte Administrateur**.

Après validation, TicketFlow crée `storage/installed.lock`, supprime le jeton d'installation et verrouille automatiquement l'assistant.

## Connexion

Après installation :

```text
http://IP_DU_SERVEUR/login.php
```

Connectez-vous avec le compte Administrateur créé lors de l'assistant initial.

## Rôles

| Rôle | Fonction principale |
| --- | --- |
| **Administrateur** | Gestion des utilisateurs, groupes, configuration, statistiques, audit et supervision globale |
| **IT** | Traitement, assignation, échanges, validation Manager et résolution des tickets |
| **Manager** | Création de tickets personnels et validation des demandes qui lui sont soumises |
| **Collaborateur** | Création, suivi et confirmation de résolution de ses propres tickets |

## Workflow simplifié

```text
Collaborateur
     │
     ▼
Création du ticket
     │
     ▼
Équipe IT ───────────────► Manager
     │                     │
     │                     └─ Validation si nécessaire
     │
     ▼
Proposition de résolution
     │
     ▼
Confirmation du demandeur
     │
     ▼
Ticket résolu / archivé
```

## Vérification de l'installation

Depuis le serveur :

```bash
cd /var/www/ticketflow

php scripts/healthcheck.php
php scripts/security_audit.php
php scripts/release_check.php
php scripts/route_check.php
php scripts/rc_preflight.php
```

Le diagnostic principal doit afficher la version :

```text
TicketFlow - diagnostic v1.0.0
```

## Configuration

Le fichier local utilisé par TicketFlow est :

```text
config/config.php
```

Il ne doit **jamais** être envoyé sur GitHub.

Le modèle public est :

```text
config/config.example.php
```

Il contient notamment la configuration :

- de l'application ;
- de MariaDB/MySQL ;
- du transport e-mail ;
- des pièces jointes.

## E-mails

TicketFlow prend en charge :

- `disabled` : e-mails désactivés ;
- `log` : simulation dans `storage/logs/mail.log` ;
- `smtp` : envoi via un serveur SMTP configuré.

Le mode `log` est recommandé pour les environnements de test.

## Tâches automatiques

Le cron principal est exécuté toutes les cinq minutes :

```cron
*/5 * * * * www-data /usr/bin/php /var/www/ticketflow/scripts/cron/run.php >> /var/www/ticketflow/storage/logs/cron.log 2>&1
```

Il gère notamment les SLA, rappels, automatisations, nettoyage et file e-mail.

## Structure du projet

```text
.github/        modèles GitHub
config/         configuration et modèle public
database/       schéma SQL et migrations
deploy/         configuration de déploiement
docs/           documentation
public/         DocumentRoot Apache et interface web
scripts/        installation, cron, diagnostics et maintenance
src/            logique applicative et services
storage/        logs, uploads et données runtime
templates/      composants d'interface partagés
```

## Sécurité

TicketFlow inclut notamment :

- `password_hash()` / `password_verify()` ;
- protection CSRF ;
- contrôle des rôles côté serveur ;
- vérification des droits d'accès aux tickets ;
- pièces jointes stockées hors du dossier public ;
- sessions PHP durcies ;
- limitation des tentatives de connexion ;
- journal d'audit ;
- en-têtes HTTP de sécurité ;
- verrouillage automatique de l'assistant d'installation.

Consultez [SECURITY.md](SECURITY.md) pour les informations de sécurité.

## Sauvegarde

Avant une mise à jour importante :

```bash
mysqldump -u ticketflow_user -p ticketflow > ticketflow-backup.sql
cp config/config.php config/config.php.backup
```

Les pièces jointes présentes dans `storage/uploads/` doivent également être incluses dans votre stratégie de sauvegarde.

## Documentation

- [Installation](docs/installation.md)
- [Déploiement Apache](docs/deployment-apache.md)
- [Mise à niveau](docs/upgrade.md)
- [Rôles et permissions](docs/roles-permissions.md)
- [Workflow des tickets](docs/ticket-workflow.md)
- [E-mails et automatisations](docs/v014-mail-automation.md)
- [SLA](docs/sla.md)
- [Exports](docs/exports.md)
- [Notifications](docs/notifications.md)
- [Statistiques](docs/statistics.md)
- [Architecture](docs/architecture.md)

## Contribution

Les règles de contribution sont disponibles dans [CONTRIBUTING.md](CONTRIBUTING.md).

## Version

**TicketFlow v1.0.0 Stable**

Première version stable validée après la phase Release Candidate et les tests d'installation sur Debian.

---

<p align="center">
  <strong>TicketFlow</strong> — Gestion des tickets IT simple, structurée et centralisée.
</p>
