# Récupération de mot de passe — v1.3.0

## Accès

Depuis la page de connexion, ouvrir le parcours « Mot de passe oublié ».

## Réinitialisation par e-mail

L'utilisateur fournit son identifiant ou son adresse e-mail. Pour un compte actif reconnu, TicketFlow invalide les anciens jetons inutilisés, crée un jeton de réinitialisation valable **30 minutes**, puis prépare un e-mail contenant le lien. Un message générique est présenté afin de ne pas révéler si un compte existe. L'envoi dépend de la configuration du service de messagerie et de sa file d'attente.

## Réinitialisation avec l'aide du Support IT

1. Un **Collaborateur** ou **Manager** actif demande une récupération avec code.
2. Un **Administrateur** ou membre **Support IT** ouvre `support-recovery.php` et examine la demande.
3. Le membre habilité **approuve** ou **refuse** la demande.
4. En cas d'approbation, un **code de six chiffres** est généré. Sa durée de validité est de **15 minutes**. Il faut le transmettre au demandeur par un canal d'identité vérifiée.
5. Après vérification du code, l'utilisateur définit son nouveau mot de passe via `reset-password-code.php`.

Une récupération terminée révoque les sessions actives et invalide les jetons de réinitialisation antérieurs. Les opérations importantes sont journalisées. Les demandes récentes sont suivies via la nouvelle table `password_recovery_requests`.

### Restriction de rôles

Le parcours par code Support est destiné aux **Collaborateurs** et **Managers**. Les opérations de traitement sont réservées aux rôles **Administrateur** et **IT** (désigné « Support IT » dans l'interface).

### Prérequis de base de données

Le fichier `database/migrations/v130_dev6_password_recovery.sql` crée `password_recovery_requests`. Dans l'état examiné, `update.sh` ne déclenche pas explicitement cette migration v1.3.0 : **ne pas considérer le parcours opérationnel sur une installation mise à jour tant que cette étape n'a pas été intégrée et testée**.

### Bonnes pratiques

Ne pas partager les codes dans des tickets publics, ne pas communiquer de jetons par capture d'écran et vérifier l'identité du demandeur selon les procédures de l'organisation. Prévoir des tests de tentative répétée, d'expiration, de refus et de permissions avant la mise en production.
