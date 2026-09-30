# Préparer une release GitHub

## Avant le commit

```bash
php scripts/healthcheck.php
php scripts/security_audit.php
php scripts/release_check.php
```

Vérifiez ensuite `git status`. Les fichiers suivants ne doivent pas être suivis : `config/config.php`, `.env`, les logs, les uploads utilisateurs et les dumps contenant des données réelles.

## Tag de version

Pour TicketFlow v1.2.0 :

```bash
git tag -a v1.2.0 -m "TicketFlow v1.2.0"
git push origin v1.2.0
```

## Contenu recommandé de la release

- titre : `TicketFlow v1.2.0` ;
- notes : `docs/release-notes-v120.md` ;
- archive : `TicketFlow-v1.2.0.zip` ;
- somme SHA-256 de l'archive ;
- rappel de la commande de mise à jour automatique.

## Ne pas joindre

- `config/config.php` réel ;
- base de données réelle ;
- logs ;
- uploads utilisateurs ;
- secrets SMTP ;
- mots de passe.
