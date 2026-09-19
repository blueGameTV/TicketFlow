#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${TICKETFLOW_DIR:-/var/www/ticketflow}"
REPO_URL="${TICKETFLOW_REPO:-https://github.com/blueGameTV/TicketFlow.git}"
DB_NAME="ticketflow"
DB_USER="ticketflow_user"
APACHE_SITE="ticketflow.conf"

say() { printf '\n\033[1;34m[TicketFlow]\033[0m %s\n' "$*"; }
ok()  { printf '\033[1;32m[OK]\033[0m %s\n' "$*"; }
fail(){ printf '\033[1;31m[ERREUR]\033[0m %s\n' "$*" >&2; exit 1; }

[[ ${EUID:-$(id -u)} -eq 0 ]] || fail "L'installateur doit être exécuté avec sudo/root."
command -v apt-get >/dev/null 2>&1 || fail "Cette installation automatique cible Debian/Ubuntu (apt)."

say "Installation des prérequis"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y apache2 mariadb-server git curl openssl \
  php php-cli libapache2-mod-php php-mysql php-mbstring php-xml php-curl php-zip
systemctl enable --now apache2 mariadb
ok "Apache, MariaDB et PHP sont prêts."

say "Récupération de TicketFlow"
if [[ -d "$APP_DIR/.git" ]]; then
  git -C "$APP_DIR" pull --ff-only
elif [[ -e "$APP_DIR" && -n "$(ls -A "$APP_DIR" 2>/dev/null || true)" ]]; then
  fail "$APP_DIR existe déjà et n'est pas un dépôt Git vide. Déplacez/supprimez ce dossier puis relancez l'installation."
else
  rm -rf "$APP_DIR"
  git clone "$REPO_URL" "$APP_DIR"
fi
ok "Code installé dans $APP_DIR."

DB_PASSWORD="$(openssl rand -hex 24)"
SETUP_TOKEN="$(openssl rand -hex 24)"
SERVER_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
[[ -n "$SERVER_IP" ]] || SERVER_IP="127.0.0.1"
BASE_URL="http://$SERVER_IP"

say "Création de la base de données"
mysql --protocol=socket -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

if ! mysql -N -B -uroot -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name='roles'" | grep -qx '1'; then
  mysql -uroot "$DB_NAME" < "$APP_DIR/database/schema.sql"
fi
ok "Base de données initialisée."

say "Génération de la configuration"
cat > "$APP_DIR/config/config.php" <<PHP
<?php
return [
    'app' => [
        'name' => 'TicketFlow',
        'base_url' => '${BASE_URL}',
        'environment' => 'production',
    ],
    'mail' => [
        'transport' => 'log',
        'host' => '127.0.0.1',
        'port' => 1025,
        'encryption' => 'none',
        'username' => '',
        'password' => '',
        'from_email' => 'ticketflow@example.local',
        'from_name' => 'TicketFlow',
        'timeout' => 15,
    ],
    'uploads' => [
        'max_file_size' => 10 * 1024 * 1024,
        'max_files_per_upload' => 5,
    ],
    'database' => [
        'host' => '127.0.0.1',
        'port' => 3306,
        'name' => '${DB_NAME}',
        'user' => '${DB_USER}',
        'password' => '${DB_PASSWORD}',
        'charset' => 'utf8mb4',
    ],
];
PHP

mkdir -p "$APP_DIR/storage/logs" "$APP_DIR/storage/uploads" "$APP_DIR/storage/cache" "$APP_DIR/storage/sessions"
printf '%s\n' "$SETUP_TOKEN" > "$APP_DIR/storage/install-token"
chown -R www-data:www-data "$APP_DIR/storage"
chmod -R 770 "$APP_DIR/storage"
chown root:www-data "$APP_DIR/config/config.php"
chmod 640 "$APP_DIR/config/config.php"
ok "Configuration locale créée."

say "Configuration PHP"
PHP_VERSION_SHORT="$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')"
PHP_TICKETFLOW_INI_CONTENT=
cat > "/etc/apache2/sites-available/$APACHE_SITE" <<APACHE
<VirtualHost *:80>
    ServerName ${SERVER_IP}
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/ticketflow-error.log
    CustomLog \${APACHE_LOG_DIR}/ticketflow-access.log combined
