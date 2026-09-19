# Contribuer à TicketFlow

## Branche de travail

Créez une branche dédiée :

```bash
git checkout -b feature/ma-fonctionnalite
```

## Style général

- PHP avec `declare(strict_types=1)` ;
- requêtes préparées pour les entrées utilisateur ;
- vérifications de rôles côté serveur ;
- protection CSRF des actions POST ;
- ne jamais stocker un mot de passe en clair ;
- composants CSS/JS réutilisables plutôt que duplication massive.

## Vérifications avant commit

```bash
find public src templates scripts -name '*.php' -print0 | xargs -0 -n1 php -l
php scripts/healthcheck.php
php scripts/release_check.php
```

## Commits

Exemples :

```text
feat: add saved ticket views
fix: prevent manager messaging during approval
ui: improve ticket conversation
security: harden PHP sessions
```
