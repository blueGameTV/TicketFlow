#!/usr/bin/env bash
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SITE_NAME="ticketflow"
APACHE_SITE="/etc/apache2/sites-available/${SITE_NAME}.conf"

if [[ "${EUID}" -ne 0 ]]; then
  echo "[ERREUR] Lancez l'installation avec : sudo bash install.sh"
  exit 1
fi

echo "============================================="
echo " TicketFlow v0.9.0-rc2 - Installation"
echo "============================================="
echo
echo "Ce script va configurer automatiquement Apache, PHP, MariaDB/MySQL,"
echo "les permissions TicketFlow et le fichier config/config.php."
echo

read -r -p "Nom de la base [ticketflow] : " DB_NAME
DB_NAME="${DB_NAME:-ticketflow}"
read -r -p "Utilisateur SQL [ticketflow_user] : " DB_USER
DB_USER="${DB_USER:-ticketflow_user}"
read -r -s -p "Mot de passe SQL pour TicketFlow : " DB_PASS
echo
if [[ -z "${DB_PASS}" ]]; then
  echo "[ERREUR] Le mot de passe SQL ne peut pas être vide."
  exit 1
fi

SERVER_IP="$(hostname -I 2>/dev/null | awk '{print $1}')"
DEFAULT_URL="http://${SERVER_IP:-localhost}"
read -r -p "URL de TicketFlow [${DEFAULT_URL}] : " BASE_URL
BASE_URL="${BASE_URL:-$DEFAULT_URL}"

if ! [[ "${DB_NAME}" =~ ^[A-Za-z0-9_]+$ && "${DB_USER}" =~ ^[A-Za-z0-9_]+$ ]]; then
  echo "[ERREUR] Le nom de base et l'utilisateur SQL ne doivent contenir que lettres, chiffres et _."
  exit 1
fi

echo
echo "[1/8] Installation des dépendances..."
apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y \
  apache2 mariadb-server git \
  php php-cli php-mysql php-mbstring php-zip php-xml php-curl

echo "[2/8] Préparation de la base de données..."
SQL_PASS_ESCAPED="${DB_PASS//\\/\\\\}"
SQL_PASS_ESCAPED="${SQL_PASS_ESCAPED//\'/\'\'}"
mysql --protocol=socket -uroot <<SQL
CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${SQL_PASS_ESCAPED}';
ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${SQL_PASS_ESCAPED}';
GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';
FLUSH PRIVILEGES;
SQL

mysql -u"${DB_USER}" -p"${DB_PASS}" "${DB_NAME}" < "${APP_DIR}/database/schema.sql"

echo "[3/8] Génération de config/config.php..."
php_escape() {
  local s="$1"
  s="${s//\\/\\\\}"
  s="${s//\'/\\\'}"
  printf '%s' "$s"
}

DB_PASS_PHP="$(php_escape "${DB_PASS}")"
BASE_URL_PHP="$(php_escape "${BASE_URL}")"

cat > "${APP_DIR}/config/config.php" <<PHP
<?php
return [
    'app' => [
        'name' => 'TicketFlow',
        'base_url' => '${BASE_URL_PHP}',
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
        'password' => '${DB_PASS_PHP}',
        'charset' => 'utf8mb4',
    ],
];
PHP

echo "[4/8] Préparation du stockage et des permissions..."
mkdir -p "${APP_DIR}/storage/logs" "${APP_DIR}/storage/uploads" "${APP_DIR}/storage/cache" "${APP_DIR}/storage/sessions"
chown -R www-data:www-data "${APP_DIR}/storage"
chmod -R 770 "${APP_DIR}/storage"
chown root:www-data "${APP_DIR}/config/config.php"
chmod 640 "${APP_DIR}/config/config.php"

echo "[5/8] Configuration automatique d'Apache..."
cat > "${APACHE_SITE}" <<APACHE
<VirtualHost *:80>
    ServerName _
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
a2dissite 000-default.conf >/dev/null 2>&1 || true
a2ensite "${SITE_NAME}.conf" >/dev/null
apache2ctl configtest
systemctl restart apache2

echo "[6/8] Installation du cron..."
CRON_FILE="/etc/cron.d/ticketflow"
cat > "${CRON_FILE}" <<CRON
*/5 * * * * www-data /usr/bin/php ${APP_DIR}/scripts/cron/run.php >> ${APP_DIR}/storage/logs/cron.log 2>&1
CRON
chmod 644 "${CRON_FILE}"

echo "[7/8] Diagnostic..."
cd "${APP_DIR}"
php scripts/healthcheck.php || true

echo "[8/8] Installation terminée."
echo
echo "TicketFlow devrait maintenant être accessible sur : ${BASE_URL}"
echo
echo "Dernière étape : créez le premier Administrateur avec :"
echo "  cd ${APP_DIR}"
echo "  php scripts/create_admin.php"
echo
echo "Aucune modification manuelle du code PHP ou de la configuration Apache n'est nécessaire."
