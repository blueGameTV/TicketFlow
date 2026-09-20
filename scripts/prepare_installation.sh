#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "[INFO] Préparation locale TicketFlow v1.1.0"

if [[ ! -f config/config.php ]]; then
  cp config/config.example.php config/config.php
  echo "[OK] config/config.php créé depuis config.example.php"
  echo "[ACTION] Modifiez maintenant config/config.php avant de lancer TicketFlow."
else
  echo "[INFO] config/config.php existe déjà : aucune modification."
fi

mkdir -p storage/logs storage/uploads
: > storage/logs/.gitkeep
: > storage/uploads/.gitkeep

echo "[OK] Dossiers storage présents"
echo
printf '%s\n' "Étapes suivantes :"
printf '%s\n' "  1. modifier config/config.php"
printf '%s\n' "  2. importer database/schema.sql pour une installation neuve"
printf '%s\n' "  3. configurer Apache avec public/ comme DocumentRoot"
printf '%s\n' "  4. exécuter php scripts/create_admin.php"
printf '%s\n' "  5. exécuter php scripts/healthcheck.php"
printf '%s\n' "  6. exécuter php scripts/security_audit.php"
printf '%s\n' "  7. exécuter php scripts/release_check.php"
