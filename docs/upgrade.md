# Mise à jour de TicketFlow

TicketFlow peut être mis à jour **sans réinitialiser l'installation**. Les comptes, tickets, groupes, paramètres, pièces jointes et données MariaDB/MySQL sont conservés.

## Méthode recommandée : mise à jour automatique

Depuis une installation TicketFlow existante installée dans `/var/www/ticketflow` :

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/update.sh | sudo bash
```

Le script `update.sh` effectue automatiquement :

- vérification de l'installation Git existante ;
- contrôle des modifications locales avant toute écriture ;
- sauvegarde de `config/config.php` ;
- sauvegarde complète de la base MariaDB/MySQL ;
- sauvegarde de `storage/uploads/` ;
- récupération de la dernière version stable ;
- exécution des migrations nécessaires ;
- conservation des données et de la configuration ;
- vérification des permissions ;
- `healthcheck.php` et `route_check.php` ;
- rechargement d'Apache.

Les sauvegardes sont créées dans :

```text
/var/backups/ticketflow/
```

Le script s'arrête si des fichiers suivis Git ont été modifiés localement afin de ne pas écraser des personnalisations.

## Mise à jour v1.0.0 vers v1.1.0

Le script automatique ci-dessus prend en charge le passage de **v1.0.0 vers v1.1.0** et lance automatiquement :

```bash
php scripts/upgrade_v110.php
```

La migration ajoute :

- `service_alerts` ;
- `maintenance_windows` ;
- `user_release_views` ;
- les paramètres de maintenance dans `app_settings`.

Elle ne supprime pas les comptes, tickets, groupes, historiques ou pièces jointes existants.

## Méthode manuelle

Avant toute mise à jour manuelle, sauvegardez au minimum :

```text
config/config.php
storage/uploads/
base de données MariaDB/MySQL
```

Après avoir récupéré les fichiers v1.1.0 :

```bash
cd /var/www/ticketflow
php scripts/upgrade_v110.php
php scripts/healthcheck.php
php scripts/security_audit.php
php scripts/route_check.php
php scripts/release_check.php
sudo systemctl reload apache2
```

## Important

`install.sh` est destiné aux **nouvelles installations**.

Ne l'utilisez pas pour mettre à jour une installation existante :

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/install.sh | sudo bash
```

Pour une mise à jour, utilisez `update.sh`.
