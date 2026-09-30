# Changelog

## v1.2.0 — Stable

- thèmes clair, sombre et système par utilisateur ;
- interface FR/EN avec anglais indiqué comme bêta ;
- changement forcé du mot de passe et refonte de setup.php ;
- validation Manager à deux niveaux : N+1 puis Manager sélectionné ;
- import utilisateurs CSV/XLSX avec modèles, prévisualisation et contrôles ;
- compatibilité Excel renforcée pour imports et extractions ;
- calendriers personnalisés, conversations et pièces jointes améliorés ;
- améliorations des alertes de service, notifications et pages d’administration ;
- mise à jour automatique prise en charge depuis v1.0.0 et v1.1.0.

## v1.2.0-dev.6.2 — Correctif priorité ticket

- correction du sélecteur Importance dans la fiche ticket ;
- suppression de l'erreur `Undefined array key "code"` ;
- restauration des niveaux Faible / Normale / Haute / Critique ;
- correction du libellé du sélecteur de statut qui utilisait par erreur les traductions de priorité.

## v1.2.0-dev.6.2 — Correctif migration langue

- correction de la migration `default_language` : `app_settings` utilise `setting_key` comme clé primaire et ne possède pas de colonne `id` ;
- migration rendue entièrement idempotente ;
- la colonne `user_preferences.language` peut déjà exister sans bloquer l'installation.

## v1.2.0-dev.6.2 — Hotfix migration langue

- corrige l'installation de la migration `language` avant le chargement du header ;
- corrige le script d'application qui lançait incorrectement les scripts de migration ;
- ajoute une protection de compatibilité dans le header pour éviter une erreur fatale si la colonne n'existe pas encore.

## v1.2.0-dev.6.2 — Correctif lecture des fichiers Excel d’import

- correction de la lecture des cellules `sharedStrings` produites par Microsoft Excel ;
- compatibilité conservée avec les cellules `inlineStr` générées par TicketFlow ;
- les en-têtes `prenom`, `nom`, `identifiant`, `email` et `role` sont désormais correctement reconnus après modification/enregistrement du modèle dans Excel.

## v1.2.0-dev.6.2 — Correctif Excel / téléchargements

- génération XLSX renforcée et vérifiée avant téléchargement ;
- suppression des sorties parasites pouvant corrompre les fichiers Excel ;
- en-têtes HTTP de téléchargement centralisés et compatibles avec Excel/Chrome ;
- correctif appliqué à toutes les extractions XLSX et au modèle d’import utilisateurs ;
- script de mise à jour corrigé pour déployer `ExcelExportService.php`, `export-users.php`, `export-tickets.php` et `export-operations.php`.

## v1.2.0-dev.6.2 — Correctif import utilisateurs

- correction du script de déploiement DEV : la page `public/admin-user-import.php` est désormais réellement copiée sur la VM ;
- correction de l’erreur Apache 404 « Not Found » sur le bouton « Importer CSV / Excel » ;
- aucun changement fonctionnel supplémentaire sur le moteur d’import.

## v1.2.0-dev.6.2 — Import utilisateurs CSV / Excel

- import massif de comptes depuis CSV ou XLSX ;
- prévisualisation et validation avant création ;
- rattachement automatique aux groupes et contrôle du Manager du groupe ;
- prise en charge des rôles Administrateur, Support IT, Manager et Collaborateur ;
- détection des doublons dans TicketFlow et dans le fichier ;
- génération automatique d’un mot de passe sécurisé lorsqu’il n’est pas fourni ;
- affichage unique des identifiants/mots de passe après import ;
- modèles CSV et Excel téléchargeables ;
- journalisation des comptes créés par import.

## v1.2.0-dev.6.2 — Validation Manager à deux niveaux

- le Support IT sélectionne maintenant un Manager précis pour la validation finale ;
- la demande passe obligatoirement d’abord par le Manager N+1 du demandeur ;
- le Manager final n’est notifié qu’après accord du N+1 ;
- un refus ou une demande d’informations du N+1 arrête la chaîne avant la seconde étape ;
- historique et file des validations indiquent clairement Étape 1 (N+1) et Étape 2 (Manager sélectionné) ;
- migration de la table `manager_approvals` pour stocker l’étape, le Manager cible et le lien entre les deux validations.

## v1.2.0-dev.6.2 — Mot de passe forcé & assistant d’installation

- remplacement de la redirection de changement forcé par une popup bloquante sur le Dashboard ;
- floutage et blocage complet de l’interface jusqu’au renouvellement ;
- ancien mot de passe non demandé uniquement dans le contexte imposé par l’Administrateur ;
- validation du nouveau mot de passe côté client et côté serveur ;
- blocage serveur des autres pages tant que le changement forcé n’est pas terminé ;
- refonte complète de `setup.php` et de ses états erreur/succès ;
- amélioration des règles de sécurité du mot de passe lors de la création du premier Administrateur.

