# Changelog

Historique des versions stables et des principales versions de développement de TicketFlow.

Ce fichier conserve uniquement :

- **v1.0.0 Stable**
- les versions de développement de **v1.1.0**
- **v1.1.0 Stable**
- les versions de développement de **v1.2.0**
- **v1.2.0 Stable**

---

# v1.2.0

## v1.2.0 — Stable

TicketFlow v1.2.0 améliore fortement l’interface, l’administration, les workflows Manager et la personnalisation utilisateur.

### Principales nouveautés

- thème **Clair / Sombre / Système** mémorisé par utilisateur ;
- thème clair utilisé par défaut ;
- interface **Français / English (Beta)** ;
- renommage visuel du rôle `IT` en **Support IT** ;
- changement forcé du mot de passe via une fenêtre bloquante ;
- nouvel indicateur de robustesse du mot de passe ;
- refonte de l’assistant `setup.php` ;
- validation Manager à deux niveaux :
  - validation du Manager N+1 ;
  - validation finale du Manager sélectionné par le Support IT ;
- import massif d’utilisateurs CSV / XLSX ;
- modèles CSV et Excel téléchargeables ;
- prévisualisation et contrôles avant import ;
- compatibilité Excel renforcée ;
- calendriers TicketFlow personnalisés ;
- amélioration des conversations et pièces jointes ;
- amélioration des alertes de service et de leur historique ;
- amélioration des paramètres, notifications, statistiques et pages d’administration ;
- mise à jour automatique prise en charge depuis **v1.0.0** et **v1.1.0**.

---

## v1.2.0-dev.6.2 — Correctifs finaux

Dernière build de développement utilisée avant la stabilisation de v1.2.0.

### Tickets

- correction du sélecteur **Importance** dans la fiche ticket ;
- suppression de l’erreur `Undefined array key "code"` ;
- restauration des niveaux :
  - Faible ;
  - Normale ;
  - Haute ;
  - Critique ;
- correction du libellé du sélecteur de statut ;
- amélioration du contraste du badge **Interne Support IT** ;
- corrections supplémentaires de la conversation en thème sombre ;
- amélioration des cartes de pièces jointes et de leur état au survol.

### Import CSV / Excel

- correction de la lecture des cellules `sharedStrings` produites par Microsoft Excel ;
- compatibilité conservée avec `inlineStr` ;
- détection correcte des colonnes :
  - `prenom` ;
  - `nom` ;
  - `identifiant` ;
  - `email` ;
  - `role` ;
- remplacement du lecteur XLSX par un parseur plus robuste ;
- prise en charge des fichiers réenregistrés avec Microsoft Excel ;
- normalisation des en-têtes avec espaces spéciaux, BOM et caractères invisibles ;
- amélioration des messages d’erreur lors d’un import invalide ;
- correction du déploiement de la page `admin-user-import.php`.

### Extractions Excel

- génération XLSX renforcée ;
- vérification des fichiers avant téléchargement ;
- suppression des sorties parasites pouvant corrompre un fichier Excel ;
- en-têtes HTTP de téléchargement centralisés ;
- meilleure compatibilité avec Microsoft Excel et Chrome.

### Langues

- correction de la migration `default_language` ;
- migration rendue idempotente ;
- compatibilité lorsque `user_preferences.language` existe déjà ;
- correction de l’ordre d’exécution des migrations ;
- protection supplémentaire lors du chargement du header.

### Interface

- corrections finales du thème sombre ;
- amélioration de la lisibilité des descriptions ;
- amélioration des boutons ;
- amélioration de la carte **Version installée** ;
- corrections des calendriers TicketFlow ;
- amélioration des espacements de la page Alertes de service ;
- corrections du cache-busting des fichiers CSS et JavaScript.

---

## v1.2.0-dev.6 — Finitions interface et administration

- refonte supplémentaire de la section **Interface** dans les paramètres ;
- amélioration de la section Notifications ;
- badge **BETA** sur les fonctions encore en évolution ;
- thème clair défini comme valeur par défaut ;
- amélioration de la page **Changer mon mot de passe** ;
- indicateur de robustesse du mot de passe ;
- badge Beta pour l’anglais ;
- ajout d’un bouton de suppression utilisateur ;
- corrections des traductions de statuts ;
- amélioration des règles de rattachement lors de l’import ;
- amélioration des calendriers avec saisie manuelle ;
- ajout du bouton **Supprimer tout l’historique** dans les alertes de service ;
- ajout d’une scrollbar dans les conversations longues ;
- repositionnement des pièces jointes pour Collaborateur et Manager.

