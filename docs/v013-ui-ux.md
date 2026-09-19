# TicketFlow v0.13 — Refonte UI/UX

La v0.13 lance la refonte visuelle et structurelle de TicketFlow.

## Principales évolutions

- layout applicatif avec sidebar fixe et contenu principal ;
- navigation Font Awesome ;
- dashboard unifié pour les quatre rôles ;
- correction des espacements et des zones sticky ;
- interface responsive mobile/tablette ;
- identifiant unique pour chaque compte ;
- connexion par identifiant ou e-mail ;
- bouton « Mot de passe oublié » et workflow de jeton de réinitialisation ;
- photo de profil sécurisée avec avatar par défaut ;
- page Paramètres ;
- thème clair, sombre ou système ;
- densité confortable/compacte ;
- sidebar développée/compacte ;
- préparation de la base pour les prochaines améliorations UX.

## Migration

Importer `database/migrations/v013_ui_ux.sql` une seule fois sur une base issue de la v0.12.

Les comptes existants reçoivent automatiquement un identifiant temporaire de la forme `user<ID>`. Un Administrateur peut ensuite le modifier depuis la fiche utilisateur.

## Réinitialisation du mot de passe

La v0.13 implémente la génération et la validation des jetons de réinitialisation. En environnement `development`, le lien est affiché directement pour permettre les tests. L'envoi réel par e-mail est prévu pour la v0.14.

## v0.13.1 - Correctifs UI/UX

- Le bloc utilisateur redondant a été retiré de la barre supérieure (profil conservé dans la sidebar).
- Dashboard IT réparé et enrichi avec les compteurs Mes tickets, Non attribués, Validations et Équipe IT.
- Les informations SLA sont désormais réservées aux Administrateurs, y compris les alertes SLA.
- Groupe et Manager du demandeur sont visibles uniquement par Administrateur et IT.
- Ouvrir une notification la marque automatiquement comme lue.
- Suppression manuelle des notifications disponible pour chaque utilisateur.
- Les notifications lues depuis plus de 24 heures sont nettoyées automatiquement.
- Suppression d'un groupe disponible ; les membres du groupe deviennent automatiquement sans groupe grâce à la clé étrangère ON DELETE SET NULL.
- Refonte du formulaire d'administration utilisateur en trois blocs : Identité, Organisation et Sécurité.
- Pagination des listes de tickets à 15 éléments par page avec conservation des filtres.
