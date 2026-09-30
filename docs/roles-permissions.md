# Rôles et permissions

TicketFlow possède quatre rôles fonctionnels.

## Administrateur

L'Administrateur peut notamment :

- gérer les utilisateurs ;
- créer, modifier, désactiver et supprimer les groupes selon les règles applicables ;
- consulter tous les tickets ;
- accéder aux archives ;
- importer des utilisateurs CSV / Excel ;
- générer les extractions ;
- consulter les statistiques ;
- consulter et vider le journal d'audit ;
- gérer la configuration ;
- gérer les alertes de service ;
- gérer les maintenances ;
- consulter les informations SLA.

Les SLA sont réservés à l'Administrateur dans l'interface.

## Support IT

Le Support IT peut :

- consulter les tickets autorisés ;
- prendre en charge un ticket ;
- modifier son statut et son importance ;
- communiquer avec le demandeur ;
- ajouter des notes internes ;
- transférer ou assigner selon les règles ;
- demander une validation Manager ;
- sélectionner le Manager de validation finale ;
- proposer une résolution ;
- suivre les tickets des autres membres Support IT lorsque nécessaire.

Le rôle est affiché **Support IT**, mais sa clé interne historique reste `IT`.

## Manager

Le Manager peut :

- créer ses propres tickets ;
- consulter ses tickets actifs ;
- intervenir lorsqu'une validation lui est adressée ;
- valider ;
- demander des informations ;
- refuser.

Dans le workflow à deux niveaux, le Manager N+1 du demandeur intervient avant le Manager sélectionné pour la décision finale.

Les restrictions de message et de pièces jointes pendant une phase de validation sont appliquées côté serveur.

## Collaborateur

Le Collaborateur peut :

- créer un ticket ;
- consulter ses tickets actifs ;
- échanger avec le Support IT ;
- ajouter les pièces jointes autorisées ;
- confirmer ou refuser une proposition de résolution.

## Archives

Les tickets résolus, fermés ou annulés ne restent pas dans les files actives des Collaborateurs et Managers. Ils restent accessibles aux rôles autorisés dans les archives.

## Principe de sécurité

Masquer un bouton ne constitue jamais une permission. Les contrôles de rôle et de propriété du ticket sont toujours exécutés côté serveur.
