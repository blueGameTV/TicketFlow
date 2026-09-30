# Mise à jour de TicketFlow

## Commande officielle

Pour une installation existante en **v1.0.0**, **v1.1.0** ou une préversion v1.2 :

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/update.sh | sudo bash
```

Il n'est pas nécessaire de réinstaller Apache, PHP ou MariaDB.

## Données conservées

La mise à jour conserve :

- `config/config.php` ;
- la base MariaDB/MySQL ;
- les utilisateurs ;
- les groupes ;
- les tickets et leur historique ;
- les paramètres et préférences ;
- les pièces jointes et avatars.

## Sauvegarde automatique

Avant toute migration, `update.sh` crée une sauvegarde dans :

```text
/var/backups/ticketflow/
```

Elle contient notamment :

```text
config.php
database.sql
uploads.tar.gz
VERSION
git-commit.txt
```

## Chemins de migration

### v1.0.0 → v1.2.0

1. sauvegarde ;
2. récupération du code v1.2.0 ;
3. migration v1.1.0 ;
4. migration v1.2.0 ;
5. permissions et contrôles.

### v1.1.0 → v1.2.0

1. sauvegarde ;
2. récupération du code v1.2.0 ;
3. migration v1.2.0 ;
4. permissions et contrôles.

### Préversion v1.2 → v1.2.0

La migration v1.2 est conçue pour compléter les éléments manquants sans réinitialiser les données.

## Fichiers locaux modifiés

Si de vrais fichiers applicatifs suivis par Git ont été modifiés localement, la mise à jour s'arrête afin d'éviter de les écraser.

Les anciens changements de permissions sur les fichiers `storage/**/.gitkeep` sont corrigés automatiquement.

## Contrôles après mise à jour

Le script exécute notamment :

```bash
php scripts/healthcheck.php
php scripts/route_check.php
apache2ctl configtest
```

Apache est ensuite rechargé lorsqu'il est actif.

## Nouvelle installation

Pour une nouvelle VM, utilisez `install.sh` et non `update.sh` :

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/install.sh | sudo bash
```
