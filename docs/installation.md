# Installation de TicketFlow

Ce guide décrit une installation neuve sur Debian avec Apache, PHP et MariaDB/MySQL.

## Installation automatique recommandée

Sur une Debian/Ubuntu neuve, utilisez l'installateur officiel TicketFlow v1.1.0 :

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/install.sh | sudo bash
```

Cette commande installe et configure automatiquement les dépendances, Apache, MariaDB, PHP, TicketFlow, les permissions et le cron. Les étapes ci-dessous restent utiles pour une installation manuelle.

## 1. Paquets nécessaires

```bash
sudo apt update
sudo apt install apache2 mariadb-server php php-cli php-mysql php-mbstring php-zip
```

Vérification :

```bash
php -v
php -m | grep -E 'pdo_mysql|mbstring|fileinfo|zip'
```

## 2. Copier le projet

Exemple :

```bash
sudo mkdir -p /var/www/ticketflow
sudo chown "$USER":"$USER" /var/www/ticketflow
```

Copiez ou clonez ensuite TicketFlow dans ce dossier.

## 3. Base MySQL/MariaDB

```sql
CREATE DATABASE ticketflow CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'ticketflow_user'@'localhost' IDENTIFIED BY 'CHANGE_ME';
GRANT ALL PRIVILEGES ON ticketflow.* TO 'ticketflow_user'@'localhost';
FLUSH PRIVILEGES;
```

Import :

```bash
mysql -u ticketflow_user -p ticketflow < database/schema.sql
```

## 4. Configuration TicketFlow

```bash
cp config/config.example.php config/config.php
nano config/config.php
```

Renseignez au minimum :

```php
'database' => [
    'host' => '127.0.0.1',
    'port' => 3306,
    'name' => 'ticketflow',
    'user' => 'ticketflow_user',
    'password' => 'VOTRE_MOT_DE_PASSE',
    'charset' => 'utf8mb4',
],
```

En développement, gardez :

```php
'environment' => 'development',
```

En production :

```php
'environment' => 'production',
```

## 5. Permissions

```bash
sudo chown -R www-data:www-data storage
sudo chmod -R 770 storage
sudo chown root:www-data config/config.php
sudo chmod 640 config/config.php
```

Ne rendez jamais `config/config.php` accessible publiquement.

## 6. Apache

Copiez l'exemple fourni :

```bash
sudo cp deploy/apache/ticketflow.conf.example /etc/apache2/sites-available/ticketflow.conf
sudo nano /etc/apache2/sites-available/ticketflow.conf
```

Puis :

```bash
sudo a2enmod rewrite headers
sudo a2ensite ticketflow.conf
sudo apache2ctl configtest
sudo systemctl reload apache2
```

Le DocumentRoot doit être `/var/www/ticketflow/public`.

## 7. PHP uploads

Pour les pièces jointes de 10 Mo :

```ini
upload_max_filesize = 10M
post_max_size = 52M
max_file_uploads = 10
```

Redémarrez Apache :

```bash
sudo systemctl restart apache2
```

## 8. Premier Administrateur

```bash
php scripts/create_admin.php
```

## 9. Cron

```bash
crontab -e
```

Ajoutez :

```cron
*/5 * * * * /usr/bin/php /var/www/ticketflow/scripts/cron/run.php >> /var/www/ticketflow/storage/logs/cron.log 2>&1
```

## 10. Validation de l'installation

```bash
php scripts/healthcheck.php
php scripts/security_audit.php
php scripts/release_check.php
```

Aucune erreur bloquante ne doit être signalée.