---

## v1.2.0-dev.5 — Multilingue FR / EN

- ajout d’un système centralisé de traduction ;
- français et anglais disponibles ;
- anglais identifié comme **Beta** ;
- espagnol reporté à une version ultérieure ;
- langue enregistrée par utilisateur ;
- langue par défaut configurable par l’Administrateur ;
- traduction progressive :
  - navigation ;
  - paramètres ;
  - dashboard ;
  - création de ticket ;
  - pages d’administration ;
  - tickets ;
  - formulaires ;
- migration de la préférence de langue.

### Correctifs

- correction de l’erreur fatale liée à la colonne `language` absente ;
- correction de la migration utilisant une colonne `id` inexistante dans `app_settings`.

---

## v1.2.0-dev.4 — Import utilisateurs CSV / Excel

- ajout de l’import massif d’utilisateurs ;
- prise en charge des formats :
  - CSV ;
  - XLSX ;
- limite de 500 utilisateurs par fichier ;
- limite de 5 Mo ;
- modèles CSV et Excel disponibles ;
- prévisualisation avant création ;
- validation des colonnes ;
- validation des rôles ;
- rattachement aux groupes ;
- contrôle des Managers ;
- détection des doublons ;
- génération automatique d’un mot de passe si nécessaire ;
- possibilité de forcer le changement de mot de passe ;
- affichage des erreurs ligne par ligne.

### Correctifs Excel

- correction de l’erreur 404 sur l’import ;
- correction des modèles Excel considérés comme endommagés ;
- amélioration des exports XLSX ;
- amélioration de la compatibilité avec Microsoft Excel ;
- correction des téléchargements signalés incorrectement par le navigateur ;
- correction de la lecture des en-têtes `prenom` et autres colonnes.

---

## v1.2.0-dev.3 — Validation Manager à deux niveaux

Le workflow de validation Manager a été entièrement revu.

### Nouveau fonctionnement

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
    └── Refuse
