## v0.9.0-rc1 — Release Candidate

- gel des grosses fonctionnalités avant la v1.0 ;
- ajout de `scripts/route_check.php` pour vérifier les pages/API/ressources critiques ;
- ajout de `scripts/rc_preflight.php` pour enchaîner healthcheck, audit sécurité, routes et contrôle de release ;
- ajout d'une checklist fonctionnelle complète par rôle ;
- ajout des notes de Release Candidate ;
- contrôle de release étendu aux nouveaux fichiers RC ;
- aucune migration SQL supplémentaire.

## v0.17.0 — Documentation, installation et préparation GitHub

- README entièrement remis à jour ;
- guide d’installation Debian/Apache/MySQL ;
- guide de déploiement Apache ;
- guide de mise à niveau ;
- documentation des rôles et du workflow ;
- SECURITY.md et CONTRIBUTING.md ;
- modèles GitHub pour bugs, fonctionnalités et pull requests ;
- exemple de VirtualHost Apache ;
- script `prepare_installation.sh` ;
- nouveau contrôle `release_check.php` ;
- `.gitignore` renforcé pour limiter les fuites de secrets et données runtime.

## v0.16.0 — stabilisation et durcissement sécurité

- ajout d’en-têtes HTTP de sécurité (CSP, X-Content-Type-Options, Referrer-Policy, Permissions-Policy, anti-framing) ;
- configuration PHP des erreurs centralisée dans `storage/logs/php-error.log` ;
- sessions PHP durcies (`use_strict_mode`, cookies uniquement, pas d’identifiant en URL) ;
- révocation effective des sessions lorsque le compte utilisateur est désactivé ;
- nettoyage automatique des anciennes tentatives de connexion, sessions et jetons de réinitialisation ;
- nouveau script `scripts/security_audit.php` pour un audit non destructif ;
- nouveau script `scripts/maintenance_cleanup.php` ;
- diagnostic `healthcheck.php` enrichi pour la v0.16.

## v0.15.2.1 — correctif visuel actions multiples

- correction du cache navigateur sur les fichiers CSS/JavaScript ;
- correction de l’affichage de la barre d’actions multiples ;
- correction du positionnement des cartes tickets et du bouton **Ouvrir** ;
- ajout d’un versionnage des ressources statiques pour éviter l’utilisation d’un ancien CSS après une mise à jour.

## v0.15.2 — actions multiples sur les tickets

- sélection de plusieurs tickets depuis la file IT/Administrateur ;
- sélection de toute la page en un clic ;
- modification groupée de l’importance ;
- modification groupée du statut, selon les droits du rôle ;
- assignation/transfert groupé vers un technicien IT ;
- contrôles serveur sur les tickets sélectionnés et les droits IT ;
- retour détaillé du nombre de tickets modifiés ou ignorés.

## v0.15.1 — vues enregistrées

- ajout des vues de tickets enregistrées pour IT et Administrateur ;
- sauvegarde d’une combinaison de filtres sous un nom personnalisé ;
- rappel d’une vue en un clic ;
- suppression individuelle d’une vue ;
- limite de 12 vues par utilisateur ;
- conservation des droits et restrictions de rôle lors du rappel d’une vue.

## v0.15.0 — recherche globale et filtres avancés

- ajout d’une recherche globale accessible depuis la barre supérieure ;
- recherche instantanée des tickets, utilisateurs et groupes selon les droits ;
- raccourci `Ctrl+K` pour ouvrir rapidement la recherche ;
- ajout de filtres avancés dans la file IT/Admin : type, catégorie, groupe, Manager, IT assigné, dates et SLA ;
- choix de 15, 30 ou 50 tickets par page ;
- conservation de tous les filtres dans l’URL et pendant la pagination ;
- affichage de la catégorie directement dans la file des tickets ;
- préparation de la base UI pour les vues enregistrées et les prochaines fonctions de productivité.

## v0.14.0 — e-mails, préférences et automatisations

- ajout d’une file d’e-mails interne ;
- transport SMTP autonome ou mode `log` pour les tests ;
- préférences e-mail par utilisateur ;
- page Administration → Configuration ;
- rappels automatiques des validations Manager ;
- rappels de confirmation de résolution ;
- fermeture automatique optionnelle ;
- résumé quotidien optionnel pour IT/Admin ;
- tâche cron centralisée ;
- nouveau diagnostic v0.14.

## v0.13.6 — conversation ticket, groupes et connexion

