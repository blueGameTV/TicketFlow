# Import utilisateurs CSV / Excel

La fonctionnalité d'import est réservée à l'Administrateur.

## Formats supportés

- CSV ;
- Excel `.xlsx`.

La taille maximale actuelle du fichier est de **5 Mo** et un import peut contenir jusqu'à **500 utilisateurs**.

## Modèles

La page d'import fournit :

- un modèle CSV ;
- un modèle Excel.

Il est recommandé de partir de l'un de ces modèles.

## Colonnes obligatoires

```text
prenom
nom
identifiant
email
role
```

## Colonnes facultatives

```text
groupe
manager
date_arrivee
actif
mot_de_passe
forcer_changement_mot_de_passe
```

## Prévisualisation

Avant de créer les comptes, TicketFlow analyse le fichier et vérifie notamment :

- les colonnes attendues ;
- les rôles ;
- les groupes ;
- les Managers ;
- les doublons ;
- les lignes invalides.

Les erreurs doivent être corrigées avant l'import final.

## Mots de passe

Si aucun mot de passe n'est fourni, TicketFlow peut générer une valeur sécurisée selon le fonctionnement prévu par l'import.

La colonne `forcer_changement_mot_de_passe` permet d'imposer le renouvellement lors de la prochaine connexion.

## Groupes et Managers

Lorsqu'un groupe est indiqué, TicketFlow vérifie son existence et les règles de rattachement applicables.

Un Manager utilisé comme responsable doit disposer du rôle Manager et d'un compte actif.

## Excel

Le lecteur XLSX a été renforcé afin de prendre en charge les modèles réenregistrés avec Microsoft Excel.
