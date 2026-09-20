<p align="center">
  <img src="docs/images/ticketflow-banner.svg" alt="TicketFlow v1.1.0" width="100%">
</p>

# TicketFlow v1.1.0 Stable

<p align="center">
  <img alt="Version" src="https://img.shields.io/badge/version-1.1.0-3156d9?style=for-the-badge">
  <img alt="Status" src="https://img.shields.io/badge/status-stable-16a34a?style=for-the-badge">
  <img alt="PHP" src="https://img.shields.io/badge/PHP-8.1%2B-777BB4?style=for-the-badge&logo=php&logoColor=white">
  <img alt="Apache" src="https://img.shields.io/badge/Apache-2.4%2B-D22128?style=for-the-badge&logo=apache&logoColor=white">
  <img alt="MariaDB" src="https://img.shields.io/badge/MariaDB-10.5%2B-003545?style=for-the-badge&logo=mariadb&logoColor=white">
</p>

TicketFlow est une plateforme interne de ticketing IT développée en PHP avec Apache et MariaDB/MySQL. Elle couvre le cycle de vie d'un ticket entre Collaborateur, Manager, IT et Administrateur.

## Nouveautés v1.1.0

- alertes de service globales en temps réel ;
- détail complet d'une alerte au clic ;
- page **À propos & Nouveautés** avec version installée et historique ;
- journal des nouveautés mémorisé par utilisateur ;
- mode maintenance TicketFlow avec redirection automatique ;
- fin automatique de maintenance ;
- maintien de l'accès Administrateur et accès IT configurable ;
- maintenances planifiées avec bandeau d'information ;
- nouvelle extraction Excel des alertes et maintenances ;
- composants de formulaire et menus déroulants harmonisés.

## Fonctions principales

- création, suivi et traitement des tickets ;
- rôles Administrateur, IT, Manager et Collaborateur ;
- validation Manager ;
- messages et pièces jointes ;
- SLA et notifications ;
- recherche globale et filtres avancés ;
- vues enregistrées et actions multiples ;
- statistiques, audit et exports Excel ;
- file d'e-mails et automatisations ;
- alertes de service et maintenance applicative.

## Installation Debian / Ubuntu

Préparer le serveur :

```bash
apt update
apt install apache2 mariadb-server php php-mysql php-mbstring php-zip curl sudo
```

Puis lancer l'installateur **uniquement pour une nouvelle installation** :

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/install.sh | sudo bash
```

> **Important :** `install.sh` est destiné aux installations neuves. Ne l'utilisez pas pour mettre à niveau une installation TicketFlow v1.0.0 existante vers v1.1.0.

L'installateur configure Apache, MariaDB, PHP, les permissions, le cron, la base TicketFlow et l'assistant de création du premier Administrateur.

À la fin, ouvrez l'URL affichée :

```text
http://IP_DU_SERVEUR/setup.php?token=...
```

Après création du premier Administrateur, l'assistant est verrouillé.

## Mise à jour de v1.0.0 vers v1.1.0

**N'utilisez pas `install.sh` pour effectuer cette mise à jour.**

Avant toute mise à jour, sauvegardez la base de données, `config/config.php` et `storage/uploads/`.

Après avoir récupéré/remplacé les fichiers applicatifs par la v1.1.0 en conservant votre configuration locale et vos données, exécutez :

```bash
cd /var/www/ticketflow
php scripts/upgrade_v110.php
php scripts/healthcheck.php
```

Puis rechargez Apache si nécessaire :

```bash
sudo systemctl reload apache2
```

La migration crée les tables nécessaires aux alertes, aux maintenances et au journal des nouveautés.

## Rôles

| Rôle | Fonctions principales |
| --- | --- |
| Administrateur | Utilisateurs, groupes, tickets, exports, statistiques, audit, configuration, alertes et maintenance |
| IT | Traitement des tickets, communication, escalade Manager, suivi équipe et alertes de service |
| Manager | Création de tickets et validations demandées par l'IT |
| Collaborateur | Création, suivi et confirmation de résolution |

## Vérifications

```bash
php scripts/healthcheck.php
php scripts/security_audit.php
php scripts/route_check.php
php scripts/release_check.php
```

## Sécurité

Ne committez jamais `config/config.php`, des mots de passe, des jetons, des dumps de production ou de vraies pièces jointes. Consultez [SECURITY.md](SECURITY.md).

## Documentation

- [Installation](docs/installation.md)
- [Mise à jour](docs/upgrade.md)
- [Rôles et permissions](docs/roles-permissions.md)
- [Workflow ticket](docs/ticket-workflow.md)
- [Exports](docs/exports.md)
- [Notes de version v1.1.0](docs/release-notes-v110.md)
- [Contribuer](CONTRIBUTING.md)

## Version

Version actuelle : **1.1.0 Stable**
