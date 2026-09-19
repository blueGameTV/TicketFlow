# TicketFlow

TicketFlow est une application web interne de gestion de tickets IT développée en **PHP + MySQL/MariaDB** pour un environnement Apache.

Version actuelle : **v0.9.1-rc2** — Release Candidate avec installation automatisée.

## Validation Release Candidate

Avant une publication v1.0, exécutez :

```bash
php scripts/rc_preflight.php
```

Puis suivez `docs/release-candidate-checklist.md` sur une instance de pré-production.


## Fonctionnalités principales

- 4 rôles : **Administrateur**, **IT**, **Manager**, **Collaborateur** ;
- création et suivi des tickets ;
- workflow IT avec assignation, statuts et résolution ;
- validation Manager ;
- conversation en direct sans rechargement complet ;
- pièces jointes sécurisées ;
- notifications internes ;
- SLA et alertes ;
- exports Excel ;
- statistiques ;
- audit et sécurité ;
- avatars et préférences utilisateur ;
- recherche globale ;
- filtres avancés ;
- vues enregistrées ;
- actions multiples sur les tickets ;
- file e-mail et automatisations ;
- maintenance et diagnostics CLI.

## Prérequis

Environnement recommandé :

- Debian 12/13 ou distribution Linux équivalente ;
- Apache 2.4+ ;
- PHP 8.1+ ;
- MySQL 8+ ou MariaDB compatible ;
- extensions PHP : `pdo`, `pdo_mysql`, `mbstring`, `fileinfo`, `zip`.

Pour Debian :

```bash
sudo apt update
sudo apt install apache2 mariadb-server php php-mysql php-mbstring php-zip
```

## Installation rapide

Sur un serveur Debian/Ubuntu neuf, une seule commande prépare automatiquement Apache, PHP, MariaDB, TicketFlow, la base, les permissions et le cron :

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/install.sh | sudo bash
```

À la fin, l’installateur affiche un lien sécurisé à ouvrir dans le navigateur. L’assistant web permet de créer le premier compte **Administrateur**, puis se verrouille automatiquement.

Pour une installation manuelle ou un environnement personnalisé, consultez [`docs/installation.md`](docs/installation.md).

## Configuration

Ne versionnez jamais `config/config.php`.

Le fichier de référence est :

```text
config/config.example.php
```

Il contient :

- configuration de l'application ;
- accès MySQL ;
- SMTP / transport e-mail ;
- limites des pièces jointes.

## Cron

TicketFlow utilise un runner central :

```bash
php scripts/cron/run.php
```

Exemple toutes les 5 minutes :

```cron
*/5 * * * * /usr/bin/php /var/www/ticketflow/scripts/cron/run.php >> /var/www/ticketflow/storage/logs/cron.log 2>&1
```

## Structure du projet

```text
config/          configuration locale
public/          DocumentRoot Apache
src/             services, sécurité et accès aux données
templates/       templates partagés
database/        schéma et migrations
scripts/         outils CLI, cron et diagnostics
storage/         fichiers utilisateurs et logs non publics
docs/            documentation
deploy/          exemples de déploiement
.github/         modèles GitHub
```

Voir [`docs/architecture.md`](docs/architecture.md) pour davantage de détails.

## Mise à niveau

Avant chaque mise à niveau :

```bash
mysqldump -u ticketflow_user -p ticketflow > ticketflow-backup.sql
cp config/config.php config/config.php.backup
```

Puis consulter [`docs/upgrade.md`](docs/upgrade.md).

## Sécurité

TicketFlow inclut notamment :

- `password_hash()` / `password_verify()` ;
- CSRF ;
- contrôle des rôles côté serveur ;
- contrôle d'accès aux tickets ;
- upload hors de `public/` ;
- sessions PHP renforcées ;
- protection contre le brute-force ;
- journal d'audit ;
- en-têtes HTTP de sécurité.

Avant une mise en production :

```bash
php scripts/security_audit.php
```

Voir [`SECURITY.md`](SECURITY.md).

## Développement

Avant de proposer une modification :

```bash
find public src templates scripts -name '*.php' -print0 | xargs -0 -n1 php -l
php scripts/healthcheck.php
php scripts/release_check.php
```

Les règles de contribution sont dans [`CONTRIBUTING.md`](CONTRIBUTING.md).

## Documentation

- [Installation](docs/installation.md)
- [Déploiement Apache](docs/deployment-apache.md)
- [Mise à niveau](docs/upgrade.md)
- [Rôles et permissions](docs/roles-permissions.md)
- [Workflow tickets](docs/ticket-workflow.md)
- [E-mails et automatisations](docs/v014-mail-automation.md)
- [SLA](docs/sla.md)
- [Exports](docs/exports.md)
- [Notifications](docs/notifications.md)
- [Statistiques](docs/statistics.md)
- [Architecture](docs/architecture.md)

## État du projet

TicketFlow est encore en **préversion**. La branche `v0.17` prépare le projet pour les futures versions de release candidate avant `v1.0`.
