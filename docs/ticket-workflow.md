# Workflow des tickets

## Cycle principal

```text
Nouveau
  ↓
Attribué
  ↓
En cours
  ↓
Attente utilisateur / Attente Manager si nécessaire
  ↓
Proposition de résolution
  ↓
Attente validation utilisateur
  ↓
Résolu
  ↓
Fermé / Archive
```

Un ticket peut également être annulé selon les permissions applicables.

## Validation Manager à deux niveaux

Lorsqu'une validation est nécessaire, le Support IT sélectionne le Manager qui doit effectuer la validation finale.

```text
Support IT
   │
   ├─ sélectionne le Manager final
   │
   ▼
Manager N+1 du demandeur
   │
   ├─ refuse / demande des informations → arrêt de la chaîne
   │
   └─ valide
        │
        ▼
Manager sélectionné
   │
   ├─ valide
   ├─ refuse
   └─ demande des informations
```

Le Manager sélectionné n'est sollicité qu'après validation du N+1.

## Résolution

Le Support IT décrit la solution et la propose au demandeur.

Le ticket passe alors en attente de confirmation utilisateur. Le demandeur peut :

- confirmer la résolution ;
- refuser la résolution si le problème persiste.

La confirmation permet au ticket de poursuivre vers l'état résolu puis l'archivage prévu par le workflow.

## Archives

Les tickets résolus, fermés et annulés sont retirés des files actives lorsque le rôle concerné ne doit plus les afficher. Les Administrateurs et Support IT disposent des vues d'archive nécessaires au suivi.
