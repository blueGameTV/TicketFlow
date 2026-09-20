#!/usr/bin/env bash
set -Eeuo pipefail

APP_DIR="${TICKETFLOW_DIR:-/var/www/ticketflow}"
BACKUP_ROOT="${TICKETFLOW_BACKUP_DIR:-/var/backups/ticketflow}"
BRANCH="${TICKETFLOW_BRANCH:-main}"

say() { printf '\n\033[1;34m[TicketFlow]\033[0m %s\n' "$*"; }
ok()  { printf '\033[1;32m[OK]\033[0m %s\n' "$*"; }
warn(){ printf '\033[1;33m[WARN]\033[0m %s\n' "$*"; }
fail(){ printf '\033[1;31m[ERREUR]\033[0m %s\n' "$*" >&2; exit 1; }

[[ ${EUID:-$(id -u)} -eq 0 ]] || fail "La mise à jour doit être exécutée avec sudo/root."
[[ -d "$APP_DIR/.git" ]] || fail "$APP_DIR n'est pas une installation Git de TicketFlow."
[[ -f "$APP_DIR/config/config.php" ]] || fail "config/config.php est introuvable."
[[ -f "$APP_DIR/VERSION" ]] || fail "Le fichier VERSION est introuvable."

for cmd in git php mysqldump tar; do
  command -v "$cmd" >/dev/null 2>&1 || fail "Commande requise absente : $cmd"
done

OLD_VERSION="$(tr -d '[:space:]' < "$APP_DIR/VERSION")"
OLD_COMMIT="$(git -C "$APP_DIR" rev-parse HEAD)"
TIMESTAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP_DIR="$BACKUP_ROOT/$TIMESTAMP-v$OLD_VERSION"

say "Préparation de la mise à jour"
printf 'Updater            : 1.1.0-r2\n'
printf 'Version installée : %s\n' "$OLD_VERSION"
printf 'Branche cible      : %s\n' "$BRANCH"

# Les installations v1.0.0 ont pu changer uniquement le bit exécutable des
# fichiers .gitkeep de storage. TicketFlow ne versionne pas les permissions
# runtime : on désactive donc la détection du file mode dans ce dépôt.
git -C "$APP_DIR" config core.fileMode false

# Les anciennes installations v1.0.0 pouvaient rendre exécutables les fichiers
# storage/**/.gitkeep via "chmod -R 770". Git considère alors ces changements
# de mode comme des modifications locales. Ces fichiers sont uniquement des
# placeholders de répertoires runtime : on peut les restaurer sans toucher aux
# données réelles (logs, uploads, cache ou sessions).
SAFE_RUNTIME_PLACEHOLDERS=(
  "storage/cache/.gitkeep"
  "storage/logs/.gitkeep"
  "storage/sessions/.gitkeep"
  "storage/uploads/.gitkeep"
  "storage/uploads/avatars/.gitkeep"
)

for placeholder in "${SAFE_RUNTIME_PLACEHOLDERS[@]}"; do
  if git -C "$APP_DIR" ls-files --error-unmatch "$placeholder" >/dev/null 2>&1; then
    if ! git -C "$APP_DIR" diff --quiet -- "$placeholder" || ! git -C "$APP_DIR" diff --cached --quiet -- "$placeholder"; then
      warn "Réparation automatique du placeholder runtime : $placeholder"
      git -C "$APP_DIR" checkout -- "$placeholder"
    fi
  fi
done

TRACKED_CHANGES="$(git -C "$APP_DIR" status --porcelain --untracked-files=no)"
if [[ -n "$TRACKED_CHANGES" ]]; then
  printf '%s\n' "$TRACKED_CHANGES"
  fail "Des fichiers applicatifs suivis ont été modifiés localement. La mise à jour est arrêtée pour éviter d'écraser vos personnalisations."
fi

mkdir -p "$BACKUP_DIR"

