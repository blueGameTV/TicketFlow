# TicketFlow v0.9.0-rc1 — Notes de release

La v0.9 est une Release Candidate. Elle gèle les grosses fonctionnalités pour concentrer les efforts sur la validation, la sécurité, les tests et la préparation de la v1.0.

## Nouveautés de cette version

- nouveau contrôle `scripts/route_check.php` des pages publiques et API critiques ;
- nouveau préflight `scripts/rc_preflight.php` qui enchaîne les contrôles techniques de release ;
- checklist fonctionnelle complète par rôle ;
- documentation de validation de pré-production ;
- version centralisée `0.9.0-rc1` ;
- contrôle de release étendu aux fichiers de la Release Candidate.

## Base de données

Aucune migration SQL supplémentaire n'est requise depuis v0.17.0.

## Objectif

Si la checklist RC est validée sans anomalie bloquante, la prochaine étape peut être la v1.0.0 stable.