- refonte de la conversation ticket en style messagerie, avec bulles et meilleur confort visuel ;
- nouveau message Manager dans le contexte de validation ;
- panneau de réponse ticket revu ;
- bouton **Créer un groupe** déplacé dans l’entête de la page Groupes ;
- interface de connexion enrichie avec une illustration bureautique / IT ;
- ajustements visuels complémentaires sur la page ticket.

## v0.13.5 — harmonisation des pages et UX

- pièces jointes masquées au Manager lorsqu’il traite une validation ;
- page **Validations** harmonisée avec le style de **Mes tickets** ;
- refonte de **Créer un ticket**, **Utilisateurs**, **Groupes**, **Audit**, **Paramètres** et **Notifications** ;
- enrichissement de la page Paramètres avec résumé du compte et accès rapides ;
- cartes, icônes et états vides harmonisés avec les couleurs de rôle.


## v0.13.4 — ajustements UI/UX et validation Manager

- repositionnement du bouton **Ouvrir** dans la file des tickets ;
- amélioration de la mise en page de **Mes tickets** pour Collaborateur et Manager ;
- amélioration légère du style de **Validations Manager** ;
- amélioration du style de **Modifier un groupe**, **Extractions Excel** et **Statistiques** ;
- note de limite mieux intégrée dans les exports ;
- le Manager ne peut plus envoyer de message lors d'une demande de validation (la zone est masquée) ;
- amélioration visuelle des panneaux d'interaction sur un ticket.

# Changelog

## 0.13.3
- Autorise explicitement le Manager sollicité pour une validation à échanger et joindre des fichiers sur le ticket.
- Corrige les recherches PDO déclenchées par Entrée en utilisant des paramètres nommés uniques.
- Refonte visuelle de la file des tickets avec icônes, métadonnées, badges et cartes de ligne.
- Ajoute des badges de rôles colorés et iconés.
- Retouches UI des Extractions Excel, Statistiques et formulaires Groupe.

## v0.13.2
- Historique des tickets masqué pour Collaborateur et Manager.
- Notifications du ticket courant automatiquement considérées comme lues.
- Bouton de suppression de toutes les notifications.
- Tickets résolus/fermés/annulés retirés de « Mes tickets » Collaborateur/Manager.
- Nouvelle vue Archives pour IT et Administrateur.
- Bouton Administrateur pour vider le journal d’audit.
- Retouches UI des groupes, exports et statistiques.
- Couleur d’accent différente selon le rôle.

## v0.13.1 - Correctifs UI/UX et pagination

- Dashboard IT réparé et réactivé.
- Suppression du profil redondant dans la barre supérieure.
- SLA visible uniquement pour les Administrateurs.
- Groupe et Manager visibles uniquement pour Administrateur et IT sur les tickets.
- Ouverture d’une notification = notification automatiquement lue.
- Suppression manuelle des notifications.
- Purge automatique des notifications lues après 24 h.
- Suppression des groupes disponible, les membres deviennent « sans groupe ».
- Formulaire utilisateur restructuré en sections Identité / Organisation / Sécurité.
- Pagination des listes de tickets à 15 tickets par page.

# Changelog

## v0.13.0

### UI / UX
- Nouveau layout avec sidebar fixe et zone principale.
- Navigation responsive mobile.
- Font Awesome intégré à la navigation et aux actions principales.
- Dashboard unique adaptatif selon le rôle.
- Correction de plusieurs risques de chevauchement grâce à une largeur de contenu fluide et à des zones sticky recalculées.
- Préférences utilisateur : thème, densité et sidebar compacte.

### Comptes
- Identifiant unique `username` pour chaque compte.
- Connexion par identifiant ou e-mail.
- Photo de profil sécurisée, avec avatar par défaut.
- Nouvelle page Paramètres.
- Workflow « Mot de passe oublié » avec jeton expirant après 30 minutes.

### Structure
- Suppression des anciens dashboards séparés par rôle.
- Dashboard métier centralisé dans `templates/dashboard.php`.
- Nouvelle couche CSS v0.13 séparée dans `public/assets/css/v013.css`.
- Nouveau contrôleur d'interface `public/assets/js/ui.js`.

## v0.9.1-rc2

- ajout de `install.sh` pour installation Debian/Ubuntu en une commande ;
- installation automatique Apache, PHP, MariaDB et extensions ;
- création automatique de la base et de l'utilisateur MySQL ;
- génération sécurisée de `config/config.php` ;
- configuration automatique du VirtualHost Apache et du cron ;
- ajout de `public/setup.php` pour créer le premier Administrateur dans le navigateur ;
- jeton temporaire et verrouillage de l'assistant après installation.