```

### Changements

- sélection d’un Manager précis par le Support IT ;
- passage obligatoire par le Manager N+1 ;
- notification du Manager final uniquement après accord du N+1 ;
- arrêt de la chaîne en cas de refus ou demande d’informations du N+1 ;
- affichage clair de :
  - Étape 1 — N+1 ;
  - Étape 2 — Manager sélectionné ;
- migration de `manager_approvals` avec :
  - étape ;
  - Manager cible ;
  - lien entre les validations.

---

## v1.2.0-dev.2 — Mot de passe forcé et setup.php

### Changement forcé du mot de passe

- remplacement de l’ancienne redirection par une fenêtre bloquante ;
- floutage du Dashboard en arrière-plan ;
- impossibilité d’utiliser TicketFlow avant le changement ;
- ancien mot de passe non demandé lorsqu’un Administrateur impose le renouvellement ;
- validation du nouveau mot de passe côté client et serveur ;
- blocage serveur des autres pages tant que le renouvellement n’est pas terminé.

### Installation

- refonte complète de `setup.php` ;
- meilleure présentation des erreurs ;
- meilleure présentation du succès de l’installation ;
- amélioration des règles de sécurité lors de la création du premier Administrateur.

---

## v1.2.0-dev.1 — Thèmes et identité Support IT

- ajout du thème personnel :
  - Clair ;
  - Sombre ;
  - Système ;
- préférence mémorisée par utilisateur ;
- amélioration générale du thème sombre ;
- corrections des textes et surfaces trop sombres ou trop clairs ;
- renommage visuel du rôle **IT** en **Support IT** ;
- conservation de la valeur technique interne `IT` ;
- harmonisation des libellés Support IT ;
- amélioration de la page **À propos & Nouveautés** ;
- amélioration des statistiques en thème sombre ;
- refonte de l’historique des alertes de service ;
- amélioration des boutons et descriptions ;
- premières améliorations du calendrier TicketFlow ;
- amélioration de la conversation et des pièces jointes.

### Correctifs intermédiaires

Les builds `v1.2.0-dev.1.1` et `v1.2.0-dev.1.2` ont apporté des corrections supplémentaires de contraste, de thème sombre, de boutons, de calendriers et de mise en page.

---

# v1.1.0

## v1.1.0 — Stable

Deuxième version stable de TicketFlow.

### Alertes de service

- alertes de service globales ;
- titre limité à 80 caractères ;
- message jusqu’à 2000 caractères ;
- affichage du titre dans la bannière ;
- détail complet au clic ;
- accès depuis la barre supérieure pour Administrateur et IT.

### À propos & Nouveautés

- nouvelle page affichant :
  - version installée ;
  - créateur ;
  - technologies ;
  - historique des versions ;
- journal des nouveautés par utilisateur ;
- possibilité de marquer les nouveautés comme consultées.

### Maintenance

- mode maintenance immédiat ;
- redirection automatique des Collaborateurs et Managers ;
- Administrateurs toujours autorisés ;
- accès IT configurable ;
- page de maintenance dédiée ;
- correction de la déconnexion depuis la page de maintenance ;
- fin automatique à l’heure prévue ;
- maintenances planifiées ;
- bandeau d’information avant intervention.

### Extractions et interface

- nouvelle extraction Excel des alertes et maintenances ;
- amélioration des menus déroulants ;
- amélioration des champs de recherche ;
- amélioration des champs d’upload ;
- amélioration des formulaires ;
- améliorations visuelles des pages de maintenance et d’administration.

### Mise à jour

- introduction du script `update.sh` ;
- mise à jour d’une installation v1.0.0 sans réinstallation ;
- sauvegarde automatique avant mise à jour ;
- conservation :
  - base de données ;
  - comptes ;
  - tickets ;
  - groupes ;
  - configuration ;
  - pièces jointes.

---

## v1.1.0-dev.5 — Finitions maintenance et formulaires

- derniers ajustements du mode maintenance immédiat ;
- menus déroulants TicketFlow entièrement personnalisés ;
- navigation clavier des menus ;
- synchronisation des menus avec les formulaires ;
- finition visuelle des champs ;
- finition des cases à cocher ;
- amélioration des actions de maintenance.

---

## v1.1.0-dev.3 — Alertes et maintenance

- correction de la fin automatique du mode maintenance ;
- prise en compte correcte du fuseau horaire TicketFlow ;
- les alertes globales n’affichent plus que leur titre dans la bannière ;
- clic sur une alerte pour consulter le message complet ;
- titre limité à 80 caractères ;
- message limité à 2000 caractères ;
- nouvelle mise en page de **À propos & Nouveautés** ;
- améliorations visuelles de la page de maintenance.

---

## v1.1.0-dev.2 — Maintenance et interface

- redirection automatique des Collaborateurs et Managers lors d’une maintenance ;
- fin automatique lorsque la date estimée est atteinte ;
- correction de la déconnexion depuis la page de maintenance ;
- nouvelle présentation de la page de maintenance ;
- nouvelle présentation de **À propos & Nouveautés** ;
- accès aux alertes de service déplacé dans la barre supérieure.

---

## v1.1.0-dev.1 — Première intégration v1.1

- ajout des alertes de service globales ;
- ajout de la page **À propos & Nouveautés** ;
- ajout du journal des nouveautés par utilisateur ;
- ajout du mode maintenance TicketFlow ;
- ajout des maintenances planifiées ;
- ajout de l’extraction Excel des alertes et maintenances ;
- ajout des premières migrations v1.1.0.

### Correctifs de développement

- correction de la migration SQL incompatible avec les requêtes préparées MariaDB ;
- correction du script de vérification `check_v110_dev1.php` ;
- amélioration de la robustesse de l’installation de développement.

---

# v1.0.0

## v1.0.0 — Première version stable

Première version stable publique de TicketFlow.

### Fonctionnalités principales

- gestion complète des tickets IT ;
- quatre rôles :
  - Administrateur ;
  - IT ;
  - Manager ;
  - Collaborateur ;
- gestion des groupes et Managers ;
- conversation dans les tickets ;
- pièces jointes ;
- notifications ;
- recherche globale ;
- filtres avancés ;
- vues enregistrées ;
- actions multiples ;
- SLA ;
- exports Excel ;
- statistiques ;
- journal d’audit ;
- e-mails et automatisations ;
- assistant d’installation sécurisé ;
- installation automatique Debian / Ubuntu.

### Installation

```bash
curl -fsSL https://raw.githubusercontent.com/blueGameTV/TicketFlow/main/install.sh | sudo bash
```

La v1.0.0 constitue la base stable à partir de laquelle les systèmes de mise à jour v1.1.0 puis v1.2.0 ont été développés.
