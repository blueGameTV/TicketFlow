# Préparer une release GitHub

## Avant le commit

```bash
php scripts/healthcheck.php
php scripts/security_audit.php
php scripts/release_check.php
```

Vérifiez ensuite :

```bash
git status
```

Les fichiers suivants ne doivent pas être suivis :

```text
config/config.php
.env
storage/logs/*
storage/uploads/*
*.sql contenant des données réelles
```

## Tag de version

Exemple :

```bash
git tag -a v0.9.0-rc1 -m "TicketFlow v0.9.0-rc1"
git push origin v0.9.0-rc1
```

## Contenu recommandé de la release

- résumé des changements ;
- procédure de mise à niveau ;
- éventuelles migrations SQL ;
- problèmes connus ;
- avertissement « préversion » avant v1.0.

## Ne pas joindre

- `config/config.php` réel ;
- base de données réelle ;
- logs ;
- uploads utilisateurs ;
- secrets SMTP ;
- mots de passe.
