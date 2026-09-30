# TicketFlow v1.2.0 Stable

TicketFlow v1.2.0 poursuit la stabilisation de l'interface et ajoute plusieurs fonctions d'administration et de workflow.

## Interface et préférences

- thème clair, sombre ou système par utilisateur ;
- thème clair utilisé par défaut pour les nouveaux profils ;
- nombreux correctifs de contraste en thème sombre ;
- interface Français / English, avec anglais encore indiqué comme bêta ;
- calendriers TicketFlow personnalisés avec saisie manuelle ;
- amélioration des conversations, pièces jointes et formulaires.

## Sécurité et installation

- nouveau changement forcé du mot de passe dans une fenêtre bloquante ;
- ancien mot de passe non demandé lorsqu'un Administrateur impose le changement ;
- contrôles du mot de passe côté client et serveur ;
- refonte de `setup.php` ;
- maintien du verrouillage de l'assistant après installation.

## Workflow Manager

Le Support IT peut sélectionner le Manager qui doit prendre la décision finale. La validation suit maintenant deux étapes :

1. le Manager N+1 du demandeur valide ou refuse ;
2. après accord du N+1, le Manager sélectionné reçoit la validation finale.

Un refus ou une demande d'informations du N+1 arrête la chaîne avant la seconde étape.

## Import utilisateurs

- import CSV et XLSX ;
- prévisualisation avant création ;
- détection des doublons et erreurs par ligne ;
- rattachement aux groupes et contrôle du Manager ;
- modèles CSV et Excel téléchargeables ;
- génération sécurisée d'un mot de passe lorsqu'il n'est pas fourni ;
- lecteur XLSX compatible avec les fichiers réenregistrés par Microsoft Excel.

## Alertes, maintenance et tickets

- historique des alertes de service modernisé ;
- suppression de l'historique résolu par l'Administrateur ;
- conversation de ticket avec zone défilable ;
- présentation des pièces jointes adaptée aux rôles ;
- corrections des statuts, priorités et traductions ;
- amélioration des boutons, cartes et contrastes.

## Mise à niveau

Les installations v1.0.0 et v1.1.0 peuvent être mises à jour directement avec :

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/update.sh | sudo bash
```

Le script sauvegarde automatiquement les données et applique les migrations v1.1 puis v1.2 lorsque nécessaire.
