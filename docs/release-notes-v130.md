# TicketFlow v1.3.0 — Notes de version

> État du dépôt au 21 octobre 2026 : code de la branche `v1.3.0`. La publication stable et les tests d'installation/mise à jour doivent encore être vérifiés. Le fichier `VERSION` indique actuellement `1.2.0`.

## Interface et expérience utilisateur

- Nouvelle présentation de la page de connexion, avec meilleure adaptation aux thèmes clair et sombre.
- Consolidation des styles précédemment répartis entre `v013.css`, `v110.css` et `v120.css` dans `public/assets/css/app.css`.
- Ajustements des pages de tickets, de configuration, de comptes et des éléments de navigation.
- Enrichissement des traductions françaises et anglaises (`lang/fr.php`, `lang/en.php`).

## Récupération de mot de passe

- Parcours de réinitialisation par courriel via `forgot-password.php`.
- Nouveau parcours assisté par Support IT : un Collaborateur ou Manager peut demander un code ; un Administrateur ou membre IT examine la demande et peut l'approuver ou la refuser.
- Code à six chiffres valable **15 minutes**, affiché lors de son émission au membre du support autorisé.
- Réinitialisation sur `reset-password-code.php` après vérification, avec invalidation des sessions actives et des anciens jetons de réinitialisation.
- Nouvelle interface `support-recovery.php` et flux `api/support-recovery-feed.php`.
- Nouvelle table `password_recovery_requests` dans `database/migrations/v130_dev6_password_recovery.sql`.

## Administration et diagnostic

- Refonte de l'interface de maintenance avec informations sur la maintenance active et gestion des interventions.
- Nouvelle page `error-codes.php` réservée aux rôles Administrateur et IT pour consulter le catalogue de codes d'erreur.
- Adaptations des scripts JavaScript de recherche, d'interface et de mise à jour de l'état système.

## Vérifications à réaliser avant une publication stable

- [ ] Faire passer `VERSION` à `1.3.0` après validation.
- [ ] Vérifier que `install.sh` installe le schéma nécessaire à la récupération assistée.
- [ ] Intégrer et tester la migration v1.3.0 dans `update.sh` pour les installations existantes.
- [ ] Tester une installation vierge et une mise à niveau conservant les données.
- [ ] Exécuter `php scripts/healthcheck.php`, `php scripts/security_audit.php`, `php scripts/route_check.php` et `php scripts/release_check.php`.
- [ ] Tester les parcours de récupération, les autorisations, les notifications e-mail et les thèmes.
- [ ] Contrôler l'absence de secrets ou de données personnelles dans les commits.

Aucun de ces contrôles n'est déclaré réussi uniquement sur la base de la présence du code dans GitHub.