## v1.2.0-dev.6.2 — Lisibilité du badge interne

- amélioration du contraste du badge "Interne Support IT" en thème clair ;
- amélioration du contraste du badge "Interne Support IT" en thème sombre ;
- cache-busting mis à jour pour recharger immédiatement les styles.


## v1.2.0-dev.6.2 — Multilingue FR / EN

- ajout d’un système centralisé de traduction ;
- français et anglais disponibles (espagnol reporté à une version ultérieure) ;
- langue enregistrée par utilisateur dans les préférences ;
- langue par défaut configurable par l’Administrateur ;
- navigation globale, paramètres, dashboard et création de ticket traduits en première passe ;
- architecture prête pour étendre progressivement la traduction aux autres écrans et messages système.


## v1.2.0-dev.6.2 — Import Excel robuste

- remplacement du lecteur XLSX par un parseur DOM indépendant des namespaces Office Open XML ;
- prise en charge robuste des `sharedStrings`, `inlineStr`, cellules numériques et fichiers réenregistrés par Microsoft Excel ;
- détection automatique de la première feuille via les relations du classeur ;
- normalisation renforcée des en-têtes invisibles/BOM/espaces spéciaux ;
- message d’erreur enrichi avec les colonnes réellement détectées.


## v1.2.0-dev.6.2 — Correctif de chargement et finitions UI

- correction du cache-busting qui empêchait les nouveaux styles v1.2 de se charger ;
- boutons sombres avec contraste renforcé ;
- description des tickets rendue plus lisible ;
- carte « Version installée » ciblée avec la bonne classe ;
- calendrier/date-heure remplacé par un sélecteur TicketFlow personnalisé ;
- espacements de la page Alertes de service améliorés.


## v1.2.0-dev.1.2 — Finitions visuelles supplémentaires

- amélioration de la visibilité des boutons, surtout en thème sombre ;
- amélioration de la lisibilité de la description des tickets ;
- nouvelle couleur de fond pour la carte « Version installée » ;
- harmonisation visuelle des champs calendrier/date-heure ;
- amélioration des espacements sur la page Alertes de service.


## v1.2.0-dev.1.1 — Correctifs thème sombre et historique des alertes

- correction des textes devenus trop sombres/invisibles sur les statistiques ;
- correction des cartes, badges et surfaces restant trop clairs en thème sombre ;
- correction de la page À propos & Nouveautés en thème sombre ;
- amélioration du contraste des tableaux, distributions et indicateurs ;
- nouvelle présentation complète de l’historique des alertes de service ;
- couleurs des niveaux d’alerte adaptées au thème sombre.


## v1.2.0-dev.1 — Thèmes et identité Support IT

- réparation et harmonisation du thème sombre ;
- thème personnel Clair / Sombre / Système mémorisé par utilisateur ;
- aperçu immédiat du thème dans Paramètres ;
- valeur de repli « Système » pour les nouveaux profils ;
- renommage visuel du rôle IT en « Support IT » sans modifier la valeur technique `IT` ;
- libellés Support IT harmonisés dans les tickets, exports, maintenance et administration.


## v1.1.0 — Alertes, maintenance et nouveautés

- alertes de service globales visibles dans toute l'application ;
- détail complet d'une alerte au clic, avec titre limité à 80 caractères et message à 2000 caractères ;
- page À propos & Nouveautés avec version installée et historique ;
- journal des nouveautés par utilisateur ;
- mode maintenance TicketFlow avec redirection automatique des Collaborateurs et Managers ;
- accès Administrateur maintenu et accès IT configurable pendant une maintenance ;
- fin automatique du mode maintenance à l'heure estimée ;
- maintenances planifiées avec bandeau d'information ;
- extraction Excel des alertes et maintenances ;
- harmonisation des menus déroulants, champs de recherche, sélecteurs de fichiers et écrans de maintenance ;
- ajout de la migration `v110_service_alerts_maintenance.sql` et du script `upgrade_v110.php`.

## v1.0.0 — Première version stable

- première version stable de TicketFlow ;
- installation automatique Debian/Ubuntu et assistant Web sécurisé ;
- workflows Administrateur, IT, Manager et Collaborateur ;
- recherche globale, filtres avancés, vues enregistrées et actions multiples ;
- SLA, notifications, exports, statistiques, audit, e-mails et automatisations ;
- durcissement sécurité et documentation de production.

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

## 1.2.0-dev.6.2
- Corrections du thème sombre sur la conversation des tickets.
- Refonte visuelle des cartes de pièces jointes et de leur état au survol.
- Amélioration du calendrier TicketFlow et prise en charge des champs `date` en plus des champs `datetime-local`.
- Mise à jour du cache-busting front (`v120.css`, `theme-preferences.js`, `datetime-picker.js`).