</VirtualHost>
APACHE

cat > /etc/apache2/conf-available/ticketflow-servername.conf <<APACHEGLOBAL
ServerName ${SERVER_IP}
APACHEGLOBAL

a2enmod rewrite headers >/dev/null
a2enconf ticketflow-servername >/dev/null 2>&1 || true
a2dissite 000-default >/dev/null 2>&1 || true
a2ensite "$APACHE_SITE" >/dev/null
rm -f /var/www/html/index.html
apache2ctl configtest
systemctl restart apache2
ok "Apache pointe maintenant vers TicketFlow et le site Debian par défaut est désactivé."

say "Activation des tâches automatiques"
cat > /etc/cron.d/ticketflow <<CRON
*/5 * * * * www-data /usr/bin/php ${APP_DIR}/scripts/cron/run.php >> ${APP_DIR}/storage/logs/cron.log 2>&1
CRON
chmod 644 /etc/cron.d/ticketflow
ok "Cron TicketFlow activé toutes les 5 minutes."

say "Vérification finale"
php "$APP_DIR/scripts/healthcheck.php" || true

printf '\n\033[1;32m============================================================\033[0m\n'
printf '\033[1;32m TicketFlow est installé.\033[0m\n'
printf '\033[1;32m============================================================\033[0m\n\n'
printf 'Ouvrez cette adresse dans votre navigateur pour créer le premier administrateur :\n\n'
printf '  \033[1;36m%s/setup.php?token=%s\033[0m\n\n' "$BASE_URL" "$SETUP_TOKEN"
printf 'Après la création du compte, l\x27assistant sera automatiquement verrouillé.\n'
upload_max_filesize = 10M\npost_max_size = 64M\nmax_file_uploads = 10\nmemory_limit = 256M\n'
for PHP_SAPI_DIR in apache2 cli; do
  PHP_CONF_DIR="/etc/php/${PHP_VERSION_SHORT}/${PHP_SAPI_DIR}/conf.d"
  if [[ -d "$PHP_CONF_DIR" ]]; then
    printf '%s' "$PHP_TICKETFLOW_INI_CONTENT" > "$PHP_CONF_DIR/99-ticketflow.ini"
  fi
done
ok "Limites PHP configurées pour les pièces jointes TicketFlow."

say "Configuration d'Apache"
cat > "/etc/apache2/sites-available/$APACHE_SITE" <<APACHE
<VirtualHost *:80>
    ServerName ${SERVER_IP}
    DocumentRoot ${APP_DIR}/public

    <Directory ${APP_DIR}/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
    </Directory>

    ErrorLog \${APACHE_LOG_DIR}/ticketflow-error.log
    CustomLog \${APACHE_LOG_DIR}/ticketflow-access.log combined
</VirtualHost>
APACHE

a2enmod rewrite headers >/dev/null
a2dissite 000-default >/dev/null 2>&1 || true
a2ensite "$APACHE_SITE" >/dev/null
apache2ctl configtest
systemctl reload apache2
ok "Apache pointe maintenant vers TicketFlow."

say "Activation des tâches automatiques"
cat > /etc/cron.d/ticketflow <<CRON
*/5 * * * * www-data /usr/bin/php ${APP_DIR}/scripts/cron/run.php >> ${APP_DIR}/storage/logs/cron.log 2>&1
CRON
chmod 644 /etc/cron.d/ticketflow
ok "Cron TicketFlow activé toutes les 5 minutes."

say "Vérification finale"
php "$APP_DIR/scripts/healthcheck.php" || true

printf '\n\033[1;32m============================================================\033[0m\n'
printf '\033[1;32m TicketFlow est installé.\033[0m\n'
printf '\033[1;32m============================================================\033[0m\n\n'
printf 'Ouvrez cette adresse dans votre navigateur pour créer le premier administrateur :\n\n'
printf '  \033[1;36m%s/setup.php?token=%s\033[0m\n\n' "$BASE_URL" "$SETUP_TOKEN"
printf 'Après la création du compte, l\x27assistant sera automatiquement verrouillé.\n'
