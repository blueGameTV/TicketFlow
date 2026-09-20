# Mise à jour de TicketFlow

## Sauvegarde préalable

Avant toute mise à jour, sauvegardez au minimum :

```text
config/config.php
storage/uploads/
base de données MariaDB/MySQL
```

## Mise à jour v1.0.0 vers v1.1.0

1. Placez les fichiers de TicketFlow v1.1.0 dans `/var/www/ticketflow` en conservant `config/config.php` et les données runtime.
2. Lancez la migration :

```bash
cd /var/www/ticketflow
php scripts/upgrade_v110.php
```

3. Vérifiez l'installation :

```bash
php scripts/healthcheck.php
php scripts/security_audit.php
php scripts/route_check.php
php scripts/release_check.php
```

4. Rechargez Apache :

```bash
sudo systemctl reload apache2
```

La migration v1.1.0 est conçue pour être relançable : les tables utilisent `IF NOT EXISTS` et les paramètres applicatifs ne sont pas écrasés lorsqu'ils existent déjà.
