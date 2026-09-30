# Mise à jour de TicketFlow

## Commande officielle

Pour une installation existante provenant de **v1.0.0**, **v1.1.0** ou d'une préversion v1.2, utilisez uniquement :

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/update.sh | sudo bash
```

Le script détecte la version installée et applique automatiquement les migrations manquantes dans le bon ordre.

## Ce qui est conservé

La mise à jour ne réinstalle pas TicketFlow et conserve :

- `config/config.php` ;
- la base MariaDB/MySQL ;
- les utilisateurs, groupes et tickets ;
- les préférences et paramètres ;
- `storage/uploads/` et les avatars.

Une sauvegarde est créée automatiquement sous `/var/backups/ticketflow/` avant toute modification.

## Chemins de migration pris en charge

### v1.0.0 → v1.2.0

Le script applique successivement :

1. la migration v1.1.0 (alertes de service, maintenance et journal des nouveautés) ;
2. la migration v1.2.0 (langue utilisateur et validation Manager à deux niveaux).

### v1.1.0 → v1.2.0

Seule la migration v1.2.0 est appliquée.

### Préversion v1.2.x-dev → v1.2.0

La migration v1.2.0 est idempotente et complète uniquement les éléments encore manquants.

## Contrôles automatiques

Après la mise à jour, le script exécute :

```bash
php scripts/healthcheck.php
php scripts/route_check.php
apache2ctl configtest
```

Puis Apache est rechargé automatiquement lorsqu'il est actif.

## Sécurité en cas d'erreur

Si un fichier applicatif suivi par Git a été modifié manuellement, `update.sh` s'arrête avant le téléchargement de la nouvelle version afin d'éviter d'écraser une personnalisation locale.

La sauvegarde créée avant mise à jour contient notamment :

```text
config.php
database.sql
uploads.tar.gz
VERSION
git-commit.txt
```

## Nouvelle installation

Pour une nouvelle VM, n'utilisez pas `update.sh`. Utilisez :

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/install.sh | sudo bash
```
