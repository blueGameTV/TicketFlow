# Installation de TicketFlow

Ce guide concerne une **nouvelle installation** de TicketFlow v1.2.0 sur Debian / Ubuntu.

Pour mettre à jour une installation existante, utilisez [upgrade.md](upgrade.md).

## 1. Préparer le serveur

```bash
apt update
apt install apache2 mariadb-server php php-mysql php-mbstring php-zip php-xml php-curl curl sudo
```

## 2. Installation automatique recommandée

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/install.sh | sudo bash
```

L'installateur configure automatiquement :

- Apache ;
- MariaDB ;
- PHP ;
- la base de données TicketFlow ;
- le compte SQL applicatif ;
- `config/config.php` ;
- le VirtualHost Apache ;
- les permissions ;
- les limites PHP utiles aux pièces jointes ;
- le cron TicketFlow ;
- l'assistant sécurisé du premier Administrateur.

## 3. Premier Administrateur

À la fin de l'installation, une URL unique est affichée :

```text
http://IP_DU_SERVEUR/setup.php?token=...
```

Ouvrez cette adresse depuis votre navigateur, complétez l'assistant puis créez le premier compte Administrateur.

Après validation :

- le jeton d'installation est invalidé ;
- `storage/installed.lock` est créé ;
- l'assistant ne peut plus être utilisé comme lors d'une première installation.

## 4. Connexion

```text
http://IP_DU_SERVEUR/login.php
```

## 5. Vérifications

```bash
cd /var/www/ticketflow
php scripts/healthcheck.php
php scripts/security_audit.php
php scripts/route_check.php
php scripts/release_check.php
```

## 6. Installation manuelle

L'installation manuelle reste possible mais n'est pas recommandée pour un déploiement standard. Elle nécessite au minimum :

1. création de la base MariaDB/MySQL ;
2. import de `database/schema.sql` ;
3. création de `config/config.php` à partir de `config/config.example.php` ;
4. permissions sur `storage/` ;
5. VirtualHost Apache pointant vers `public/` ;
6. mise en place du cron.

Utilisez de préférence `install.sh` afin d'éviter une divergence de configuration avec les installations supportées.
