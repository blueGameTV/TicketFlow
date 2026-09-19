# Contribuer à TicketFlow

Merci de contribuer à **TicketFlow**.

Ce document décrit la méthode recommandée pour proposer une correction, une amélioration ou de la documentation sur la branche stable **v1.0.x**.

## Avant de commencer

Avant toute modification :

1. vérifiez qu'une Issue similaire n'existe pas déjà ;
2. pour un bug, indiquez la version de TicketFlow, l'OS, PHP, Apache et MariaDB/MySQL ;
3. pour une nouvelle fonctionnalité importante, ouvrez d'abord une Issue afin de discuter du besoin ;
4. ne publiez jamais de secret, mot de passe, jeton, dump de production ou pièce jointe utilisateur.

Les vulnérabilités de sécurité ne doivent pas être signalées dans une Issue publique. Consultez [SECURITY.md](SECURITY.md).

## Workflow Git

Ne travaillez pas directement sur `main`.

Créez une branche dédiée à partir de la dernière version de `main` :

```bash
git checkout main
git pull
git checkout -b feature/ma-fonctionnalite
```

Exemples de noms de branches :

```text
feature/saved-views
fix/ticket-permission
security/session-hardening
docs/installation
ui/ticket-list
```

## Types de contributions

Les contributions peuvent notamment concerner :

- corrections de bugs ;
- sécurité ;
- interface et expérience utilisateur ;
- documentation ;
- performances ;
- tests ;
- nouvelles fonctionnalités cohérentes avec TicketFlow.

Évitez les changements massifs non liés dans une seule Pull Request.

## Règles de développement

### PHP

- utiliser `declare(strict_types=1)` pour les nouveaux fichiers PHP ;
- utiliser PDO et des requêtes préparées pour toute donnée provenant d'un utilisateur ;
- vérifier les autorisations côté serveur, jamais uniquement dans l'interface ;
- protéger les actions sensibles avec CSRF ;
- échapper les données affichées dans le HTML ;
- ne jamais stocker de mot de passe en clair ;
- utiliser `password_hash()` et `password_verify()` ;
- conserver la compatibilité avec **PHP 8.1+**.

### Base de données

Toute modification du schéma doit être accompagnée d'une migration dans :

```text
database/migrations/
```

Une migration doit :

- être non destructive autant que possible ;
- être documentée ;
- fonctionner sur une installation existante ;
- ne contenir aucune donnée personnelle réelle.

### Sécurité

Toute fonctionnalité doit respecter les règles existantes :

- contrôle des rôles ;
- contrôle d'accès aux tickets ;
- CSRF ;
- validation des entrées ;
- pièces jointes hors du répertoire public ;
- aucune fuite de configuration ou de secrets.

### Interface

- conserver le style général de TicketFlow ;
- privilégier les composants CSS/JS réutilisables ;
- éviter les rechargements complets lorsqu'une interaction est déjà prévue en AJAX/fetch ;
- vérifier au minimum l'affichage desktop 1920 px et 1366 px ;
- ne pas casser le comportement mobile existant.

## Tests avant Pull Request

Avant de proposer une modification, lancez au minimum :

```bash
find public src templates scripts -name '*.php' -print0 | xargs -0 -n1 php -l

php scripts/healthcheck.php
php scripts/security_audit.php
php scripts/route_check.php
php scripts/release_check.php
```

Pour une modification importante ou avant une release :

```bash
php scripts/rc_preflight.php
```

Testez également manuellement les rôles concernés :

- Administrateur ;
- IT ;
- Manager ;
- Collaborateur.

## Commits

Utilisez des messages courts et explicites.

Exemples :

```text
feat: add ticket saved views
fix: enforce ticket ownership check
ui: improve ticket conversation layout
security: harden session validation
docs: update Debian installation guide
```

Préfixes recommandés :

```text
feat:
fix:
security:
ui:
docs:
refactor:
test:
chore:
```

## Pull Requests

Une Pull Request doit contenir :

- une description claire du changement ;
- le problème résolu ou l'Issue associée ;
- les étapes de test ;
- les éventuelles migrations SQL ;
- des captures d'écran pour un changement visuel important ;
- l'impact éventuel sur les rôles et permissions.

Avant l'envoi, vérifiez que :

- aucun secret n'est présent ;
- `config/config.php` n'est pas ajouté ;
- aucun log ou upload réel n'est ajouté ;
- les tests utiles passent ;
- la documentation est mise à jour si nécessaire.

## Compatibilité

La branche `main` correspond à la version stable courante de TicketFlow.

Les changements destinés à une future version doivent éviter de casser :

- l'installation automatique ;
- les mises à jour depuis une version stable ;
- la base existante ;
- les permissions par rôle ;
- les URLs publiques documentées.

## Licence et droits

En proposant une contribution, vous confirmez disposer du droit de soumettre le code, la documentation ou les ressources concernés au projet TicketFlow.