say "Sauvegarde avant mise à jour"
cp -a "$APP_DIR/config/config.php" "$BACKUP_DIR/config.php"
printf '%s\n' "$OLD_VERSION" > "$BACKUP_DIR/VERSION"
printf '%s\n' "$OLD_COMMIT" > "$BACKUP_DIR/git-commit.txt"

if [[ -d "$APP_DIR/storage/uploads" ]]; then
  tar -C "$APP_DIR/storage" -czf "$BACKUP_DIR/uploads.tar.gz" uploads
fi

mapfile -t DB_VALUES < <(php -r '
$c = require $argv[1];
$db = $c["database"] ?? [];
foreach (["host","port","name","user","password"] as $key) {
    echo (string)($db[$key] ?? "") . PHP_EOL;
}
' "$APP_DIR/config/config.php")

DB_HOST="${DB_VALUES[0]:-127.0.0.1}"
DB_PORT="${DB_VALUES[1]:-3306}"
DB_NAME="${DB_VALUES[2]:-ticketflow}"
DB_USER="${DB_VALUES[3]:-}"
DB_PASS="${DB_VALUES[4]:-}"

[[ -n "$DB_USER" && -n "$DB_NAME" ]] || fail "Configuration MariaDB incomplète."

MYSQL_PWD="$DB_PASS" mysqldump   --single-transaction   --routines   --triggers   --events   -h "$DB_HOST" -P "$DB_PORT" -u "$DB_USER" "$DB_NAME"   > "$BACKUP_DIR/database.sql"

chmod 600 "$BACKUP_DIR/config.php" "$BACKUP_DIR/database.sql"
ok "Sauvegarde créée : $BACKUP_DIR"

say "Récupération de la dernière version stable"
git -C "$APP_DIR" fetch origin "$BRANCH"
git -C "$APP_DIR" checkout "$BRANCH"
git -C "$APP_DIR" pull --ff-only origin "$BRANCH"

NEW_VERSION="$(tr -d '[:space:]' < "$APP_DIR/VERSION")"
ok "Code mis à jour : v$OLD_VERSION → v$NEW_VERSION"

needs_v110="$(
  php -r '
  $old=$argv[1]; $new=$argv[2];
  echo (version_compare($old,"1.1.0","<") && version_compare($new,"1.1.0",">=")) ? "1" : "0";
  ' "$OLD_VERSION" "$NEW_VERSION"
)"

if [[ "$needs_v110" == "1" ]]; then
  say "Migration base de données vers v1.1.0"
  php "$APP_DIR/scripts/upgrade_v110.php"
fi

say "Permissions"
mkdir -p "$APP_DIR/storage/logs" "$APP_DIR/storage/uploads" "$APP_DIR/storage/cache" "$APP_DIR/storage/sessions"
chown -R www-data:www-data "$APP_DIR/storage"
# Répertoires exécutables/traversables, fichiers non exécutables.
# Cela évite de recréer le problème Git des anciens .gitkeep en mode 770.
find "$APP_DIR/storage" -type d -exec chmod 770 {} +
find "$APP_DIR/storage" -type f -exec chmod 660 {} +
chown root:www-data "$APP_DIR/config/config.php"
chmod 640 "$APP_DIR/config/config.php"
ok "Permissions vérifiées."

say "Contrôles après mise à jour"
php "$APP_DIR/scripts/healthcheck.php"
php "$APP_DIR/scripts/route_check.php"

if command -v apache2ctl >/dev/null 2>&1; then
  apache2ctl configtest
fi
if command -v systemctl >/dev/null 2>&1 && systemctl is-active --quiet apache2; then
  systemctl reload apache2
fi

printf '\n\033[1;32m============================================================\033[0m\n'
printf '\033[1;32m TicketFlow a été mis à jour avec succès vers v%s.\033[0m\n' "$NEW_VERSION"
printf '\033[1;32m============================================================\033[0m\n'
printf 'Sauvegarde de sécurité : %s\n' "$BACKUP_DIR"
printf 'Configuration, base de données et pièces jointes ont été conservées.\n'
