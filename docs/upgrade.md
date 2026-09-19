# Mise à niveau de TicketFlow

## Sauvegarde obligatoire

Avant chaque mise à niveau :

```bash
mysqldump -u ticketflow_user -p ticketflow > ticketflow-$(date +%F-%H%M).sql
cp config/config.php config/config.php.backup
```

Sauvegardez aussi `storage/uploads/` si le serveur contient des pièces jointes ou avatars.

## Procédure générale

1. mettre le site en maintenance si nécessaire ;
2. sauvegarder la base et `config/config.php` ;
3. remplacer les fichiers applicatifs ;
4. conserver le vrai `config/config.php` ;
5. exécuter uniquement les migrations non encore appliquées ;
6. remettre les permissions de `storage/` ;
7. lancer les diagnostics.

```bash
php scripts/healthcheck.php
php scripts/security_audit.php
php scripts/release_check.php
```

## Migrations historiques

Les installations neuves utilisent directement `database/schema.sql`.

Pour les anciennes installations, les migrations sont dans `database/migrations/` et doivent être appliquées dans l'ordre correspondant aux versions déjà installées.

## v0.16 -> v0.17

Aucune migration SQL n'est nécessaire.
