# Rôles et permissions

TicketFlow possède quatre rôles fonctionnels :

- **Administrateur**
- **Support IT**
- **Manager**
- **Collaborateur**

Les permissions dépendent à la fois du rôle de l'utilisateur et du **périmètre du ticket** auquel il accède.

> Les rôles **Manager** et **Collaborateur** peuvent tous les deux créer des tickets. La différence entre ces rôles concerne principalement les droits de validation et le périmètre d'accès.

---

## Tableau récapitulatif

| Fonction / permission | Administrateur | Support IT | Manager | Collaborateur | Périmètre / remarque |
| --- | :---: | :---: | :---: | :---: | --- |
| Créer un ticket | — | — | ✅ | ✅ | Manager et Collaborateur peuvent utiliser la création de ticket |
| Consulter les tickets actifs | ✅ | ✅ | ✅ | ✅ | Le périmètre dépend du rôle |
| Consulter tous les tickets | ✅ | ❌ | ❌ | ❌ | Réservé à l'Administrateur |
| Voir les tickets de l'équipe Support IT | ✅ | ✅ | ❌ | ❌ | Permet la continuité de traitement en cas d'absence |
| Prendre en charge un ticket | ✅ | ✅ | ❌ | ❌ | Traitement Support IT / administration |
| Modifier le statut d'un ticket | ✅ | ✅ | ❌ | ❌ | Selon le workflow et les droits applicables |
| Modifier l'importance | ✅ | ✅ | ❌ | ❌ | Selon les droits applicables |
| Échanger dans la conversation | ✅ | ✅ | ✅* | ✅ | *Le Manager peut être limité pendant certaines phases de validation |
| Ajouter une note interne | ✅ | ✅ | ❌ | ❌ | Visible uniquement par les rôles autorisés |
| Ajouter des pièces jointes | ✅ | ✅ | ✅* | ✅ | *Le Manager peut être limité pendant une validation |
| Demander une validation Manager | ✅ | ✅ | ❌ | ❌ | Le Support IT sélectionne le Manager final |
| Valider une demande Manager | ❌ | ❌ | ✅ | ❌ | Uniquement lorsqu'une validation est adressée au Manager |
| Proposer une résolution | ✅ | ✅ | ❌ | ❌ | La proposition est ensuite confirmée par le demandeur |
| Confirmer / refuser une résolution | ❌ | ❌ | ✅** | ✅ | **Lorsque le Manager est lui-même demandeur du ticket |
| Consulter les archives | ✅ | ✅ | ❌ | ❌ | Les tickets résolus / fermés / annulés sortent des files actives Manager / Collaborateur |
| Voir les informations SLA | ✅ | ❌ | ❌ | ❌ | Les SLA détaillés sont réservés à l'Administrateur |
| Gérer les utilisateurs | ✅ | ❌ | ❌ | ❌ | Administration uniquement |
| Importer des utilisateurs CSV / Excel | ✅ | ❌ | ❌ | ❌ | Administration uniquement |
| Gérer les groupes | ✅ | ❌ | ❌ | ❌ | Administration uniquement |
| Consulter les statistiques | ✅ | ❌ | ❌ | ❌ | Administration uniquement |
| Générer les extractions Excel | ✅ | ❌ | ❌ | ❌ | Administration uniquement |
| Consulter / vider le journal d'audit | ✅ | ❌ | ❌ | ❌ | Administration uniquement |
| Gérer la configuration | ✅ | ❌ | ❌ | ❌ | Administration uniquement |
| Gérer la maintenance | ✅ | ❌ | ❌ | ❌ | Administration uniquement |
| Gérer les alertes de service | ✅ | ✅ | ❌ | ❌ | Selon les droits prévus par TicketFlow |

---

# Administrateur

L'Administrateur dispose du périmètre le plus large dans TicketFlow.

Il peut notamment :

- gérer les comptes utilisateurs ;
- modifier les rôles ;
- activer ou désactiver les comptes ;
- importer des utilisateurs depuis CSV / Excel ;
- créer, modifier, désactiver ou supprimer les groupes selon les règles applicables ;
- consulter l'ensemble des tickets ;
- intervenir sur les tickets ;
- consulter les archives ;
- consulter les statistiques ;
- générer les extractions Excel ;
- consulter et vider le journal d'audit ;
- gérer la configuration générale ;
- gérer les alertes de service ;
- activer ou planifier une maintenance ;
- consulter les informations SLA.

## Périmètre

L'Administrateur n'est pas limité à un groupe ou à un demandeur particulier.

Il peut consulter l'ensemble des tickets nécessaires à l'administration et à la supervision de TicketFlow.

## SLA

Les informations SLA détaillées sont visibles uniquement par l'Administrateur dans l'interface.

---

# Support IT

Le rôle **Support IT** correspond aux techniciens chargés de traiter les demandes.

Le Support IT peut notamment :

- consulter les tickets auxquels il est autorisé à accéder ;
- prendre en charge un ticket ;
- modifier son statut ;
- modifier son importance ;
- échanger avec le demandeur ;
- ajouter des notes internes ;
- ajouter les pièces jointes autorisées ;
- transférer ou assigner un ticket selon les règles prévues ;
- demander une validation Manager ;
- sélectionner le Manager chargé de la validation finale ;
- proposer une résolution ;
- suivre les tickets des autres membres Support IT lorsque cela est nécessaire.

## Périmètre

Un membre Support IT n'a pas les mêmes droits globaux qu'un Administrateur.

Son périmètre concerne principalement :

- les tickets à traiter ;
- les tickets déjà pris en charge ;
- les tickets de l'équipe Support IT lorsque le suivi doit être assuré en cas d'absence.

