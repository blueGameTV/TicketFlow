# Notifications TicketFlow

TicketFlow possède un centre de notifications par utilisateur.

## Événements principaux

Les notifications peuvent être générées lors de :

- la création d'un nouveau ticket ;
- la prise en charge d'un ticket ;
- la modification du statut ou de l'importance ;
- un transfert ou une assignation ;
- un nouveau message ;
- une note interne pour les destinataires autorisés ;
- une demande de validation Manager ;
- une réponse du Manager ;
- le passage de la validation N+1 à la validation finale ;
- une proposition de résolution ;
- une confirmation ou un refus de résolution ;
- certains événements SLA ;
- certaines opérations de service.

## Lecture

Le compteur de la barre supérieure indique les notifications non lues.

La page `notifications.php` permet notamment :

- d'afficher toutes les notifications ;
- de filtrer les non lues ;
- d'ouvrir une notification ;
- de la marquer comme lue ;
- de supprimer les notifications autorisées.

Ouvrir une notification associée à un ticket la marque comme lue.

## Nettoyage automatique

Les notifications lues peuvent être purgées automatiquement après le délai prévu par TicketFlow. Cette tâche est exécutée par le cron principal.

## E-mails

Selon les préférences utilisateur et la configuration du serveur, certains événements peuvent également créer un e-mail dans la file TicketFlow.

Le transport peut être désactivé, journalisé en mode `log` ou configuré en SMTP.
