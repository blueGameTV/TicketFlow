<p align="center">
  <img src="docs/images/ticketflow-banner.svg" alt="TicketFlow v1.1.0" width="100%">
</p>

<p align="center">
  <strong>TicketFlow v1.1.0 — Stable</strong><br>
  Plateforme web interne de gestion des tickets IT en PHP, Apache et MariaDB/MySQL.
</p>

<p align="center">
  <img alt="Version" src="https://img.shields.io/badge/version-1.1.0-3156d9">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.1%2B-777BB4">
  <img alt="Apache" src="https://img.shields.io/badge/Apache-2.4%2B-D22128">
  <img alt="MariaDB" src="https://img.shields.io/badge/MariaDB-compatible-003545">
  <img alt="Status" src="https://img.shields.io/badge/status-stable-16a34a">
</p>

---

## À propos

**TicketFlow** est une application web interne conçue pour centraliser les demandes et incidents IT d'une entreprise.  
Elle propose quatre rôles distincts : **Administrateur**, **IT**, **Manager** et **Collaborateur**, avec des droits et workflows adaptés à chacun.

La version **v1.1.0 Stable** ajoute les alertes de service globales, la maintenance immédiate et planifiée, la page **À propos & Nouveautés**, le journal des nouveautés, une nouvelle extraction Excel et plusieurs améliorations d'interface.

## Nouveautés v1.1.0

- alertes de service globales visibles en temps réel ;
- détail complet d'une alerte au clic ;
- titre des alertes limité à 80 caractères et message à 2000 caractères ;
- page **À propos & Nouveautés** ;
- journal des nouveautés mémorisé par utilisateur ;
- mode maintenance TicketFlow ;
- redirection automatique des Collaborateurs et Managers pendant une maintenance bloquante ;
- accès Administrateur conservé et accès IT configurable ;
- arrêt automatique du mode maintenance à la fin prévue ;
- maintenances planifiées avec bandeau d'information ;
- nouvelle extraction Excel des alertes et maintenances ;
- menus déroulants, recherche, champs fichiers et formulaires harmonisés.

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
- exports utilisateurs, tickets et opérations ;
- statistiques et journal d'audit ;
- gestion des groupes et responsables ;
- paramètres utilisateur et avatars ;
- file e-mail, rappels et automatisations ;
- alertes de service globales ;
- mode maintenance et maintenances planifiées ;
- scripts de diagnostic, sécurité, migration et mise à jour ;
- installation automatisée sur Debian/Ubuntu.

## Installation sur Debian

### 1. Préparer le serveur

Sur une Debian propre :

```bash
apt update
apt install apache2 mariadb-server php php-mysql php-mbstring php-zip curl sudo
```

### 2. Installer TicketFlow automatiquement

> Cette commande est destinée à une **nouvelle installation**.

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/install-v1.1.0.sh | sudo bash
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

## Mise à jour d'une installation existante

Depuis la v1.1.0, TicketFlow propose un script de mise à jour **sur place** : il ne réinstalle pas Apache, PHP ou MariaDB et **conserve la configuration, la base de données, les comptes, les tickets et les pièces jointes**.

Pour mettre à jour une installation existante :

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/update.sh | sudo bash
```

Le script :

- vérifie que l'installation actuelle est bien un dépôt Git TicketFlow ;
- corrige automatiquement les anciens changements de mode sur les fichiers `storage/**/.gitkeep` produits par la v1.0.0 ;
- refuse uniquement la mise à jour si de vrais fichiers applicatifs suivis ont été modifiés localement ;
- sauvegarde automatiquement `config/config.php` ;
- sauvegarde la base MariaDB/MySQL ;
- sauvegarde `storage/uploads/` ;
- récupère la dernière version stable depuis `main` ;
- exécute automatiquement les migrations nécessaires ;
- conserve les comptes, tickets, groupes, paramètres, pièces jointes et données existantes ;
- lance les contrôles de santé et de routes ;
- recharge Apache.

Les sauvegardes sont placées par défaut dans :

```text
/var/backups/ticketflow/
```

### Mise à jour manuelle v1.0.0 → v1.1.0

Si vous préférez effectuer la mise à jour manuellement :

```bash
cd /var/www/ticketflow
php scripts/upgrade_v110.php
php scripts/healthcheck.php
php scripts/security_audit.php
php scripts/route_check.php
php scripts/release_check.php
sudo systemctl reload apache2
```

Consultez également [docs/upgrade.md](docs/upgrade.md).

## Connexion

Après installation :

```text
http://IP_DU_SERVEUR/login.php
```

Connectez-vous avec le compte Administrateur créé lors de l'assistant initial.

## Rôles

| Rôle | Fonction principale |
| --- | --- |
| **Administrateur** | Gestion des utilisateurs, groupes, configuration, statistiques, audit, alertes, maintenance et supervision globale |
| **IT** | Traitement, assignation, échanges, validation Manager, résolution des tickets et alertes de service |
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

Le diagnostic principal doit afficher :

```text
TicketFlow - diagnostic v1.1.0
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
- du fuseau horaire ;
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
scripts/        installation, migrations, cron, diagnostics et maintenance
src/            logique applicative et services
storage/        logs, uploads et données runtime
templates/      composants d'interface partagés
update.sh       mise à jour sécurisée d'une installation existante
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
- verrouillage automatique de l'assistant d'installation ;
- sauvegarde automatique avant mise à jour avec `update.sh`.

La branche **v1.1.x** est la branche stable recommandée. Les installations **v1.0.x** doivent être mises à jour vers la dernière v1.1.x afin de bénéficier des correctifs et améliorations les plus récents.

Consultez [SECURITY.md](SECURITY.md) pour les informations de sécurité.

## Sauvegarde

Avant une opération importante, vous pouvez toujours effectuer une sauvegarde manuelle :

```bash
mysqldump -u ticketflow_user -p ticketflow > ticketflow-backup.sql
cp config/config.php config/config.php.backup
```

Les pièces jointes présentes dans `storage/uploads/` doivent également être incluses dans votre stratégie de sauvegarde.

Le script `update.sh` crée automatiquement une sauvegarde avant chaque mise à jour.

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
- [Notes de version v1.1.0](docs/release-notes-v110.md)

## Contribution

Les règles de contribution sont disponibles dans [CONTRIBUTING.md](CONTRIBUTING.md).

## Version

**TicketFlow v1.1.0 Stable**

Version stable incluant les alertes de service globales, le mode maintenance, les maintenances planifiées, le journal des nouveautés et la nouvelle extraction opérationnelle.

---

<p align="center">
  <strong>TicketFlow</strong> — Gestion des tickets IT simple, structurée et centralisée.
</p>