Cette visibilité permet d'éviter qu'un ticket soit bloqué uniquement parce que le technicien initialement assigné est indisponible.

## Nom du rôle

Dans l'interface, le rôle est affiché :

**Support IT**

Pour préserver la compatibilité avec les anciennes versions de TicketFlow, sa valeur technique interne reste :

```text
IT
```

---

# Manager

Le Manager est à la fois un utilisateur classique de TicketFlow et un acteur du workflow de validation.

Comme un Collaborateur, il peut **créer des tickets** lorsqu'il a besoin de contacter le Support IT.

Il peut notamment :

- créer un ticket ;
- consulter les tickets qu'il est autorisé à voir ;
- suivre l'avancement de ses demandes ;
- échanger avec le Support IT lorsqu'une conversation est disponible ;
- intervenir lorsqu'une validation Manager lui est adressée ;
- accepter une demande ;
- refuser une demande ;
- demander des informations complémentaires.

## Périmètre

Le Manager ne dispose pas d'un accès général aux tickets de l'entreprise.

Il accède principalement :

- aux tickets qu'il a créés ;
- aux validations qui lui sont explicitement adressées.

Le fait d'être Manager d'un groupe ne signifie donc pas automatiquement qu'il peut ouvrir ou administrer tous les tickets de ce groupe.

---

## Validation Manager à deux niveaux

Depuis TicketFlow v1.2.0, une demande de validation peut suivre deux étapes.

```text
Support IT
    │
    ├── sélectionne le Manager final
    │
    ▼
Manager N+1 du demandeur
    │
    ├── Refuse / demande des informations
    │
    └── Valide
          │
          ▼
Manager sélectionné
    │
    ├── Valide
    ├── Refuse
    └── Demande des informations
```

### Étape 1 — Manager N+1

Le Manager responsable du demandeur reçoit d'abord la validation.

Il peut :

- accepter ;
- refuser ;
- demander des informations.

Si le N+1 refuse ou demande des informations, la validation ne passe pas automatiquement à l'étape suivante.

### Étape 2 — Manager sélectionné

Après accord du N+1, le Manager choisi par le Support IT reçoit la demande de validation finale.

Cela permet :

- d'informer le responsable direct du demandeur ;
- d'obtenir son accord ;
- puis de transmettre la décision finale au Manager réellement concerné par la demande.

---

## Restrictions pendant une validation

Certaines actions du Manager peuvent être volontairement limitées pendant la phase de validation.

Selon l'état du ticket :

- la zone de message peut être désactivée ;
- l'ajout de pièces jointes peut être indisponible.

Ces restrictions sont appliquées **côté serveur** et ne reposent pas uniquement sur l'affichage de l'interface.

---

# Collaborateur

Le Collaborateur représente l'utilisateur standard de TicketFlow.

Il peut notamment :

- créer un ticket ;
- consulter les tickets qu'il est autorisé à voir ;
- suivre leur avancement ;
- échanger avec le Support IT ;
- ajouter les pièces jointes autorisées ;
- recevoir les notifications liées à ses demandes ;
- confirmer une proposition de résolution ;
- refuser une proposition de résolution si le problème persiste.

## Périmètre

Le Collaborateur ne peut pas consulter les tickets des autres utilisateurs.

Son accès est limité aux demandes qui le concernent selon les règles prévues par TicketFlow.

Il ne peut pas :

- administrer les utilisateurs ;
- modifier les groupes ;
- consulter les statistiques globales ;
- accéder au journal d'audit ;
- voir les informations SLA détaillées ;
- intervenir dans les validations Manager.

---

# Tickets actifs et archives

Les files visibles dépendent du rôle.

## Collaborateur et Manager

Les tickets terminés ne restent pas dans leurs files actives.

Les tickets concernés peuvent notamment être :

- résolus ;
- fermés ;
- annulés.

L'objectif est de conserver une vue centrée sur les demandes nécessitant encore une action.

## Support IT et Administrateur

Les rôles Support IT et Administrateur disposent d'un accès aux vues d'archive prévues par TicketFlow afin de conserver le suivi des anciens tickets.

---

# Informations visibles dans un ticket

Certaines informations sont volontairement limitées selon le rôle.

| Information | Administrateur | Support IT | Manager | Collaborateur |
| --- | :---: | :---: | :---: | :---: |
| Description du ticket | ✅ | ✅ | ✅ | ✅ |
| Conversation autorisée | ✅ | ✅ | ✅ | ✅ |
| Groupe du demandeur | ✅ | ✅ | Limité | Limité |
| Manager du demandeur | ✅ | ✅ | Limité | Limité |
| Support IT assigné | ✅ | ✅ | Selon contexte | Selon contexte |
| Historique détaillé | ✅ | ✅ | ❌ | ❌ |
| Informations SLA | ✅ | ❌ | ❌ | ❌ |
| Notes internes | ✅ | ✅ | ❌ | ❌ |

> « Limité » signifie que l'information peut être affichée uniquement lorsqu'elle est nécessaire au contexte de la page ou du workflow.

---

# Principe de sécurité

Les permissions TicketFlow sont vérifiées côté serveur.

Cela signifie que :

- masquer un bouton n'est pas considéré comme une protection ;
- modifier manuellement une URL ne doit pas permettre d'accéder à une page interdite ;
- une requête HTTP envoyée directement doit subir les mêmes contrôles que l'interface ;
- un utilisateur ne doit pouvoir agir que sur les tickets autorisés pour son rôle et son périmètre.

L'interface reflète les permissions, mais **le serveur reste toujours la source de vérité**.
